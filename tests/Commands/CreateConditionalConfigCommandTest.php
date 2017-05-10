<?php

namespace Stolt\GitUserBend\Tests\Commands;

use Stolt\GitUserBend\Commands\CreateConditionalConfigCommand;
use Stolt\GitUserBend\Git\Configuration;
use Stolt\GitUserBend\Git\Repository;
use Stolt\GitUserBend\Persona\Storage;
use Stolt\GitUserBend\Tests\CommandTester;
use Stolt\GitUserBend\Tests\TestCase;
use Symfony\Component\Console\Application;

class CreateConditionalConfigCommandTest extends TestCase
{
    /**
     * @var \Symfony\Component\Console\Application
     */
    private $application;

    /**
     * @var string
     */
    private $commandName;

    /**
     * @return \Symfony\Component\Console\Application
     */
    protected function getApplication()
    {
        $application = new Application();
        $this->commandName = 'create-conditional-config';

        $command = new CreateConditionalConfigCommand(
            new Storage(STORAGE_FILE),
            new Repository($this->temporaryDirectory),
            new Configuration(HOME_DIRECTORY)
        );

        $application->add($command);

        return $application;
    }

    /**
     * Set up test environment.
     */
    protected function setUp()
    {
        $this->setUpTemporaryDirectory();

        if (!defined('WORKING_DIRECTORY')) {
            define('WORKING_DIRECTORY', $this->temporaryDirectory);
        }

        if (!defined('HOME_DIRECTORY')) {
            define('HOME_DIRECTORY', $this->temporaryDirectory);
        }

        if (!defined('STORAGE_FILE')) {
            define(
                'STORAGE_FILE',
                HOME_DIRECTORY . DIRECTORY_SEPARATOR . Storage::FILE_NAME
            );
        }

        $this->application = $this->getApplication();
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
     * @group integration
     */
    public function returnsExpectedWarningWhenProvidedDirectoryDoesNotExist()
    {
        $command = $this->application->find($this->commandName);
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => '/out/of/orbit',
            'alias' => 'jo',
            'configuration-name' => 'some-name',
        ]);

        $expectedDisplay = <<<CONTENT
Error: The directory /out/of/orbit doesn't exist.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() == 1);
    }

    /**
     * @test
     * @group integration
     */
    public function returnsExpectedWarningWhenProvidedDirectoryIsNotAGitRepository()
    {
        $command = $this->application->find($this->commandName);
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => $this->temporaryDirectory,
            'alias' => 'jo',
            'configuration-name' => 'some-name',
        ]);

        $expectedDisplay = <<<CONTENT
Error: No Git repository in {$this->temporaryDirectory}.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() == 1);
    }

    /**
     * @test
     * @group integration
     */
    public function returnsExpectedWarningWhenNoGlobalGitConfigFilePresent()
    {
        $this->createTemporaryGitRepository();

        $existingStorageContent = <<<CONTENT
[{"alias":"jd","name":"John Doe","email":"john.doe@example.org","usage_frequency":11},
 {"alias":"so","name":"Some One","email":"some.one@example.org","usage_frequency":23}]
CONTENT;

        $this->createTemporaryStorageFile($existingStorageContent);

        $command = $this->application->find($this->commandName);
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => $this->temporaryDirectory,
            'alias' => 'jd',
            'configuration-name' => 'some-name',
        ]);

        $expectedDisplay = <<<CONTENT
Error: No global Git configuration present in {$this->temporaryDirectory}.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() == 1);
    }

    /**
     * @test
     * @group integration
     */
    public function returnsExpectedWarningWhenNoPersonasDefined()
    {
        $this->createTemporaryGitRepository();

        $command = $this->application->find($this->commandName);
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => $this->temporaryDirectory,
            'alias' => 'jo',
            'configuration-name' => 'some-name',
            '--create-global-git-config' => true,
        ]);

        $expectedDisplay = <<<CONTENT
Error: There are no defined personas.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() == 1);
    }

    /**
     * @test
     * @group integration
     */
    public function returnsExpectedWarningWhenUnknownPersonaAliasProvided()
    {
        $this->createTemporaryGitRepository();

        $existingStorageContent = <<<CONTENT
[{"alias":"jd","name":"John Doe","email":"john.doe@example.org","usage_frequency":11},
 {"alias":"so","name":"Some One","email":"some.one@example.org","usage_frequency":23}]
CONTENT;
        $this->createTemporaryStorageFile($existingStorageContent);

        $command = $this->application->find($this->commandName);
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => $this->temporaryDirectory,
            'alias' => 'jo',
            'configuration-name' => 'some-name',
            '--create-global-git-config' => true,
        ]);

        $expectedDisplay = <<<CONTENT
Error: No known persona for alias jo.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() == 1);
    }

    /**
     * @test
     * @group integration
     * @dataProvider invalidConfigurationNames
     */
    public function returnsExpectedWarningWhenConfigurationNameIsInvalid($invalidConfigurationName, $expectedWarning)
    {
        $this->createTemporaryGitRepository();

        $existingStorageContent = <<<CONTENT
[{"alias":"jd","name":"John Doe","email":"john.doe@example.org","usage_frequency":11},
 {"alias":"so","name":"Some One","email":"some.one@example.org","usage_frequency":23}]
CONTENT;
        $this->createTemporaryStorageFile($existingStorageContent);

        $command = $this->application->find($this->commandName);
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => $this->temporaryDirectory,
            'alias' => 'jd',
            'configuration-name' => $invalidConfigurationName,
            '--create-global-git-config' => true,
        ]);

        $expectedDisplay = <<<CONTENT
Error: {$expectedWarning}.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() == 1);
    }

    /**
     * An invalid alias configuration name provider.
     *
     * @return array
     */
    public function invalidConfigurationNames()
    {
        $maxConfigurationNameLength = Configuration::MAX_CONDITIONAL_NAME_LENGTH;
        $tooLongConfigurationName = str_repeat("a", Configuration::MAX_CONDITIONAL_NAME_LENGTH + 1);

        return [
            'empty_configuration_name' => ['   ', 'The provided configuration name is empty'],
            'numeric_configuration_name' => ['23', 'The provided configuration name is a number'],
            'too_long_configuration_name' => [$tooLongConfigurationName, "The provided configuration name {$tooLongConfigurationName} is longer than "
                . "{$maxConfigurationNameLength} characters"],
        ];
    }

    /**
     * @test
     * @group integration
     */
    public function createsAConditionalConfigurationWithTheExpectedNames()
    {
        // configuration-name = .some-name
        // configuration-name = another-name
        // configuration-name = ThatOtherName
        $this->markTestIncomplete('TBD');
    }

    /**
     * @test
     * @group integration
     */
    public function addsAConditionalConfigurationToNewlyCreatedGlobalGitConfigurationFile()
    {
        $this->markTestIncomplete('TBD');
    }

    /**
     * @test
     * @group integration
     */
    public function addsAConditionalConfigurationToExistingGlobalGitConfigurationFile()
    {
        // $HOME/.gitconfig
        // $HOME/.config/git/config
        // $HOME/.config/git/.config
        $this->markTestIncomplete('TBD');
    }
}
