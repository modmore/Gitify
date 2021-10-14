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
                'config',
                'c',
                InputArgument::OPTIONAL,
                'Path to XML configuration file. If specified, Gitify won\'t ask for configuration details through the command line.'
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
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $version = $this->input->getArgument('version');
        $configFile = $this->input->getOption('config');
        $forced = $this->input->getOption('download');

        if (!$this->getMODX($version, $forced)) {
            return 1; // exit
        }

        // Variables for running the setup
        $tz = date_default_timezone_get();
        $wd = GITIFY_WORKING_DIR;
        $configXmlFile = $wd . 'config.xml';
        $providedConfig = [];

        // Create the XML config and config array
        if ($configFile && !file_exists($configFile)) {
            $output->writeln("<error>Unable to load specified config file.</error>");
            return 1;
        }

        // Load XML config from file
        if ($configFile && file_exists($configFile)) {
            $configXmlFile = $configFile;
            $xml = simplexml_load_file($configXmlFile, "SimpleXMLElement", LIBXML_NOCDATA);
            $json = json_encode($xml);
            $providedConfig = json_decode($json, true);
            unset($providedConfig['comment']);
        }

        $config = $this->createMODXConfig($providedConfig);

        $output->writeln("Running MODX Setup...");

        // Move core to alternative location if specified
        $corePathParameter = '--core_path=' . $config['core_path_full'];
        if ($config['core_path_full'] !== $wd . 'core/') {
            if (!file_exists($config['core_path'])) {
                mkdir($config['core_path'], 0777, true);
            }
            $corePathParameter = '--core_path=' . $config['core_path'] . $config['core_name'] . '/';
            if (!rename($wd . 'core', $config['core_path'] . $config['core_name'])) {
                $output->writeln("<warning>Moving core folder wasn't possible</warning>");
                return 0;
            }
        }

        // Only the manager directory name can be changed on install. It can't be moved.
        if ($config['context_mgr_path'] !== $wd . 'manager/') {
            if (!rename($wd . 'manager', $config['context_mgr_path'])) {
                $output->writeln("<warning>Renaming manager folder wasn't possible</warning>");
                return 0;
            }
        }

        // Actually run the CLI setup
        exec("php -d date.timezone={$tz} {$wd}setup/index.php --installmode=new --config={$configXmlFile} {$corePathParameter}", $setupOutput);
        $output->writeln("<comment>{$setupOutput[0]}</comment>");

        // Try to clean up the config file
        if (!$configFile && !unlink($configXmlFile)) {
            $output->writeln("<warning>Warning:: could not clean up the setup config file, please remove this manually.</warning>");
        }

        $output->writeln('Done! ' . $this->getRunStats());
        return 0;
    }

    /**
     * Asks the user to complete a bunch of details and creates a MODX CLI config xml file
     * @param array $providedConfig
     * @return array
     */
    protected function createMODXConfig(array $providedConfig): array
    {
        $directory = GITIFY_WORKING_DIR;

        // Creating config xml to install MODX with
        $this->output->writeln("Please complete following details to install MODX. Leave empty to use the [default].");

        $helper = $this->getHelper('question');

        $dbHost = $providedConfig['database_server'] ?? '';
        if (!$dbHost) {
            $defaultDbHost = 'localhost';
            $question = new Question("Database Host [{$defaultDbHost}]: ", $defaultDbHost);
            $dbHost = $helper->ask($this->input, $this->output, $question);
        }

        $dbName = $providedConfig['database'] ?? '';
        if (!$dbName) {
            $defaultDbName = basename(GITIFY_WORKING_DIR);
            $question = new Question("Database Name [{$defaultDbName}]: ", $defaultDbName);
            $dbName = $helper->ask($this->input, $this->output, $question);
        }

        $dbUser = $providedConfig['database_user'] ?? '';
        if (!$dbUser) {
            $question = new Question('Database User [root]: ', 'root');
            $dbUser = $helper->ask($this->input, $this->output, $question);
        }

        $dbPass = $providedConfig['database_password'] ?? '';
        if (!$dbPass) {
            $question = new Question('Database Password: ');
            $question->setHidden(true);
            $dbPass = $helper->ask($this->input, $this->output, $question);
        }

        $dbConnectionCharset = $providedConfig['database_connection_charset'] ?? '';
        if (!$dbConnectionCharset) {
            $question = new Question('Database Connection Charset [utf8mb4]: ', 'utf8mb4');
            $dbConnectionCharset = $helper->ask($this->input, $this->output, $question);
        }

        $dbCharset = $providedConfig['database_charset'] ?? '';
        if (!$dbCharset) {
            $question = new Question('Database Charset [utf8mb4]: ', 'utf8mb4');
            $dbCharset = $helper->ask($this->input, $this->output, $question);
        }

        $dbCollation = $providedConfig['database_collation'] ?? '';
        if (!$dbCollation) {
            $question = new Question('Database Collation [utf8mb4_general_ci]: ', 'utf8mb4_general_ci');
            $dbCollation = $helper->ask($this->input, $this->output, $question);
        }

        $dbPrefix = $providedConfig['table_prefix'] ?? '';
        if (!$dbPrefix) {
            $question = new Question('Database Prefix [modx_]: ', 'modx_');
            $dbPrefix = $helper->ask($this->input, $this->output, $question);
        }

        $host = $providedConfig['http_host'] ?? '';
        if (!$host) {
            $question = new Question('Hostname [' . gethostname() . ']: ', gethostname());
            $host = $helper->ask($this->input, $this->output, $question);
            $host = rtrim(trim($host), '/');
        }

        $baseUrl = $providedConfig['context_web_url'] ?? '';
        if (!$baseUrl) {
            $defaultBaseUrl = '/';
            $question = new Question('Base URL [' . $defaultBaseUrl . ']: ', $defaultBaseUrl);
            $baseUrl = $helper->ask($this->input, $this->output, $question);
            $baseUrl = '/' . trim(trim($baseUrl), '/') . '/';
            $baseUrl = str_replace('//', '/', $baseUrl);
        }

        $language = $providedConfig['language'] ?? '';
        if (!$language) {
            $question = new Question('Manager Language [en]: ', 'en');
            $language = $helper->ask($this->input, $this->output, $question);
        }

        $managerUser = $providedConfig['cmsadmin'] ?? '';
        if (!$managerUser) {
            $defaultMgrUser = basename(GITIFY_WORKING_DIR) . '_admin';
            $question = new Question('Manager User [' . $defaultMgrUser . ']: ', $defaultMgrUser);
            $managerUser = $helper->ask($this->input, $this->output, $question);
        }

        $managerPass = $providedConfig['cmspassword'] ?? '';
        if (!$managerPass) {
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
        }

        if ($managerPass == 'generate') {
            $managerPass = substr(str_shuffle(md5(microtime(true))), 0, rand(8, 15));
            $this->output->writeln("<info>Generated Manager Password: {$managerPass}</info>");
        }

        $managerEmail = $providedConfig['cmsadminemail'] ?? '';
        if (!$managerEmail) {
            $question = new Question('Manager Email: ');
            $managerEmail = $helper->ask($this->input, $this->output, $question);
        }

        $corePath = $providedConfig['core_path'] ?? '';
        $defaultCorePath = 'core/';
        if (!$corePath) {
            $question = new Question('Core Path [' . $defaultCorePath . ']: ', $defaultCorePath);
            $corePath = $helper->ask($this->input, $this->output, $question);
        }
        $corePathData = $this->buildPath($corePath, $directory, $defaultCorePath);

        $managerPath = $providedConfig['context_mgr_url'] ?? '';
        $defaultManagerPath = 'manager/';
        if (!$managerPath) {
            $question = new Question('Manager Directory [' . $defaultManagerPath . ']: ', $defaultManagerPath);
            $managerPath = $helper->ask($this->input, $this->output, $question);
        }
        $managerPathData = $this->buildPath(trim($managerPath, '/'), $directory, $defaultManagerPath);
        $managerUrl = $baseUrl . trim($managerPathData['name'], '/') . '/';

        $config = [
            'database_type' => 'mysql',
            'database_server' => $dbHost,
            'database' => $dbName,
            'database_user' => $dbUser,
            'database_password' => $dbPass,
            'database_connection_charset' => $dbConnectionCharset,
            'database_charset' => $dbCharset,
            'database_collation' => $dbCollation,
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
            'core_name' => $corePathData['name'],
            'core_path' => $corePathData['path'],
            'core_path_full' => $corePathData['full_path'],
            'context_mgr_path' => $managerPathData['full_path'],
            'context_mgr_url' => $managerUrl,
            'context_connectors_path' => $directory . 'connectors/',
            'context_connectors_url' => $baseUrl . 'connectors/',
            'context_web_path' => $directory,
            'context_web_url' => $baseUrl,
            'remove_setup_directory' => true
        ];

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
     * @return array
     */
    protected function buildPath($path, $directory, $defaultPath): array
    {
        if (empty($path)) {
            $path = $directory . $defaultPath;
        } elseif (substr($path, 0, 1) == '/') {
            // absolute
            $path = '/' . trim($path, '/') . '/';
        } else {
            // relative
            $path = $directory . trim($path, '/') . '/';
        }

        // Remove directory name from path and return separately.
        $parts = explode('/', trim($path, '/'));
        $coreDirectoryName = array_pop($parts);

        return [
            'full_path' => $path,
            'path' => '/' . implode('/', $parts) . '/',
            'name' => $coreDirectoryName
        ];
    }

}
