<?php
declare(strict_types=1);

namespace Stolt\GitUserBend\Git;

use Stolt\GitUserBend\Exceptions\NonExistentGlobalGitConfiguration;
use Stolt\GitUserBend\Exceptions\FailedToCreateDirectory;
use Stolt\GitUserBend\Exceptions\FailedToCreateDefaultConfigurationFile;
use Stolt\GitUserBend\Git\User;
use Stolt\GitUserBend\Helpers\Str as OsHelper;

class Configuration
{
    const MAX_CONDITIONAL_NAME_LENGTH = 20;

    /**
     * @var string
     */
    private $homeDirectory;

    /**
     * The preferred end of line sequence
     *
     * @var string
     */
    private $preferredEol = "\n";

    /**
     * @var string
     */
    private $globalConfigurationGlobPattern = '{.gitconfig,.config/git/config,.config/git/.gitconfig,.config/git/gitconfig,git/config}';

    /**
     * @var string
     */
    private $globalConfigurationFile;

    /**
     * @param string|null $homeDirectory
     */
    public function __construct(string $homeDirectory = null)
    {
        if ($homeDirectory === null) {
            if ($this->isWindows() === false) {
                $homeDirectory = getenv('HOME');
            } else {
                $homeDirectory = getenv('userprofile');
            }
        }

        $this->homeDirectory = $homeDirectory;
    }

    /**
     * Detect most frequently used end of line sequence.
     *
     * @param  string $content The content to detect the eol in.
     *
     * @return string
     */
    private function detectEol($content)
    {
        $maxCount = 0;
        $preferredEol = $this->preferredEol;
        $eols = ["\n", "\r", "\n\r", "\r\n"];

        foreach ($eols as $eol) {
            if (($count = substr_count($content, $eol)) >= $maxCount) {
                $maxCount = $count;
                $preferredEol = $eol;
            }
        }

        $this->preferredEol = $preferredEol;

        return $preferredEol;
    }

    /**
     * Returns the default configuration file.
     *
     * @return string
     */
    public function getDefaultConfigurationFile(): string
    {
        return $this->homeDirectory
            . DIRECTORY_SEPARATOR . '.config'
            . DIRECTORY_SEPARATOR . 'git'
            . DIRECTORY_SEPARATOR . 'config';
    }

    /**
     * @return string The global Git configuration file.
     * @throws \Stolt\GitUserBend\Exceptions\NonExistentGlobalGitConfiguration
     */
    public function getConfigurationFile(): string
    {
        if ($this->globalConfigurationFile === null) {
            $initialWorkingDirectory = getcwd();
            chdir($this->homeDirectory);

            foreach (glob($this->globalConfigurationGlobPattern, GLOB_BRACE) as $filename) {
                $this->globalConfigurationFile = $this->homeDirectory . DIRECTORY_SEPARATOR . $filename;
                if ((new OsHelper())->isWindows()) {
                    $this->globalConfigurationFile = realpath($this->globalConfigurationFile);
                }
                break;
            }

            chdir($initialWorkingDirectory);

            if ($this->globalConfigurationFile === null) {
                $exceptionMessage = "No global Git configuration present in '{$this->homeDirectory}'.";
                throw new NonExistentGlobalGitConfiguration($exceptionMessage);
            }
        }

        return $this->globalConfigurationFile;
    }

    /**
     * @return string The path to created configuration file.
     * @throws \Stolt\GitUserBend\Exceptions\FailedToCreateDirectory
     * @throws \Stolt\GitUserBend\Exceptions\FailedToCreateDefaultConfigurationFile
     */
    private function createDefaultConfigurationFile(): string
    {
        $defaultConfigurationFile = $this->getDefaultConfigurationFile();
        $defaultConfigurationDirectory = dirname($defaultConfigurationFile);
        if (!mkdir($defaultConfigurationDirectory, 0777, true)) {
            $exceptionMessage = 'Failed to create directory '
                . "'{$defaultConfigurationDirectory}'.";
            throw new FailedToCreateDirectory($exceptionMessage);
        }
        if (!touch($defaultConfigurationFile)) {
            $exceptionMessage = 'Failed to create default Git configuration file '
                . "'{$defaultConfigurationFile}'.";
            throw new FailedToCreateDefaultConfigurationFile($exceptionMessage);
        }

        return $defaultConfigurationFile;
    }

    /**
     * @param string $dotfilename The name of the conditional configuration dotfile.
     * @param string $directory   The directory the ifInclude references.
     * @return boolean
     * @throws \Stolt\GitUserBend\Exceptions\NonExistentGlobalGitConfiguration
     * @throws \Stolt\GitUserBend\Exceptions\FailedToCreateDirectory
     * @throws \Stolt\GitUserBend\Exceptions\FailedToCreateDefaultConfigurationFile
     */
    public function addIfInclude(string $dotfilename, string $directory, bool $create = false): bool
    {
        $additionalIfIncludeContent = <<<CONTENT
[includeIf "gitdir:{$directory}"]
    path = {$dotfilename}
CONTENT;

        if ($this->globalConfigurationFile === null) {
            try {
                $this->globalConfigurationFile = $this->getConfigurationFile();
            } catch (NonExistentGlobalGitConfiguration $e) {
                if ($create === false) {
                    throw $e;
                }
                $this->globalConfigurationFile = $this->createDefaultConfigurationFile();
            }
        }

        $dotfile = pathinfo($this->globalConfigurationFile)['dirname']
            . DIRECTORY_SEPARATOR . $dotfilename;

        $globalConfigurationFileContent = file_get_contents($this->globalConfigurationFile);
        if (trim($globalConfigurationFileContent) !== '') {
            $preferredEol = $this->detectEol($globalConfigurationFileContent);
            $globalConfigurationFileContent.= $preferredEol . $additionalIfIncludeContent;
        } else {
            $globalConfigurationFileContent.= $additionalIfIncludeContent;
        }

        return file_put_contents($this->globalConfigurationFile, $globalConfigurationFileContent) > 0;
    }

    /**
     * @param  string $dotfilename The name of the conditional configuration dotfile.
     * @param  \Stolt\GitUserBend\Git\User   $user        The user to set in conditional configuration dotfile
     * @return boolean
     * @throws \Stolt\GitUserBend\Exceptions\NonExistentGlobalGitConfiguration
     */
    public function createConditionalConfigurationDotfile(string $dotfilename, User $user): bool
    {
        $dotfileContent = <<<CONTENT
[user]
    email = {$user->getEmail()}
    name = {$user->getName()}
CONTENT;

        if ($this->globalConfigurationFile === null) {
            $this->globalConfigurationFile = $this->getConfigurationFile();
        }

        $dotfile = pathinfo($this->globalConfigurationFile)['dirname']
            . DIRECTORY_SEPARATOR . $dotfilename;

        return file_put_contents($dotfile, $dotfileContent) > 0;
    }
}
