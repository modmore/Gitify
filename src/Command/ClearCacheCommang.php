<?php

namespace modmore\Gitify\Command;

use modmore\Gitify\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClearCacheCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('clearcache')
            ->setDescription('Clears gitify\'s internal package cache.')
            ->setAliases(['clear-cache']);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

    }
}
