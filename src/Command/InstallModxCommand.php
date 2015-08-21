<?php
namespace modmore\Gitify\Command;

use modmore\Gitify\BaseCommand;
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
    public $loadConfig = false;
    public $loadMODX = false;
    
    protected function configure()
    {
        $this
            ->setName('modx:install')
            ->setAliases(array('install:modx'))
            ->setDescription('Downloads, configures and installs a fresh MODX installation. [Note: <info>install:modx</info> will be removed in 1.0, use <info>modx:install</info> instead]')

            ->addArgument(
                'modx_version',
                InputArgument::OPTIONAL,
                'The version of MODX to install, in the format 2.3.2-pl. Leave empty or specify "latest" to install the last stable release.'
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
        $downloaded = $this->download();
        if (!$downloaded)
        {
            return 1;
        }

        // Create the XML config
        $config = $this->createMODXConfig();

        // Variables for running the setup
        $tz = date_default_timezone_get();
        $wd = GITIFY_WORKING_DIR;
        $output->writeln("Running MODX Setup...");

        // Actually run the CLI setup
        exec("php -d date.timezone={$tz} {$wd}setup/index.php --installmode=new --config={$config}", $setupOutput);
        $output->writeln("Setup:: {$setupOutput[0]}");

        // Try to clean up the config file
        if (!unlink($config))
        {
            $output->writeln("<warning>Warning:: could not clean up the setup config file, please remove this manually.</warning>");
        }

        $output->writeln('Done! ' . $this->getRunStats());
        return 0;
    }

    /**
     * Downloads the latest version of MODX, and puts it into place in the working directory.
     *
     * @return bool
     */
    protected function download()
    {
        $version = $this->input->getArgument('modx_version');

        if (empty($version) || $version == 'latest') {
            $url = 'http://modx.com/download/latest/';
        }
        else {
            $url = 'http://modx.com/download/direct/modx-' . $version . '.zip';
        }

        $this->output->writeln("Downloading MODX from {$url}...");
        exec('curl -Lo modx.zip ' . $url . ' -#');

        if (!file_exists('modx.zip')) {
            $this->output->writeln('<error>Error: Could not download the MODX zip</error>');
            return false;
        }

        $this->output->writeln("Extracting zip...");
        exec('unzip modx.zip -x "*/./"');

        $insideFolder = exec('ls -F | grep "modx-" | head -1');
        if(empty($insideFolder) || $insideFolder == '/') {
            $this->output->writeln("<error>Error: Could not locate unzipped MODX folder; perhaps the download failed or unzip is not available on your system.</error>");
            return false;
        }

        $this->output->writeln("Moving unzipped files out of temporary directory...");

        exec("mv ./{$insideFolder}* ./");
        exec("rm -r ./{$insideFolder}");

        if (!unlink('modx.zip')) {
            $this->output->writeln("<info>Note: unable to clean up modx.zip file.</info>");
        }
        return true;
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

        $defaultDbName = basename(GITIFY_WORKING_DIR);
        $question = new Question("Database Name [{$defaultDbName}]: ", $defaultDbName);
        $dbName = $helper->ask($this->input, $this->output, $question);

        $question = new Question('Database User [root]: ', 'root');
        $dbUser = $helper->ask($this->input, $this->output, $question);

        $question = new Question('Database Password: ');
        $question->setHidden(true);
        $dbPass = $helper->ask($this->input, $this->output, $question);

        $question = new Question('Hostname [' . gethostname() . ']: ', gethostname());
        $host = $helper->ask($this->input, $this->output, $question);
        $host = rtrim(trim($host), '/');

        $defaultBaseUrl = '/';
        $question = new Question('Base URL [' . $defaultBaseUrl . ']: ', $defaultBaseUrl);
        $baseUrl = $helper->ask($this->input, $this->output, $question);
        $baseUrl = '/' . trim(trim($baseUrl), '/') . '/';
        $baseUrl = str_replace('//','/', $baseUrl);

        $question = new Question('Manager Language [en]: ', 'en');
        $language = $helper->ask($this->input, $this->output, $question);

        $defaultMgrUser = basename(GITIFY_WORKING_DIR) . '_admin';
        $question = new Question('Manager User ['.$defaultMgrUser.']: ', $defaultMgrUser);
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

        $configXMLContents = "<modx>
            <database_type>mysql</database_type>
            <database_server>localhost</database_server>
            <database>{$dbName}</database>
            <database_user>{$dbUser}</database_user>
            <database_password>{$dbPass}</database_password>
            <database_connection_charset>utf8</database_connection_charset>
            <database_charset>utf8</database_charset>
            <database_collation>utf8_general_ci</database_collation>
            <table_prefix>modx_</table_prefix>
            <https_port>443</https_port>
            <http_host>{$host}</http_host>
            <cache_disabled>0</cache_disabled>
            <inplace>1</inplace>
            <unpacked>0</unpacked>
            <language>{$language}</language>
            <cmsadmin>{$managerUser}</cmsadmin>
            <cmspassword>{$managerPass}</cmspassword>
            <cmsadminemail>{$managerEmail}</cmsadminemail>
            <core_path>{$directory}core/</core_path>
            <context_mgr_path>{$directory}manager/</context_mgr_path>
            <context_mgr_url>{$baseUrl}manager/</context_mgr_url>
            <context_connectors_path>{$directory}connectors/</context_connectors_path>
            <context_connectors_url>{$baseUrl}connectors/</context_connectors_url>
            <context_web_path>{$directory}</context_web_path>
            <context_web_url>{$baseUrl}</context_web_url>
            <remove_setup_directory>1</remove_setup_directory>
        </modx>";

        $fh = fopen($directory . 'config.xml', "w+");
        fwrite($fh, $configXMLContents);
        fclose($fh);

        return $directory . 'config.xml';
    }

}
