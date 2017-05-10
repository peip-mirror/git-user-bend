<?php
namespace Stolt\GitUserBend\Commands;

use Stolt\GitUserBend\Exceptions\CommandFailed;
use Stolt\GitUserBend\Exceptions\Exception;
use Stolt\GitUserBend\Exceptions\InvalidConditionalConfigurationName;
use Stolt\GitUserBend\Exceptions\NonExistentGlobalGitConfiguration;
use Stolt\GitUserBend\Git\Configuration;
use Stolt\GitUserBend\Git\Repository;
use Stolt\GitUserBend\Git\User;
use Stolt\GitUserBend\Persona\Storage;
use Stolt\GitUserBend\Traits\Guards;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateConditionalConfigCommand extends Command
{
    use Guards;

    /**
     * @var Stolt\GitUserBend\Persona\Repository
     */
    private $repository;

    /**
     * @var Stolt\GitUserBend\Persona\Storage
     */
    private $storage;

    /**
     * @var Stolt\GitUserBend\Git\Configuration
     */
    private $gitConfiguration;

    /**
     * @var string
     */
    private $globalGitConfigurationFile;

    /**
     * @var string
     */
    private $globalGitConfigurationDirectory;

    /**
     * Initialize.
     *
     * @param Stolt\GitUserBend\Persona\Storage $storage
     * @param Stolt\GitUserBend\Persona\Git\Repository $repository
     * @return void
     */
    public function __construct(Storage $storage, Repository $repository, Configuration $gitConfiguration)
    {
        $this->storage = $storage;
        $this->repository = $repository;
        $this->gitConfiguration = $gitConfiguration;

        try {
            $this->globalGitConfigurationFile = $this->gitConfiguration->getConfigurationFile();
            $this->globalGitConfigurationDirectory = dirname($this->globalGitConfigurationFile);
        } catch (NonExistentGlobalGitConfiguration $e) {
            $this->globalGitConfigurationFile = $this->gitConfiguration->getDefaultConfigurationFile();
            $this->globalGitConfigurationDirectory = dirname($this->globalGitConfigurationFile);
        }

        parent::__construct();
    }

    /**
     * Command configuration.
     *
     * @return void
     */
    protected function configure()
    {
        $commandDescription = 'Creates a conditional configuration dotfile in '
            . '<comment>' . $this->globalGitConfigurationDirectory . '</comment> '
            . 'and adds a matching includeIf to '
            . '<comment>' . $this->globalGitConfigurationFile . '</comment>.';

        $this->setName('create-conditional-config');
        $this->setDescription($commandDescription);

        $personaArgumentDescription = 'The persona alias to set in the conditional configuration';
        $this->addArgument(
            'alias',
            InputArgument::REQUIRED,
            $personaArgumentDescription
        );

        $configurationNameArgumentDescription = 'The name of the conditional '
            . 'configuration dotfile to create, the leading . can be omitted';
        $this->addArgument(
            'configuration-name',
            InputArgument::REQUIRED,
            $configurationNameArgumentDescription
        );

        $directoryArgumentDescription = 'The directory of the Git repository';
        $this->addArgument(
            'directory',
            InputArgument::OPTIONAL,
            $directoryArgumentDescription,
            WORKING_DIRECTORY
        );

        $createGlobalGitConfigOptionDescription = 'Create a global Git config file when not present';
        $this->addOption(
            '--create-global-git-config',
            'c',
            InputOption::VALUE_NONE,
            $createGlobalGitConfigOptionDescription
        );
    }

    /**
     * Execute command.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $directory = $input->getArgument('directory');

        try {
            $this->repository->setDirectory($directory);

            $alias = $this->guardRequiredAlias($input->getArgument('alias'));
            $alias = $this->guardAlias($alias);
            $persona = $this->storage->all()->getByAlias($alias);
            $user = $persona->factorUser();

            $this->addConditionalConfiguration($input, $user);
        } catch (Exception $e) {
            $error = "<error>Error:</error> " . $e->getInforizedMessage();
            $output->writeln($error);

            return 1;
        }
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Stolt\GitUserBend\Git\User $user
     * @throws Stolt\GitUserBend\Exceptions\InvalidConditionalConfigurationName
     */
    private function addConditionalConfiguration(InputInterface $input, User $user)
    {
        $createGlobalGitConfiguration = false;
        $conditionalConfigurationName = $this->guardConditionalConfigurationName(
            $input->getArgument('configuration-name')
        );

        if ($input->getOption('create-global-git-config')) {
            $createGlobalGitConfiguration = true;
        }

        if ($createGlobalGitConfiguration === false) {
            $this->gitConfiguration->getConfigurationFile();
        }
    }

    /**
     * @param  string $name
     * @return string
     * @throws Stolt\GitUserBend\Exceptions\InvalidConditionalConfigurationName
     */
    private function guardConditionalConfigurationName(string $name): string
    {
        $maxNameLength = Configuration::MAX_CONDITIONAL_NAME_LENGTH;
        if (strlen($name) > $maxNameLength) {
            $exceptionMessage = "The provided configuration name '{$name}' is longer than "
                . "'{$maxNameLength}' characters.";
            throw new InvalidConditionalConfigurationName($exceptionMessage);
        }

        if (trim($name) === '') {
            $exceptionMessage = "The provided configuration name is empty.";
            throw new InvalidConditionalConfigurationName($exceptionMessage);
        }

        if (is_numeric($name)) {
            $exceptionMessage = "The provided configuration name is a number.";
            throw new InvalidConditionalConfigurationName($exceptionMessage);
        }

        return $name;
    }
}
