<?php

namespace modmore\Gitify;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;

/**
 * Class Gitify
 */
class Gitify extends Application
{
    protected $envopts = array();
    /**
     * Used to separate between meta data and content in object files.
     *
     * @var string
     */
    public static $contentSeparator = "\n-----\n\n";
    /**
     * @var \modX
     */
    public static $modx;

    /**
     * Takes in an array of data, and turns it into blissful YAML using Symfony's YAML component.
     *
     * @param array $data
     * @return string
     */
    public static function toYAML(array $data = array())
    {
        return Yaml::dump($data, 4);
    }

    /**
     * Takes YAML, and turns it into an array using Symfony's YAML component.
     *
     * @param string $input
     * @return array
     */
    public static function fromYAML($input = '')
    {
        return Yaml::parse($input);
    }

    /**
     * Loads a new modX instance
     *
     * @throws \RuntimeException
     * @return \modX
     */
    public static function loadMODX()
    {
        if (self::$modx) {
            return self::$modx;
        }

        if (!file_exists(GITIFY_WORKING_DIR . 'config.core.php')) {
            throw new \RuntimeException('There does not seem to be a MODX installation here. ');
        }

        require_once(GITIFY_WORKING_DIR . 'config.core.php');
        require_once(MODX_CORE_PATH . 'model/modx/modx.class.php');

        $modx = new \modX();
        $modx->initialize('mgr');
        $modx->getService('error', 'error.modError', '', '');
        $modx->setLogTarget('ECHO');

        self::$modx = $modx;

        return $modx;
    }

    /**
     * @throws \RuntimeException
     */
    public static function loadConfig()
    {
        if (!file_exists(GITIFY_WORKING_DIR . '.gitify')) {
            throw new \RuntimeException("Directory is not a Gitify directory: " . GITIFY_WORKING_DIR);
        }

        $config = Gitify::fromYAML(file_get_contents(GITIFY_WORKING_DIR . '.gitify'));
        if (!$config || !is_array($config)) {
            throw new \RuntimeException("Error: " . GITIFY_WORKING_DIR . ".gitify file is not valid YAML, or is empty.");
        }

        return $config;
    }

    /**
     * Gets the default input definition.
     *
     * @return InputDefinition An InputDefinition instance
     */
    protected function getDefaultInputDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('command', InputArgument::REQUIRED, 'The command to execute'),

            new InputOption('--help',           '-h', InputOption::VALUE_NONE, 'Display this help message.'),
            new InputOption('--verbose',        '-v|vv|vvv', InputOption::VALUE_NONE, 'Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug.'),
            new InputOption('--version',        '-V', InputOption::VALUE_NONE, 'Display the Gitify version.'),
        ));
    }


    /**
     * Run a shell command using proc_open to get around limitations with exec()
     *
     * "Inspired" by https://github.com/kbjr/Git.php/blob/master/Git.php#L283
     *
     * @param string $command The command to execute
     * @return string
     * @throws \Exception
     */
    public function exec($command) {
        $descriptorspec = array(
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );
        $pipes = array();
        /* Depending on the value of variables_order, $_ENV may be empty.
         * In that case, we have to explicitly set the new variables with
         * putenv, and call proc_open with env=null to inherit the reset
         * of the system.
         *
         * This is kind of crappy because we cannot easily restore just those
         * variables afterwards.
         *
         * If $_ENV is not empty, then we can just copy it and be done with it.
         */
        if(count($_ENV) === 0) {
            $env = NULL;
            foreach($this->envopts as $k => $v) {
                putenv(sprintf("%s=%s",$k,$v));
            }
        } else {
            $env = array_merge($_ENV, $this->envopts);
        }
        $cwd = GITIFY_WORKING_DIR;
        $resource = proc_open($command, $descriptorspec, $pipes, $cwd, $env);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $status = trim(proc_close($resource));
        if ($status) {
            throw new \Exception($stderr);
        }

        return $stdout;
    }

    /**
     * Sets custom environment options
     *
     * @param string $key
     * @param string $value
     */
    public function setEnvOpt($key, $value) {
        $this->envopts[$key] = $value;
    }
}
