<?php

/**
 * Class GitifyInit
 */
class GitifyInit extends Gitify
{
    /**
     * Runs the GitifyInit command
     *
     * @param array $options
     */
    public function run(array $options = array())
    {
        if (file_exists($options['cwd'] . '.gitify')) {
            $this->echoInfo("* Error: there already is a Gitify project in {$options['cwd']}");
            exit(1);
        }
        $cwd = $options['cwd'];

        $name = $this->askQuestion("Please enter a Project Name: ");
        $name = trim($name);
        $directory = $this->askQuestion("Please enter the relative Data Directory: ");
        $directory = rtrim(trim($directory), '/') . '/';

        $target_path = $options['cwd'] . $directory;
        if (!is_dir($target_path)) {
            mkdir($target_path);
            $this->echoInfo("Creating new data directory $target_path");
        }

        $options = $this->getDefaultOptions($name, $directory);
        $yaml = $this->toYAML($options);

        file_put_contents($cwd . '.gitify', $yaml);

        $this->echoInfo("Created new Gitify project in $cwd");

        if (!file_exists($cwd . 'config.core.php')) {
            $install = $this->askQuestion('No MODX installation found. Would you like to install the latest stable MODX version? (Y/N) ');
            $install = strtoupper(trim($install));
            if ($install == 'Y') {
                $this->installMODX($cwd);
            }
        }

        exit(0);
    }

    /**
     * Defines the default .gitify file
     *
     * @param $name
     * @param $directory
     * @return array
     */
    private function getDefaultOptions($name, $directory)
    {
        $options = array(
            'name' => $name,
            'data_directory' => $directory,
            'data' => array(),
        );

        if (strtoupper($this->askQuestion('Include Contexts? (Y/N) [Y]', 'Y', true)) == 'Y')
        {
            $options['data']['contexts'] = array(
                'class' => 'modContext',
                'primary' => 'key'
            );
        }
        if (strtoupper($this->askQuestion('Include Content? (Y/N) [Y]', 'Y', true)) == 'Y')
        {
            $options['data']['content'] = array(
                'type' => 'content',
                'exclude_keys' => array('createdby', 'createdon', 'editedby', 'editedon')
            );
        }
        if (strtoupper($this->askQuestion('Include Elements? (Y/N) [Y]', 'Y', true)) == 'Y')
        {
            $options['data'] = array_merge($options['data'], array(
                'categories' => array(
                    'class' => 'modCategory',
                    'primary' => 'category'
                ),
                'templates' => array(
                    'class' => 'modTemplate',
                    'primary' => 'templatename',
                    'extension' => '.html',
                ),
                'template_variables' => array(
                    'class' => 'modTemplateVar',
                    'primary' => 'name'
                ),
                'chunks' => array(
                    'class' => 'modChunk',
                    'primary' => 'name',
                    'extension' => '.html',
                ),
                'snippets' => array(
                    'class' => 'modSnippet',
                    'primary' => 'name',
                    'extension' => '.php'
                ),
                'plugins' => array(
                    'class' => 'modPlugin',
                    'primary' => 'name',
                    'extension' => '.php'
                )
            ));
        }

        return $options;
    }

    /**
     * Installs MODX
     *
     * @param $cwd
     */
    public function installMODX($cwd)
    {
        require_once dirname(__FILE__) . '/GitifyInstallMODX.class.php';
        $runner = new GitifyInstallMODX();
        $runner->run(array(
            'cwd' => $cwd,
        ));
    }


}
