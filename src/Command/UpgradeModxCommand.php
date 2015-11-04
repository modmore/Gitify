<?php

namespace modmore\Gitify\Command;

use modmore\Gitify\BaseCommand;
use modmore\Gitify\Mixins\DownloadModx;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class UpgradeModxCommand
 * @package modmore\Gitify\Command
 */
class UpgradeModxCommand extends BaseCommand
{
    use DownloadModx;

    public $loadConfig = false;
    public $loadModx = true;

    protected function configure()
    {
        $this
            ->setName('modx:upgrade')
            ->setDescription('Downloads, configures and updates the current MODX installation.')
            ->addArgument(
                'version',
                InputArgument::OPTIONAL,
                'The version of MODX to upgrade, in the format 2.3.2-pl. Leave empty or specify "latest" to install the last stable release.',
                'latest'
            )
            ->addOption(
                'download',
                'd',
                InputOption::VALUE_NONE,
                'Force download the MODX package even if it already exists in the cache folder.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $version = $this->input->getArgument('version');
        $forced = $this->input->getOption('download');

        if (!$this->getMODX($version, $forced)) {
            return 1; // exit
        }

        // Create the XML config
        $config = $this->createMODXConfig();

        // Variables for running the setup
        $tz = date_default_timezone_get();
        $wd = GITIFY_WORKING_DIR;
        $output->writeln("Running MODX Upgrade...");

        // Actually run the CLI setup
        exec("php -d date.timezone={$tz} {$wd}setup/index.php --installmode=upgrade --config={$config}", $setupOutput);
        $output->writeln($setupOutput[0]);

        // Try to clean up the config file
        if (!unlink($config)) {
            $output->writeln("<warning>Warning:: could not clean up the setup config file, please remove this manually.</warning>");
        }

        $output->writeln('Done! ' . $this->getRunStats());

        return 0;
    }

    protected function createMODXConfig()
    {
        $directory = GITIFY_WORKING_DIR;

        $config = array(
            'inplace' => 1,
            'unpacked' => 0,
            'language' => $this->modx->getOption('manager_language'),
            'core_path' => $this->modx->getOption('core_path'),
            'remove_setup_directory' => true
        );

        $xml = new \DOMDocument('1.0', 'utf-8');
        $modx = $xml->createElement('modx');

        foreach ($config as $key => $value) {
            $modx->appendChild($xml->createElement($key, $value));
        }

        $xml->appendChild($modx);

        $fh = fopen($directory . 'config.xml', "w+");
        fwrite($fh, $xml->saveXML());
        fclose($fh);

        return $directory . 'config.xml';
    }
}
