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
 * Class BackupCommand
 *
 * Used for creating a quick timestamped mysql dump of the database.
 *
 * @package modmore\Gitify\Command
 */
class BackupCommand extends BaseCommand
{
    public $loadConfig = true;
    public $loadMODX = true;

    protected function configure()
    {
        $this
            ->setName('backup')
            ->setDescription('Generates the .gitify file to set up a new Gitify project. Optionally installs MODX as well.')

            ->addOption(
                'name',
                null,
                InputOption::VALUE_OPTIONAL,
                'Optionally the name of the backup file, useful for milestone backups.'
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

        // Grab the directory to place the backup
        $backupDirectory = isset($this->config['backup_directory']) ? $this->config['backup_directory'] : '_backup/';
        $targetDirectory = GITIFY_WORKING_DIR . $backupDirectory;

        // Make sure the directory exists
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory);
            if (!is_dir($targetDirectory)) {
                $output->writeln('<error>Could not create {$backupDirectory} folder</error>');
                return 1;
            }
        }

        // Compute the name
        $file = $input->getOption('name');
        if (!empty($file)) {
            $file = $this->modx->filterPathSegment($file);
        }
        else {
            $file = date(DATE_ATOM);
        }
        if (substr($file, -4) != '.sql') {
            $file .= '.sql';
        }

        // Full target directory and file
        $targetFile = $targetDirectory . $file;

        if (file_exists($targetFile)) {
            $output->writeln('<error>A file with the name {$file} already exists in {$backupDirectory}.</error>');
            return 1;
        }

        $output->writeln('Writing database backup to <info>' . $file . '</info>...');
        exec("mysqldump -u {$database_user} -p{$database_password} {$dbase} > {$targetFile} ");
        return 0;
    }
}
