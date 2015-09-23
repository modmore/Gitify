<?php
namespace modmore\Gitify\Command;

use modmore\Gitify\Gitify;
use modmore\Gitify\BaseCommand;
use modmore\Gitify\Mixins\DownloadModx;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Class BuildCommand
 *
 * Builds a MODX site from the files and configuration.
 *
 * @package modmore\Gitify\Command
 */
class InstallModxCommand extends BaseCommand
{
    use DownloadModx;

    public $loadConfig = false;
    public $loadMODX = false;

    protected function configure()
    {
        $this
            ->setName('modx:install')
            ->setAliases(array('install:modx'))
            ->setDescription('Downloads, configures and installs a fresh MODX installation. [Note: <info>install:modx</info> will be removed in 1.0, use <info>modx:install</info> instead]')
            ->addArgument(
                'version',
                InputArgument::OPTIONAL,
                'The version of MODX to install, in the format 2.3.2-pl. Leave empty or specify "latest" to install the last stable release.'
            )
            ->addOption(
                'config',
                null,
                InputOption::VALUE_OPTIONAL,
                'When --config=file are specified, config vars get from file.'
            
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
        $version = $this->input->getArgument('version');
        if (!$this->download($version)) {
            return 1; // exit
        }

        // Create the XML config
        $config = $this->createMODXConfig();

        // Variables for running the setup
        $tz = date_default_timezone_get();
        $wd = GITIFY_WORKING_DIR;
        $output->writeln("Running MODX Setup...");

        // Actually run the CLI setup
        exec("php -d date.timezone={$tz} {$wd}setup/index.php --installmode=new --config={$config}", $setupOutput);
        $output->writeln($setupOutput[0]);

        // Try to clean up the config file
        if (!unlink($config)) {
            $output->writeln("<warning>Warning:: could not clean up the setup config file, please remove this manually.</warning>");
        }

        $output->writeln('Done! ' . $this->getRunStats());
        return 0;
    }

    /**
     * Asks the user to complete a bunch of details and creates a MODX CLI config xml file
     */
    protected function createMODXConfig()
    {
        // Creating config xml to install MODX with
        $this->output->writeln("Please complete following details to install MODX. Leave empty to use the [default].");

        extract($this->getConfig());

        $directory = isset($modx_dir) ? $modx_dir : GITIFY_WORKING_DIR;
        $table_prefix = isset($table_prefix) ? $table_prefix : 'modx_';
        $database_type = isset($database_type) ? $database_type : 'mysql';

        $helper = $this->getHelper('question');

        if ( !isset($dbase) ) {
            $defaultDbName = basename(GITIFY_WORKING_DIR);
            $question = new Question("Database Name [{$defaultDbName}]: ", $defaultDbName);
            $dbase = $helper->ask($this->input, $this->output, $question);
        };

        if ( !isset($database_user) ) {
            $question = new Question('Database User [root]: ', 'root');
            $database_user = $helper->ask($this->input, $this->output, $question);
        }

        if ( !isset($database_password) ) {
            $question = new Question('Database Password: ');
            $question->setHidden(true);
            $database_password = $helper->ask($this->input, $this->output, $question);
        }

        if ( !isset($database_server) ) {
            $question = new Question('Hostname [' . gethostname() . ']: ', gethostname());
            $database_server = $helper->ask($this->input, $this->output, $question);
            $database_server = rtrim(trim($database_server), '/');
        }

        if ( !isset($modx_base_url) ) {
            $defaultBaseUrl = '/';
            $question = new Question('Base URL [' . $defaultBaseUrl . ']: ', $defaultBaseUrl);
            $modx_base_url = $helper->ask($this->input, $this->output, $question);
            $modx_base_url = '/' . trim(trim($modx_base_url), '/') . '/';
            $modx_base_url = str_replace('//', '/', $modx_base_url);
        }

        if ( !isset($language) ) {
            $question = new Question('Manager Language [en]: ', 'en');
            $language = $helper->ask($this->input, $this->output, $question);
        }

        if ( !isset($defaultMgrUser) ) {
            $defaultMgrUser = basename(GITIFY_WORKING_DIR) . '_admin';
            $question = new Question('Manager User [' . $defaultMgrUser . ']: ', $defaultMgrUser);
            $managerUser = $helper->ask($this->input, $this->output, $question);
        }

        if ( !isset($managerPass) ) {
            $question = new Question('Manager User Password [generated]: ', 'generate');
            $question->setHidden(true);
            $question->setValidator(function ($value) {
                if (empty($value) || strlen($value) < 8) {
                    throw new \RuntimeException(
                        'Please specify a password of at least 8 characters to continue.'
                    );
                }

                return $value;
            });
            $managerPass = $helper->ask($this->input, $this->output, $question);

            if ($managerPass == 'generate') {
                $managerPass = substr(str_shuffle(md5(microtime(true))), 0, rand(8, 15));
                $this->output->writeln("<info>Generated Manager Password: {$managerPass}</info>");
            }
        }

        if ( !isset($managerEmail) ) {
            $question = new Question('Manager Email: ');
            $managerEmail = $helper->ask($this->input, $this->output, $question);
        }

        $configXMLContents = "<modx>
            <database_type>{$database_type}</database_type>
            <database_server>{$database_server}</database_server>
            <database>{$dbase}</database>
            <database_user>{$database_user}</database_user>
            <database_password>{$database_password}</database_password>
            <database_connection_charset>utf8</database_connection_charset>
            <database_charset>utf8</database_charset>
            <database_collation>utf8_general_ci</database_collation>
            <table_prefix>{$table_prefix}</table_prefix>
            <https_port>443</https_port>
            <http_host>{$database_server}</http_host>
            <cache_disabled>0</cache_disabled>
            <inplace>1</inplace>
            <unpacked>0</unpacked>
            <language>{$language}</language>
            <cmsadmin>{$managerUser}</cmsadmin>
            <cmspassword>{$managerPass}</cmspassword>
            <cmsadminemail>{$managerEmail}</cmsadminemail>
            <core_path>{$directory}core/</core_path>
            <context_mgr_path>{$directory}manager/</context_mgr_path>
            <context_mgr_url>{$modx_base_url}manager/</context_mgr_url>
            <context_connectors_path>{$directory}connectors/</context_connectors_path>
            <context_connectors_url>{$modx_base_url}connectors/</context_connectors_url>
            <context_web_path>{$directory}</context_web_path>
            <context_web_url>{$modx_base_url}</context_web_url>
            <remove_setup_directory>1</remove_setup_directory>
        </modx>";

        $fh = fopen($directory . 'config.xml', "w+");
        fwrite($fh, $configXMLContents);
        fclose($fh);

        return $directory . 'config.xml';
    }

     /**
     * return array - config
     */
    protected function getConfig()
    {
        $arr = array();

        if ( $file = $this->input->getOption('config') ) {
            $arr = $this->getConfigFromFile( $file );
        };
        
        return empty($arr) ? $this->getConfigFromYaml() : $arr;
    }

    protected function getConfigFromYaml()
    {
        try {
            $config = Gitify::loadConfig();
        } catch (\RuntimeException $e) {
            return array();
        }

        // try load config from .gitify
        $config = isset($config['config']) ? $config['config'] : array();
        $this->output->writeln("<info>Get config from [.gitify].</info>");

        if ( isset($config['path']) ) {
            $arr = $this->getConfigFromFile( $config['path'] );
            if ( !empty($arr) ) {
                return $arr;
            };
        };
        return $config;
    }

     protected function getConfigFromFile($file)
    {
        if ( file_exists($file) ) {
        // if absolute path to config
        } else if ( file_exists(GITIFY_WORKING_DIR . $file) ) {
            $file = GITIFY_WORKING_DIR . $file;
        } else {
          return array();
        };

        $this->output->writeln("<info>Get config from [{$file}].</info>");

        include($file);
     
        return array_filter(array(
            'modx_dir' => isset($modx_dir) ? $modx_dir : null,
            'table_prefix' => isset($table_prefix) ? $table_prefix : null,
            'database_type' => isset($database_type) ? $database_type : null,
            'dbase' => isset($dbase) ? $dbase : null,
            'database_user' => isset($database_user) ? $database_user : null,
            'database_password' => isset($database_password) ? $database_password : null,
            'database_server' => isset($database_server) ? $database_server : null,
            'modx_base_url' => isset($modx_base_url) ? $modx_base_url : null,
            'language' => isset($language) ? $language : null,
            'defaultMgrUser' => isset($defaultMgrUser) ? $defaultMgrUser : null,
            'managerPass' => isset($managerPass) ? $managerPass : null,
            'managerEmail' => isset($managerEmail) ? $managerEmail : null,
        ));
    }
}
