<?php
$fn = 'Gitify.php';

// This file would be something you could run from the command line
// For example:
//
// $ gitify build
//
// which would then build a new MODX site based on what's contained in the gitify repo.
if ($argc < 2) {
    echo "Usage: php $fn [command] [options]
    Commands:
        init: starts a new Gitify project
        build [environment]: builds the site into MODX
        load [environment]: loads the entire site into the repository
";
    exit(1);
}

require_once dirname(__FILE__) . '/Gitify/Gitify.class.php';
$gitify = new Gitify();

$command = $argv[1];

switch ($command) {
    // starts a new Gitify project
    case 'init':
        echo "Please enter a name for the Project: ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        $name = trim($line);

        $cwd = getcwd();
        $path = (isset($argv[2]) && !empty($argv[2])) ?  DIRECTORY_SEPARATOR . $argv[2] : '';

        $project_path = $cwd . $path;
        if (!is_dir($project_path)) {
            echo "Creating new directory $project_path\n";
            mkdir($project_path);
        }
        if (file_exists($project_path . '.gitify')) {
            echo "Error: there already is a Gitify project in $project_path";
            exit(1);
        }

        $project = array(
            'name' => $name,

            'data' => array(
                'content' => array(
                    'type' => 'content',
                    'exclude_keys' => array('createdby', 'createdon', 'editedby', 'editedon')
                ),
                'categories' => array(
                    'class' => 'modCategory',
                    'primary' => 'category'
                ),
                'templates' => array(
                    'class' => 'modTemplate',
                    'primary' => 'name'
                ),
                'template_variables' => array(
                    'class' => 'modTemplateVar',
                    'primary' => 'name'
                ),
                'chunks' => array(
                    'class' => 'modChunk',
                    'primary' => 'name'
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
                ),
            )
        );

        $yaml = $gitify->toYAML($project);

        file_put_contents($project_path . '/.gitify', $yaml);
        echo "Created new Gitify project in $project_path\n";

        /*
         * @todo allow installing a new MODX from the command line
         * @see https://github.com/bertoost/MODX-Installers/blob/master/install-core.php
        if (!file_exists($project_path . '/config.core.php')) {
            echo "Could not find a config.core.php file. Do you wish to install MODX? If so, enter the version number like 2.2.14-pl. If not, leave empty.: ";
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            $version = trim($line);

            if (!empty($version)) {

            }
        }
        */

        exit(0);

        break;

    // build the site in MODX
    case 'build':

        break;

    // Load the site into Git
    case 'load':
        $cwd = getcwd();
        $path = (isset($argv[2]) && !empty($argv[2])) ?  DIRECTORY_SEPARATOR . $argv[2] : '';
        $project_path = $cwd . $path . DIRECTORY_SEPARATOR;

        if (!file_exists($project_path . '.gitify')) {
            echo "Directory is not a Gitify directory: $project_path\n";
            exit(1);
        }

        $project = $gitify->fromYAML(file_get_contents($project_path . '.gitify'), true);
        if (!$project || !is_array($project)) {
            echo "Error: $project_path.gitify file is not valid JSON, or is empty.\n";
            exit (1);
        }
        $project['path'] = $project_path;


        require_once dirname(__FILE__) . '/Gitify/GitifyLoad.class.php';
        $runner = new GitifyLoad();
        $runner->run($project);


        break;
}
