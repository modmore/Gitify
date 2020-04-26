<?php
namespace modmore\Gitify\Command;

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
 * Installs a clean version of MODX.
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
                'The version of MODX to install, in the format 2.3.2-pl. Leave empty or specify "latest" to install the last stable release.',
                'latest'
            )
            ->addOption(
                'config',
                'c',
                InputArgument::OPTIONAL,
                'Path to XML configuration file. Leave empty to enter configuration details through the command line.',
                ''
            )
            ->addOption(
                'download',
                'd',
                InputOption::VALUE_NONE,
                'Force download the MODX package even if it already exists in the cache folder.'
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
        $configFile = $this->input->getOption('config');
        $forced = $this->input->getOption('download');

        if (!$this->getMODX($version, $forced)) {
            return 1; // exit
        }

        if ($configFile && !file_exists($configFile)) {
            $output->writeln("<error>Could not find a valid config file.</error>");
            return 1;
        } else if ($configFile && file_exists($configFile)) {
            // Load config from file
            $config = $configFile;
        } else {
            // Create the XML config
            $config = $this->createMODXConfig();
        }

        // Variables for running the setup
        $tz = date_default_timezone_get();
        $wd = GITIFY_WORKING_DIR;
        $output->writeln("Running MODX Setup...");

        // Actually run the CLI setup
        exec("php -d date.timezone={$tz} {$wd}setup/index.php --installmode=new --config={$config}", $setupOutput);
        $output->writeln("<comment>{$setupOutput[0]}</comment>");

        // Try to clean up the config file
        if (!$configFile && !unlink($config)) {
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
        $directory = GITIFY_WORKING_DIR;

        // Creating config xml to install MODX with
        $this->output->writeln("Please complete following details to install MODX. Leave empty to use the [default].");

        $helper = $this->getHelper('question');
        
        $defaultDbHost = 'localhost';
        $question = new Question("Database Host [{$defaultDbHost}]: ", $defaultDbHost);
        $dbHost = $helper->ask($this->input, $this->output, $question);

        $defaultDbName = basename(GITIFY_WORKING_DIR);
        $question = new Question("Database Name [{$defaultDbName}]: ", $defaultDbName);
        $dbName = $helper->ask($this->input, $this->output, $question);

        $question = new Question('Database User [root]: ', 'root');
        $dbUser = $helper->ask($this->input, $this->output, $question);

        $question = new Question('Database Password: ');
        $question->setHidden(true);
        $dbPass = $helper->ask($this->input, $this->output, $question);

        $question = new Question('Database Prefix [modx_]: ', 'modx_');
        $dbPrefix = $helper->ask($this->input, $this->output, $question);

        $question = new Question('Hostname [' . gethostname() . ']: ', gethostname());
        $host = $helper->ask($this->input, $this->output, $question);
        $host = rtrim(trim($host), '/');

        $defaultBaseUrl = '/';
        $question = new Question('Base URL [' . $defaultBaseUrl . ']: ', $defaultBaseUrl);
        $baseUrl = $helper->ask($this->input, $this->output, $question);
        $baseUrl = '/' . trim(trim($baseUrl), '/') . '/';
        $baseUrl = str_replace('//', '/', $baseUrl);

        $question = new Question('Manager Language [en]: ', 'en');
        $language = $helper->ask($this->input, $this->output, $question);

        $defaultMgrUser = basename(GITIFY_WORKING_DIR) . '_admin';
        $question = new Question('Manager User [' . $defaultMgrUser . ']: ', $defaultMgrUser);
        $managerUser = $helper->ask($this->input, $this->output, $question);

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

        $question = new Question('Manager Email: ');
        $managerEmail = $helper->ask($this->input, $this->output, $question);

        $config = array(
            'database_type' => 'mysql',
            'database_server' => $dbHost,
            'database' => $dbName,
            'database_user' => $dbUser,
            'database_password' => $dbPass,
            'database_connection_charset' => 'utf8',
            'database_charset' => 'utf8',
            'database_collation' => 'utf8_general_ci',
            'table_prefix' => $dbPrefix,
            'https_port' => 443,
            'http_host' => $host,
            'cache_disabled' => 0,
            'inplace' => 1,
            'unpacked' => 0,
            'language' => $language,
            'cmsadmin' => $managerUser,
            'cmspassword' => $managerPass,
            'cmsadminemail' => $managerEmail,
            'core_path' => $directory . 'core/',
            'context_mgr_path' => $directory . 'manager/',
            'context_mgr_url' => $baseUrl . 'manager/',
            'context_connectors_path' => $directory . 'connectors/',
            'context_connectors_url' => $baseUrl . 'connectors/',
            'context_web_path' => $directory,
            'context_web_url' => $baseUrl,
            'remove_setup_directory' => true
        );

        $xml = new \DOMDocument('1.0', 'utf-8');
        $modx = $xml->createElement('modx');

        foreach ($config as $key => $value) {
            $modx->appendChild($xml->createElement($key, htmlentities($value, ENT_QUOTES|ENT_XML1)));
        }

        $xml->appendChild($modx);

        $fh = fopen($directory . 'config.xml', "w+");
        fwrite($fh, $xml->saveXML());
        fclose($fh);

        return $directory . 'config.xml';
    }

}
