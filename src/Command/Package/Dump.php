<?php

namespace modmore\Gitify\Command\Package;

use modmore\Gitify\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Dump
 *
 * Gets list of installed packages with their repositories and stores it to gitify config.
 *
 * @package modmore\Gitify\Command\Package
 */
class Dump extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('package:dump')
            ->setDescription('This command gets all installed packages and save list of them, repositiories and keys to gitify configuration file.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
    }
}
