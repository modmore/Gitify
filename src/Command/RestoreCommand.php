<?php
namespace modmore\Gitify\Command;

use modmore\Gitify\Gitify;
use modmore\Gitify\BaseCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Class RestoreCommand
 *
 * Used for restoring database backups created with `gitify backup` (or possibly any other type of mysql backup).
 *
 * @package modmore\Gitify\Command
 */
class RestoreCommand extends BaseCommand
{
    public $loadConfig = true;
    public $loadMODX = true;

    protected function configure()
    {
        $this
            ->setName('restore')
            ->setDescription('Restores the MODX database from a database dump created by `gitify backup`')

            ->addArgument(
                'file',
                InputArgument::OPTIONAL,
                'The file name of the backup to restore; if left empty you will be provided a list of available backups. Specify "last" to use the last backup, based on the file modification time.'
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
        /**
         * @var $database_type
         * @var $database_server
         * @var $database_user
         * @var $database_password
         * @var $dbase
         * @var
         */
        include MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';

        if ($database_type !== 'mysql') {
            $output->writeln('<error>Sorry, only MySQL is supported as database driver currently.</error>');
            return 1;
        }

        // Grab the directory the backups are in
        $backupDirectory = isset($this->config['backup_directory']) ? $this->config['backup_directory'] : '_backup/';
        $targetDirectory = GITIFY_WORKING_DIR . $backupDirectory;

        // Make sure the directory exists
        if (!is_dir($targetDirectory) || !is_readable($targetDirectory)) {
            $output->writeln('<error>Cannot read the {$backupDirectory} folder.</error>');
            return 1;
        }

        // Grab available backups
        $backups = array();
        $directory = new \DirectoryIterator($targetDirectory);
        foreach ($directory as $path => $info) {
            /** @var \SplFileInfo $info */
            $name = $info->getBasename();
            // Ignore dotfiles/folders
            if (substr($name, 0, 1) == '.') {
                continue;
            }

            if ($info->isDir()) {
                continue;
            }

            $modified = $info->getMTime();

            $backups[$name] = array(
                'name' => $name,
                'last_modified' => $modified
            );
        }

        uasort($backups, function($a, $b) {
            if ($a['last_modified'] === $b['last_modified']) {
                return 0;
            }
            return ($a['last_modified'] < $b['last_modified']) ? 1 : -1;
        });

        $file = false;
        $fileInput = $input->getArgument('file');
        if (!empty($fileInput)) {
            if (array_key_exists($fileInput, $backups)) {
                $file = $fileInput;
            }
            elseif (array_key_exists($fileInput . '.sql', $backups)) {
                $file = $fileInput . '.sql';
            }
            elseif ($fileInput === 'last') {
                $file = reset(array_keys($backups));
            }

        }

        if (!$file) {
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                'Please choose the backup to restore (defaults to option 0): ',
                array_keys($backups),
                0
            );
            $question->setErrorMessage('There is no backup with the name %s.');

            $file = $helper->ask($input, $output, $question);
        }

        $output->writeln('Restoring from backup <info>' . $file . '</info>...');

        $database_password = str_replace("'", '\'', $database_password);
        exec("mysql -u {$database_user} -p'{$database_password}' -h {$database_server} {$dbase} < \"{$targetDirectory}{$file}\" ");
        return 0;
    }
}
