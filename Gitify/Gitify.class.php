<?php

require_once dirname(__FILE__) . '/vendor/kbjr/git.php/Git.php';
require_once dirname(__FILE__) . '/vendor/GerHobbelt/nicejson-php/nicejson.php';

/**
 * Class Gitify
 */
class Gitify
{
    public $git;
    /** @var modX|null */
    public $modx;

    public $sep = "\n-----\n";

    public function __construct()
    {
        $this->git = new Git();
    }

    public function toJSON(array $data = array())
    {
        return json_format($data);
    }

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
