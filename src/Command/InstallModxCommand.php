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
            ->setDescription('Downloads, configures and installs a fresh MODX installation.')
            ->addArgument(
                'version',
                InputArgument::OPTIONAL,
                'The version of MODX to install, in the format 2.8.3-pl. Leave empty or specify "latest" to install the last stable release.',
                'latest'
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
        $forced = $this->input->getOption('download');

        if (!$this->getMODX($version, $forced)) {
            return 1; // exit
        }

        // Create the XML config and config array
        $config = $this->createMODXConfig();

        // Variables for running the setup
        $tz = date_default_timezone_get();
        $wd = GITIFY_WORKING_DIR;
        $configXmlFile = $wd . 'config.xml';

        $output->writeln("Running MODX Setup...");

        $corePathParameter = '';
        if ($config['core_path'] !== $wd . 'core/') {
            $corePathParameter = '--core_path='.$config['core_path'];
            if (!rename($wd . 'core', $config['core_path'])) {
                $output->writeln("<warning>moving core folder wasn't possible</warning>");
                // should stop here?
            }
        }

        if ($config['context_mgr_path'] !== $wd . 'manager/') {
            if (!rename($wd . 'manager', $config['context_mgr_path'])) {
                $output->writeln("<warning>rename manager folder wasn't possible</warning>");
                // should stop here?
            }
        }
        // Actually run the CLI setup
        exec("php -d date.timezone={$tz} {$wd}setup/index.php --installmode=new --config={$configXmlFile} {$corePathParameter}", $setupOutput);
        $output->writeln("<comment>{$setupOutput[0]}</comment>");

        // Try to clean up the config file
        if (!unlink($configXmlFile)) {
            $output->writeln("<warning>Warning:: could not clean up the setup config file, please remove this manually.</warning>");
        }

        $output->writeln('Done! ' . $this->getRunStats());
        return 0;
    }

    /**
     * Asks the user to complete a bunch of details and creates a MODX CLI config xml file
     * @param $config
     * @return array
     */
    protected function createMODXConfig($config = [])
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

        /**
         * Ask the user for the core directory
         * this can be a good setting: dirname(GITIFY_WORKING_DIR) .'/modx-core';
         */
        $defaultCorePath = 'core/';
        $question = new Question('Please enter the name of the core directory, can be relative to working dir (defaults to '. $defaultCorePath .'): ', $defaultCorePath);
        $corePathEntry = $helper->ask($this->input, $this->output, $question);

        $corePath = $this->buildPath($corePathEntry, $directory, $defaultCorePath);

        /**
         * Ask the user for the manager directory
         * this can be a good setting: dirname(GITIFY_WORKING_DIR) .'/modx';
         */
        $defaultManagerPath = 'manager/';
        $question = new Question('Please enter the name of the manager directory, it must be relative to working dir (defaults to '. $defaultManagerPath .'): ', $defaultManagerPath);
        $managerPathEntry = $helper->ask($this->input, $this->output, $question);

        $managerPath = $this->buildPath($managerPathEntry, $directory, $defaultManagerPath);
        $managerUrl = $baseUrl . trim($managerPathEntry, '/') . '/';

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
            'core_path' => $corePath,
            'context_mgr_path' => $managerPath,
            'context_mgr_url' => $managerUrl,
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

        return $config;
    }

    /**
     * @param $path
     * @param $directory
     * @param $defaultPath
     * @return string
     */
    protected function buildPath($path, $directory, $defaultPath) {
        if (empty($path)) {
            $path = $directory . $defaultPath;
        } elseif (substr($path, 0, 1) == '/') {
            // absolute
            $path = '/' . trim($path, '/') . '/';
        } else {
            // relative
            $path = $directory . trim($path, '/') . '/';
        }
        return $path;
    }

}
