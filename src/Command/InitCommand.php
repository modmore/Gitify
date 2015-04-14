<?php
namespace modmore\Gitify\Command;

use modmore\Gitify\Gitify;
use modmore\Gitify\BaseCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Class InitCommand
 *
 * Initiates a new Gitify project by asking some questions and creating the .gitify file.
 *
 * @package modmore\Gitify\Command
 */
class InitCommand extends BaseCommand
{
    public $loadConfig = false;
    public $loadMODX = false;

    protected function configure()
    {
        $this
            ->setName('init')
            ->setDescription('Generates the .gitify file to set up a new Gitify project. Optionally installs MODX as well.')

            ->addOption(
                'overwrite',
                null,
                InputOption::VALUE_NONE,
                'When a .gitify file already exists, and this flag is set, it will be overwritten.'
            )
        ;
    }

    /**
     * Runs the command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Make sure we're not overwriting existing configuration by checking for existing .gitify files
        if (file_exists(GITIFY_WORKING_DIR . '.gitify'))
        {
            // If the overwrite option is set we'll warn the user but continue anyway
            if ($input->getOption('overwrite'))
            {
                $output->writeln('<comment>A Gitify project already exists in this directory. If you continue, this will be overwritten.</comment>');
            }
            // .. otherwise, error out.
            else
            {
                $output->writeln('<error>Error: a Gitify project already exists in this directory.</error> If you wish to continue anyway, specify the --overwrite flag.');
                return 1;
            }
        }

        $helper = $this->getHelper('question');

        // Where we'll store the configuration
        $data = array();

        /**
         * Ask the user for the data directory to store object files in
         */
        $question = new Question('Please enter the name of the data directory (defaults to _data/): ', '_data');
        $directory = $helper->ask($input, $output, $question);
        if (empty($directory)) $directory = '_data/';
        $directory = trim($directory, '/') . '/';
        $data['data_directory'] = $directory;
        mkdir($directory);

        /**
         * Ask the user for a backup directory to store database backups in
         */
        $question = new Question('Please enter the name of the backup directory (defaults to _backup/): ', '_backup');
        $directory = $helper->ask($input, $output, $question);
        if (empty($directory)) $directory = '_backup/';
        $directory = trim($directory, '/') . '/';
        $data['backup_directory'] = $directory;
        mkdir($directory);

        /**
         * Ask if we want to include some default data types
         */
        $dataTypes = array();

        $question = new ConfirmationQuestion('Would you like to include <info>Contexts</info>? <comment>(Y/N)</comment> ', true);
        if ($helper->ask($input, $output, $question)) {
            $dataTypes['contexts'] = array(
                'class' => 'modContext',
                'primary' => 'key',
            );
        }

        $question = new ConfirmationQuestion('Would you like to include <info>Content</info>? <comment>(Y/N)</comment> ', true);
        if ($helper->ask($input, $output, $question)) {
            $dataTypes['content'] = array(
                'type' => 'content',
                'exclude_keys' => array('editedby', 'editedon'),
            );
        }

        $question = new ConfirmationQuestion('Would you like to include <info>Templates</info>? <comment>(Y/N)</comment> ', true);
        if ($helper->ask($input, $output, $question)) {
            $dataTypes['templates'] = array(
                'class' => 'modTemplate',
                'primary' => 'templatename',
                'extension' => '.html',
            );
        }

        $question = new ConfirmationQuestion('Would you like to include <info>Categories</info>? <comment>(Y/N)</comment> ', true);
        if ($helper->ask($input, $output, $question)) {
            $dataTypes['categories'] = array(
                'class' => 'modCategory',
                'primary' => 'category',
                'truncate_on_force' => array('modCategoryClosure'),
            );
        }

        $question = new ConfirmationQuestion('Would you like to include <info>Template Variables</info>? <comment>(Y/N)</comment> ', true);
        if ($helper->ask($input, $output, $question)) {
            $dataTypes['template_variables'] = array(
                'class' => 'modTemplateVar',
                'primary' => 'name',
            );
        }

        $question = new ConfirmationQuestion('Would you like to include <info>Chunks</info>? <comment>(Y/N)</comment> ', true);
        if ($helper->ask($input, $output, $question)) {
            $dataTypes['chunks'] = array(
                'class' => 'modChunk',
                'primary' => 'name',
                'extension' => '.html'
            );
        }

        $question = new ConfirmationQuestion('Would you like to include <info>Snippets</info>? <comment>(Y/N)</comment> ', true);
        if ($helper->ask($input, $output, $question)) {
            $dataTypes['snippets'] = array(
                'class' => 'modSnippet',
                'primary' => 'name',
                'extension' => '.php'
            );
        }

        $question = new ConfirmationQuestion('Would you like to include <info>Plugins</info>? <comment>(Y/N)</comment> ', true);
        if ($helper->ask($input, $output, $question)) {
            $dataTypes['plugins'] = array(
                'class' => 'modPlugin',
                'primary' => 'name',
                'extension' => '.php'
            );
        }

        $data['data'] = $dataTypes;

        /**
         * Turn the configuration into YAML, and write the file.
         */
        $config = Gitify::toYAML($data);
        file_put_contents(GITIFY_WORKING_DIR . '.gitify', $config);
        $output->writeln('<info>Gitify Project initiated and .gitify file written.</info>');

        /**
         * Check if we already have MODX installed, and if not, offer to install it right away.
         */
        if (!file_exists(GITIFY_WORKING_DIR . 'config.core.php')) {

            $question = new ConfirmationQuestion('No MODX installation found in the current directory. Would you like to install the latest stable version? <comment>(Y/N)</comment> ', false);
            if ($helper->ask($input, $output, $question)) {

                $command = $this->getApplication()->find('modx:install');
                $arguments = array(
                    'command' => 'modx:install'
                );
                $input = new ArrayInput($arguments);
                return $command->run($input, $output);
            }

        }

        return 0;
    }
}
