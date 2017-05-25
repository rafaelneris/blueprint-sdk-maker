<?php
use PHPUnit\Framework\TestCase;
use BlueprintSdkMaker\Parser;
use Symfony\Component\Finder\Finder;
use BlueprintSdkMaker\Command\MakeCommand;
use Symfony\Component\Console\Tester\CommandTester;
use BlueprintSdkMaker\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

final class ParserTest extends TestCase
{
    protected $rootDir;

    public function setUp()
    {
        $this->rootDir = self::getUniqueTmpDirectory();
    }
    
    public function testValidateApibString()
    {
        $parser = new Parser('bla.apib', $this->rootDir);
        $this->assertEquals($parser->getApib(), 'bla.apib');
    }
    
    public function testAboutCommand()
    {
        $application = new Application();
        $command = $application->get('about');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $expected = "Blueprint SDK Maker - Create SDK client from API blueprint apib file
API Blueprint is a powerful high-level API description language for web APIs.
With this command you will parse doc from API Blueprint and generate a PHP SKD.
See https://github.com/vitormattos/blueprint-sdk-maker/ for more information.\n";
        $this->assertEquals($expected, $commandTester->getDisplay());
    }
    
    public function testHelpCommand()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $ApplicationTester = new ApplicationTester($application);
        $ApplicationTester->run([]);
        $this->assertRegExp('/Blueprint API Maker/', $ApplicationTester->getDisplay());
    }
    
    public function testMakeCommandInvalidApibFile()
    {
        $command = new MakeCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'apib-file' => 'invalid.apib',
            '--directory' => $this->rootDir,
            '--namespace' => 'BlueprintApi'
        ]);
        $output = $commandTester->getDisplay();
        $this->assertEquals("invalid apib file.\n", $output);
    }
    
    /**
     * @dataProvider resourceProvider
     */
    public function testMakeCommand(SplFileInfo $testDirectory)
    {
        $command = new MakeCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'apib-file' => $testDirectory->getRealPath().DIRECTORY_SEPARATOR.'ApiBlueprint.apib',
            '--directory' => $this->rootDir,
            '--namespace' => 'BlueprintApi',
            '--no-phar' => true
        ]);
        $output = $commandTester->getDisplay();
        $this->assertRegExp('/Generate .*.php/', $output);

        $expectedFinder = new Finder();
        $expectedFinder->in($testDirectory->getRealPath() . DIRECTORY_SEPARATOR . 'expected'.DIRECTORY_SEPARATOR.'src/');

        $generatedFinder = new Finder();
        $generatedFinder->in($this->rootDir.DIRECTORY_SEPARATOR.'src');

        $this->assertEquals(count($expectedFinder), count($generatedFinder), 'Failute in generate files');
        
        foreach ($generatedFinder as $generatedFile) {
            $generatedData[$generatedFile->getRelativePathname()] = $generatedFile->getPathName();
        }
        
        foreach ($expectedFinder as $expectedFile) {
            $this->assertArrayHasKey($expectedFile->getRelativePathname(), $generatedData);
            
            if ($expectedFile->isFile()) {
                $expectedPath = $expectedFile->getRealPath();
                $path = $expectedFile->getRelativePathname();
                $actualPath   = $generatedData[ $expectedFile->getRelativePathname() ];

                $this->assertEquals(
                    file_get_contents($expectedPath),
                    file_get_contents($actualPath),
                    "Expected " . $expectedPath . " got " . $actualPath
                    );
            }
        }
    }
    
    public function resourceProvider()
    {
        $finder = new Finder();
        $finder->directories()->in(__DIR__.'/fixtures');
        $finder->depth('< 1');
        
        $data = array();
        
        foreach ($finder as $directory) {
            $data[] = [$directory];
        }
        
        return $data;
    }

    
    public static function getUniqueTmpDirectory()
    {
        $attempts = 5;
        $root = sys_get_temp_dir();
        
        do {
            $unique = $root . DIRECTORY_SEPARATOR . uniqid('blueprint-sdk-maker-test-' . rand(1000, 9000));
            
            if (!file_exists($unique) && mkdir($unique, 0777)) {
                return realpath($unique);
            }
        } while (--$attempts);
        
        throw new \RuntimeException('Failed to create a unique temporary directory.');
    }
    
    private function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir."/".$object))
                        $this->rrmdir($dir."/".$object);
                        else
                            unlink($dir."/".$object);
                }
            }
            rmdir($dir);
        } 
    }
    
    protected function tearDown()
    {
        $this->rrmdir($this->rootDir);
    }
}