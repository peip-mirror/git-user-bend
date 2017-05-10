<?php

namespace Stolt\GitUserBend\Tests\Git;

use \phpmock\phpunit\PHPMock;
use Stolt\GitUserBend\Exceptions\NonExistentGlobalGitConfiguration;
use Stolt\GitUserBend\Exceptions\FailedToCreateDirectory;
use Stolt\GitUserBend\Exceptions\FailedToCreateDefaultConfigurationFile;
use Stolt\GitUserBend\Git\Configuration;
use Stolt\GitUserBend\Git\User;
use Stolt\GitUserBend\Tests\TestCase;

class ConfigurationTest extends TestCase
{
    use PHPMock;

    /**
     * Set up test environment.
     */
    protected function setUp()
    {
        $this->setUpTemporaryDirectory();
    }

    /**
     * Tear down test environment.
     *
     * @return void
     */
    protected function tearDown()
    {
        if (is_dir($this->temporaryDirectory)) {
            $this->removeDirectory($this->temporaryDirectory);
        }
    }

    /**
     * @test
     * @group unit
     */
    public function returnsGlobalConfigurationFile()
    {
        $configuration = new Configuration($this->temporaryDirectory);

        $expectedGlobalConfigurationFile = $this->createTemporaryGitConfiguration('');

        $this->assertSame($expectedGlobalConfigurationFile, $configuration->getConfigurationFile());
    }

    /**
     * @test
     * @group unit
     */
    public function returnsGlobalConfigurationFileWhenLocatedInSubfolder()
    {
        $configuration = new Configuration($this->temporaryDirectory);

        $directories = ['.config', 'git'];
        $this->createTemporaryGitConfiguration('', $directories, 'gitconfig');

        $expectedGlobalConfigurationFile = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . implode(DIRECTORY_SEPARATOR, $directories)
            . DIRECTORY_SEPARATOR
            . 'gitconfig';

        $this->assertSame($expectedGlobalConfigurationFile, $configuration->getConfigurationFile());
    }

    /**
     * @test
     * @group unit
     */
    public function throwsExpectedExceptionForNonExistentGlobalGitConfigurationFile()
    {
        $this->expectException(NonExistentGlobalGitConfiguration::class);
        $expectedExceptionMessage = "No global Git configuration present in '{$this->temporaryDirectory}'";
        $this->expectExceptionMessage($expectedExceptionMessage);

        $configuration = new Configuration($this->temporaryDirectory);

        $this->createTemporaryGitConfiguration('', [], '.non-glob-matching-name');

        $configuration->getConfigurationFile();
    }

    /**
     * @test
     * @group unit
     */
    public function createsExpectedConditionalConfigurationDotfile()
    {
        $configuration = new Configuration($this->temporaryDirectory);

        $this->createTemporaryGitConfiguration('');

        $conditionalConfigurationDotfilename = '.work-gitconfig';
        $user = new User('John Doe', 'john.doe@example.org');

        $expectedConditionalConfigurationDotfileContent = <<<CONTENT
[user]
    email = {$user->getEmail()}
    name = {$user->getName()}
CONTENT;
        $expectedConditionalConfigurationDotfile = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . $conditionalConfigurationDotfilename;

        $configuration->createConditionalConfigurationDotfile(
            $conditionalConfigurationDotfilename,
            $user
        );

        $this->assertStringEqualsFile(
            $expectedConditionalConfigurationDotfile,
            $expectedConditionalConfigurationDotfileContent
        );
    }

    /**
     * @test
     * @group unit
     */
    public function addExpectedIfIncludeToAnExistingGlobalGitConfiguration()
    {
        $configuration = new Configuration($this->temporaryDirectory);

        $globalGitConfigurationContent = <<<CONTENT
[user]
    name = John Doe
    email = john.doe@example.org
[alias]
    co = checkout
CONTENT;
        $globalGitConfigurationFile = $this->createTemporaryGitConfiguration(
            $globalGitConfigurationContent
        );

        $conditionalConfigurationDotfilename = '.work-gitconfig';
        $conditionalConfigurationGitDirectory = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR . 'git-repos';

        $expectedGlobalGitConfigurationContent = <<<CONTENT
[user]
    name = John Doe
    email = john.doe@example.org
[alias]
    co = checkout
[includeIf "gitdir:{$conditionalConfigurationGitDirectory}"]
    path = {$conditionalConfigurationDotfilename}
CONTENT;

        $configuration->addIfInclude(
            $conditionalConfigurationDotfilename,
            $conditionalConfigurationGitDirectory
        );

        $this->assertStringEqualsFile(
            $globalGitConfigurationFile,
            $expectedGlobalGitConfigurationContent
        );
    }

    /**
     * @test
     * @group unit
     */
    public function addExpectedIfIncludeToANonExistingGlobalGitConfiguration()
    {
        $configuration = new Configuration($this->temporaryDirectory);

        $conditionalConfigurationDotfilename = '.work-gitconfig';
        $conditionalConfigurationGitDirectory = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR . 'git-repos';

        $expectedGlobalGitConfigurationContent = <<<CONTENT
[includeIf "gitdir:{$conditionalConfigurationGitDirectory}"]
    path = {$conditionalConfigurationDotfilename}
CONTENT;

        $configuration->addIfInclude(
            $conditionalConfigurationDotfilename,
            $conditionalConfigurationGitDirectory,
            true
        );

        $this->assertStringEqualsFile(
            $configuration->getDefaultConfigurationFile(),
            $expectedGlobalGitConfigurationContent
        );
    }

    /**
     * @test
     * @group unit
     * @runInSeparateProcess
     */
    public function throwsExpectedExceptionWhenCreationOfConfigurationDirectoryFails()
    {
        $configuration = new Configuration($this->temporaryDirectory);
        $directoryFailedToCreate = dirname($configuration->getDefaultConfigurationFile());

        $this->expectException(FailedToCreateDirectory::class);
        $expectedExceptionMessage = "Failed to create directory '{$directoryFailedToCreate}'.";
        $this->expectExceptionMessage($expectedExceptionMessage);

        $mkdir = $this->getFunctionMock(
            'Stolt\GitUserBend\Git',
            'mkdir'
        );
        $mkdir->expects($this->once())->willReturn(false);

        $conditionalConfigurationDotfilename = '.work-gitconfig';
        $conditionalConfigurationGitDirectory = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR . 'git-repos';

        $configuration->addIfInclude(
            $conditionalConfigurationDotfilename,
            $conditionalConfigurationGitDirectory,
            true
        );
    }

    /**
     * @test
     * @group unit
     * @runInSeparateProcess
     */
    public function throwsExpectedExceptionWhenCreationOfDefaultConfigurationFileFails()
    {
        $configuration = new Configuration($this->temporaryDirectory);

        $this->expectException(FailedToCreateDefaultConfigurationFile::class);
        $expectedExceptionMessage = 'Failed to create default Git configuration file '
            . "'{$configuration->getDefaultConfigurationFile()}'.";
        $this->expectExceptionMessage($expectedExceptionMessage);

        $touch = $this->getFunctionMock(
            'Stolt\GitUserBend\Git',
            'touch'
        );
        $touch->expects($this->once())->willReturn(false);

        $conditionalConfigurationDotfilename = '.work-gitconfig';
        $conditionalConfigurationGitDirectory = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR . 'git-repos';

        $configuration->addIfInclude(
            $conditionalConfigurationDotfilename,
            $conditionalConfigurationGitDirectory,
            true
        );
    }
}
