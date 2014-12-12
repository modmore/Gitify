<?php

require_once dirname(__FILE__) . '/vendor/kbjr/git.php/Git.php';
require_once dirname(__FILE__) . '/vendor/mustangostang/spyc/Spyc.php';

/**
 * Class Gitify
 */
class Gitify
{
    public $git;
    /** @var modX|null */
    public $modx;
    /** @var Spyc|null */
    public $spyc;
    /** @var array */
    public $argv;

    public $sep = "\n-----\n";

    /**
     * Creates a new Gitify instance, creating local Git and Spyc (YAML) instances.
     */
    public function __construct()
    {
        $this->git = new Git();
        $this->spyc = new Spyc();

        global $argv;
        $this->argv = $argv;
    }
    
     /**
     * Check for config
     *
     */
    public static function getConfig($argv)
    {
        $config = '.gitify';
        if (in_array('-c', $argv)) {
            $c = array_search('-c',$argv);
            if( !$argv[($c+1)] ) {
                echo "Config not specified, using default (.gitify)\n";
            } else {
                $config = $argv[($c+1)];
            }
        };
        return $config;
    }

    /**
     * Takes in an array of data, and turns it into blissful YAML using Spyc.
     *
     * @param array $data
     * @return string
     */
    public function toYAML(array $data = array())
    {
        return $this->spyc->dump($data, false, false, true);
    }

    /**
     * Takes YAML, and turns it into an array using Spyc.
     *
     * @param string $input
     * @return array
     */
    public function fromYAML($input = '')
    {
        return $this->spyc->load($input);
    }

    /**
     * Loads a new modX instance
     *
     * @param $root
     * @return bool
     */
    public function loadMODX($root)
    {
        if ($this->modx && $this->modx->config['base_path'] == $root) {
            return true;
        }

        if (!file_exists($root . 'config.core.php')) {
            return false;
        }

        require_once($root . 'config.core.php');
        require_once(MODX_CORE_PATH . 'model/modx/modx.class.php');

        $this->modx = new modX();
        $this->modx->initialize('mgr');
        $this->modx->getService('error', 'error.modError', '', '');

        return true;
    }

    public function echoInfo ($msg, $lineEnd = true)
    {
        echo '|' . date('H:i:s') . '| ' . $msg;
        if ($lineEnd) echo "\n";
    }

    /**
     * Asks a question on the php command line
     *
     * @param $question
     * @param string $default
     * @param bool $formStyle
     * @return string
     */
    public function askQuestion($question, $default = '', $formStyle = false) {
        if ($formStyle) {
            $question = str_pad($question, 40, ' ', STR_PAD_RIGHT);
        }
        $this->echoInfo('> ' . $question, false);
        $handle = fopen("php://stdin", "r");
        $return = fgets($handle);
        $return = trim($return);

        if (empty($return)) return $default;
        return $return;
    }

    /**
     * Checks for a command line flag/option
     *
     * @param $shortFlag
     * @param null $longFlag
     * @return bool
     */
    public function hasOption($shortFlag, $longFlag = null) {
        if (in_array('-' . $shortFlag, $this->argv)) return true;
        if (!empty($longFlag) && in_array('--' . $longFlag, $this->argv)) return true;
        return false;
    }
}
