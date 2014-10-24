<?php

namespace modmore\Gitify;

use Symfony\Component\Console\Application;
use Symfony\Component\Yaml\Yaml;

/**
 * Class Gitify
 */
class Gitify extends Application
{
    /**
     * Used to separate between meta data and content in object files.
     *
     * @var string
     */
    public $contentSeparator = "\n-----\n";
    /**
     * @var modX
     */
    public $modx;
    /**
     * @var array
     */
    public $config;

    /**
     * Takes in an array of data, and turns it into blissful YAML using Spyc.
     *
     * @param array $data
     * @return string
     */
    public function toYAML(array $data = array())
    {
        return Yaml::dump($data, 4);
    }

    /**
     * Takes YAML, and turns it into an array using Spyc.
     *
     * @param string $input
     * @return array
     */
    public function fromYAML($input = '')
    {
        return Yaml::parse($input);
    }

    /**
     * Loads a new modX instance
     *
     * @return bool
     */
    public function loadMODX()
    {
        if ($this->modx) {
            return true;
        }

        if (!file_exists(GITIFY_WORKING_DIR . 'config.core.php')) {
            return false;
        }

        require_once(GITIFY_WORKING_DIR . 'config.core.php');
        require_once(MODX_CORE_PATH . 'model/modx/modx.class.php');

        $this->modx = new modX();
        $this->modx->initialize('mgr');
        $this->modx->getService('error', 'error.modError', '', '');

        return true;
    }

    /**
     * @return bool|array
     */
    public function loadConfig()
    {
        if (!file_exists(GITIFY_WORKING_DIR . '.gitify')) {
            echo "Directory is not a Gitify directory: " . GITIFY_WORKING_DIR ."\n";
            return false;
        }

        $config = $this->fromYAML(file_get_contents(GITIFY_WORKING_DIR . '.gitify'));
        if (!$config || !is_array($config)) {
            echo "Error: " . GITIFY_WORKING_DIR . ".gitify file is not valid YAML, or is empty.\n";
            return false;
        }

        $this->config = $config;
        return $this->config;
    }
}
