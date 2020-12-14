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
 * Class DownloadModxCommand
 *
 * Download a clean version of MODX.
 *
 * @package modmore\Gitify\Command
 */
class DownloadModxCommand extends BaseCommand
{
    use DownloadModx;

    public $loadConfig = false;
    public $loadMODX = false;

    protected function configure()
    {
        $this
            ->setName('modx:download')
            ->setDescription('Downloads a fresh MODX installation.')
            ->addArgument(
                'version',
                InputArgument::OPTIONAL,
                'The version of MODX to download, in the format 2.3.2-pl. Leave empty or specify "latest" to download the last stable release.',
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

        $output->writeln('Done! ' . $this->getRunStats());
        return 0;
    }

}
