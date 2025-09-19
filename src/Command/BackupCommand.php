<?php
namespace modmore\Gitify\Command;

use modmore\Gitify\Gitify;
use modmore\Gitify\BaseCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
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
            ->setDescription('Creates a quick backup of the entire MODX database. Runs automatically when using `gitify build --force`, but can also be used manually.')
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'Optionally the name of the backup file, useful for milestone backups. If not specified the file name will be a full timestamp.'
            )
            ->addOption(
                'overwrite',
                'o',
                InputOption::VALUE_NONE,
                'When specified, a backup with the same name will be overwritten if it exists.'
            )
            ->addOption( // https://dev.mysql.com/doc/relnotes/mysql/8.0/en/news-8-0-21.html#mysqld-8-0-21-security
                'no-tablespaces',
                'ntbs',
                InputOption::VALUE_NONE,
                'As of MySQL 8.0.21 (and MySQL 5.7.31), the PROCESS privilege is required to backup tablespaces. To ignore tablespaces in your backup, include this option.'
            )
            ->addOption(
                'compress',
                'c',
                InputOption::VALUE_NONE,
                'When specified, resulting backup file will be gzip compressed.'
            )            
            ->addOption(
                'ignoretables',
                'ignore',
                InputArgument::OPTIONAL,
                'When specified, the tables are ignored. Separate multiple tables with commas.'
            );
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
        $file = $input->getArgument('name');
        if (!empty($file)) {
            $file = $this->modx->filterPathSegment($file);
        }
        else {
            $file = str_replace(':', '', date(DATE_ATOM));
        }

        if (substr($file, -4) !== '.sql') {
            $file .= '.sql';
        }

        if ($input->getOption('compress') && substr($file, -3) !== '.gz') {
            $file .= '.gz';
        }

        // Full target directory and file
        $targetFile = $targetDirectory . $file;

        if (file_exists($targetFile)) {
            $overwrite = $input->getOption('overwrite');
            if (!$overwrite) {
                $output->writeln("<error>A file with the name {$file} already exists in {$backupDirectory}.</error>");
                return 1;
            }
            else {
                $output->writeln("Removing existing file {$file}.");
                unlink($targetFile);
            }
        }

        $output->writeln('Writing database backup to <info>' . $file . '</info>...');
        $database_password = str_replace("'", '\'', $database_password);

        $password_parameter = '';
        if ($database_password != '') {
            $password_parameter = "-p'{$database_password}'";
        }

        $tablespaces = $input->getOption('no-tablespaces') ? ' --no-tablespaces' : '';
        $gzip = $input->getOption('compress') ? '| gzip - ' : '';
        
        $ignoretables = $input->getOption('ignoretables');
        $ignoretables_parameter = '';
        if ($ignoretables) {
            $ignoretables_parameters = [];
            foreach (explode(',', $ignoretables) as $tablename) {
                $ignoretables_parameters[] = '--ignore-table=' . $dbase . '.' . $tablename;
            }
            $ignoretables_parameter = implode(' ', $ignoretables_parameters);
        }
        
        exec("mysqldump{$tablespaces} -u {$database_user} {$password_parameter} -h {$database_server} {$dbase} {$ignoretables_parameter} {$gzip}> {$targetFile} ");

        return 0;
    }
}
