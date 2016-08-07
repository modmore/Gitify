<?php

namespace modmore\Gitify\Command;

use modmore\Gitify\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClearCacheCommand extends BaseCommand
{
    public $loadConfig = false;
    public $loadMODX = false;

    protected function configure()
    {
        $this
            ->setName('clearcache')
            ->setDescription('Clears the internal Gitify cache.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (file_exists(GITIFY_CACHE_DIR)) {
            exec("rm -rf " . GITIFY_CACHE_DIR);
            $output->writeln('Cleared the Gitify cache.');
        }
    }
}
