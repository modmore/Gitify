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

    public $startTime;

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
        $this->startTime = microtime(true);
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

    /**
     * @return string
     */
    public function getRunStats()
    {
        $curTime = microtime(true);
        $duration = $curTime - $this->startTime;

        $output = 'Time: ' . number_format($duration * 1000, 0) . 'ms | ';
        $output .= 'Memory Usage: ' . $this->convertBytes(memory_get_usage(false)) . ' | ';
        $output .= 'Peak Memory Usage: ' . $this->convertBytes(memory_get_peak_usage(false));
        return $output;
    }

    /**
     * @param $bytes
     * @return string
     */
    public function convertBytes($bytes)
    {
        $unit = array('b','kb','mb','gb','tb','pb');
        return @round($bytes/pow(1024,($i=floor(log($bytes,1024)))),2).' '.$unit[$i];
    }

    /**
     * @param $path
     * @return string
     */
    public function normalizePath($path)
    {
        $normalized_path = str_replace('\\', Gitify::$directorySeparator, $path);

        return $normalized_path;
    }

    /**
     * @param string $partition
     * @return array|null
     */
    public function getPartitionCriteria($partition)
    {
        if (!isset($this->config['data']) || !isset($this->config['data'][$partition])) {
            return null;
        }
        $options = $this->config['data'][$partition];

        if (isset($options['where']) && !empty($options['where'])) {
            return $options['where'];
        }

        return null;
    }
}
