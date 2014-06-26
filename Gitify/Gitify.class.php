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

    public $sep = "\n-----\n";

    /**
     * Creates a new Gitify instance, creating local Git and Spyc (YAML) instances.
     */
    public function __construct()
    {
        $this->git = new Git();
        $this->spyc = new Spyc();
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
}
