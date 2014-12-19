<?php
namespace modmore\Gitify;

use modX;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Command
 *
 * @package modmore\Gitify\Command
 */
abstract class BaseCommand extends Command
{
    /** @var modX $modx */
    public $modx;
    /** @var array $config Contains the contents of the .gitify file */
    public $config = array();
    /** \Symfony\Component\Console\Input\InputInterface $input */
    public $input;
    /** \Symfony\Component\Console\Output\OutputInterface $output */
    public $output;

    public $loadConfig = true;
    public $loadMODX = true;

    /**
     * Initializes the command just after the input has been validated.
     *
     * This is mainly useful when a lot of commands extends one main command
     * where some things need to be initialized based on the input arguments and options.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     */
    public function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        if ($this->loadConfig)
        {
            $this->config = Gitify::loadConfig();
        }
        if ($this->loadMODX)
        {
            $this->modx = Gitify::loadMODX();
        }
    }
}
