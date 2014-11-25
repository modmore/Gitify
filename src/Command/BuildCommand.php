<?php
namespace modmore\Gitify\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Class BuildCommand
 *
 * Builds a MODX site from the files and configuration.
 *
 * @package modmore\Gitify\Command
 */
class BuildCommand extends Command
{
    protected function configure()
    {
        $this
        ->setName('build')
        ->setDescription('Builds a MODX site from the files and configuration.')

        ->addOption(
            'overwrite',
            null,
            InputOption::VALUE_NONE,
            'When a .gitify file already exists, and this flag is set, it will be overwritten.'
        )
        ;
    }

    /**
     * Runs the command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $modx = $this->getApplication()->loadMODX();
        $config = $this->getApplication()->loadConfig();

        
        foreach ($config['data'] as $folder => $type) {
            switch (true) {
                case (!empty($type['type']) && $type['type'] == 'content'):
                    // "content" is a shorthand for contexts + resources
                    $output->writeln("- Building content from $folder/...");
                    $this->buildContent($input, $output, $config['data_directory'] . $folder, $type);

                    break;

                case (!empty($type['class'])):
                    $output->writeln(" - Building {$type['class']} from {$folder}/...");
                    if (isset($type['package'])) {
                        $this->getPackage($type['package'], $type);
                    }
                    $this->buildObjects($config['data_directory'] . $folder, $type);

                    break;
            }
        }

        if (!$input->getOption('ncc','no-cache-clear')) {
            $output->writeln('Clearing cache...');
            $this->modx->getCacheManager()->refresh();
        }

        $output->writeln('Done!');
        exit(0);
    }

    /**
     * Loads the Content, handling uris for naming etc.
     *
     * @param $folder
     * @param $options
     */
    public function buildContent($folder, $options)
    {
        $folder = getcwd() . DIRECTORY_SEPARATOR . $folder;
        $directory = new DirectoryIterator($folder);

        if ($this->hasOption('f')) {
            $output->writeln('Forcing build, removing prior Resources...');
            $this->modx->removeCollection('modResource', array());
        }

        foreach ($directory as $path => $info) {
            /** @var SplFileInfo $info */
            $name = $info->getBasename();

            // Ignore dotfiles/folders
            if (substr($name, 0, 1) == '.') continue;

            if (!$info->isDir()) {
                //$output->writeln('Expecting directory, got ' . $info->getType() . ': ' . $name);
                continue;
            }

            $context = $this->modx->getObject('modContext', array('key' => $name));
            if (!$context) {
                $output->writeln('Context ' . $name . ' does not exist. Perhaps you\'re missing contexts data?');
                continue;
            }

            $output->writeln('Building context ' . $name . '...');

            $path = $info->getRealPath();
            $this->buildResources($name, new RecursiveDirectoryIterator($path));
        }
    }

    /**
     * Loads a package (xPDO Model) by its name
     *
     * @param $package
     * @param array $options
     */
    public function getPackage($package, array $options = array())
    {
        $path = (isset($options['package_path'])) ? $options['package_path'] : false;
        if (!$path) {
            $path = $this->modx->getOption($package . '.core_path', null, $this->modx->getOption('core_path') . 'components/' . $package . '/', true);
            $path .= 'model/';
        }

        $this->modx->addPackage($package, $path);
    }

    public function buildResources($context, $iterator)
    {
        $resources = array();
        $parents = array();
        foreach ($iterator as $fileInfo) {
            /** @var SplFileInfo $fileInfo */
            if ($fileInfo->isDir()) {
                if (substr($fileInfo->getBasename(), 0, 1) != '.') {
                    $parents[] = $fileInfo;
                }
            }
            elseif ($fileInfo->isFile()) {
                $resources[] = $fileInfo;
            }
        }

        // create the resources first
        /** @var SplFileInfo $resource */
        foreach ($resources as $resource) {
            $file = file_get_contents($resource->getRealPath());

            list($rawData, $content) = explode($this->contentSeparator, $file);

            $data = $this->fromYAML($rawData);
            if (!empty($content)) {
                $data['content'] = $content;
            }
            $data['context_key'] = $context;

            $this->buildSingleResource($data);
        }

        // Then loop over all subs
        foreach ($parents as $parentResource) {
            $this->buildResources($context, new RecursiveDirectoryIterator($parentResource.'/'));
        }
    }

    /**
     * Creates or updates a single Resource
     *
     * @param $data
     */
    public function buildSingleResource($data) {
        if (!empty($data['uri'])) {
            $primary = array('uri' => $data['uri']);
            $method = 'uri';
        }
        else {
            $primary = $data['id'];
            $method = 'id';
        }

        $new = false;
        $object = $this->modx->getObject('modResource', $primary);
        if (!($object instanceof modResource)) {
            $object = $this->modx->newObject('modResource');
            $new = true;
        }
        foreach ($object->_fieldMeta as $field => $meta) {
            if (!isset($data[$field])) $data[$field] = $meta['default'];
        }
        $object->fromArray($data, '', true, true);

        if ($object->save()) {
            if ($this->verbose) {
                $new = ($new) ? 'Created new' : 'Updated';
                $output->writeln("{$new} resource from {$method}: {$data[$method]}");
            }
        }
    }

    /**
     * Loops over an object folder and parses the files to pass to buildSingleObject
     *
     * @param $folder
     * @param $type
     */
    public function buildObjects($folder, $type)
    {
        $folder = getcwd() . DIRECTORY_SEPARATOR . $folder;
        $directory = new DirectoryIterator($folder);


        foreach ($directory as $file) {
            /** @var SplFileInfo $file */
            $name = $file->getBasename();

            // Ignore dotfiles/folders
            if (substr($name, 0, 1) == '.') continue;

            if (!$file->isFile()) {
                $output->writeln('Skipping ' . $file->getType() . ': ' . $name);
                continue;
            }

            // Load the file contents
            $fileContents = file_get_contents($file->getRealPath());

            // Get the raw data, and the content
            list($rawData, $content) = explode($this->contentSeparator, $fileContents);

            // Turn the raw YAML data into an array
            $data = $this->fromYAML($rawData);
            if (!empty($content)) {
                $data['content'] = $content;
            }

            $this->buildSingleObject($data, $type);
        }
    }

    /**
     * Creates or updates a single xPDOObject.
     *
     * @param $data
     * @param $type
     */
    public function buildSingleObject($data, $type) {
        $primaryKey = !empty($type['primary']) ? $type['primary'] : 'id';
        $class = $type['class'];

        $primary = array($primaryKey => $data[$primaryKey]);

        $new = false;
        $object = $this->modx->getObject($class, $primary);
        if (!($object instanceof xPDOObject)) {
            $object = $this->modx->newObject($class);
            $new = true;
        }

        if ($object instanceof modElement && !($object instanceof modCategory)) {
            // Handle string-based categories automagically
            if (isset($data['category']) && !empty($data['category']) && !is_numeric($data['category'])) {
                $catName = $data['category'];
                $data['category'] = $this->getCategoryId($catName);
            }
        }

        $object->fromArray($data, '', true, true);

        if ($object->save()) {
            if ($this->verbose) {
                $new = ($new) ? 'Created new' : 'Updated';
                $output->writeln("{$new} {$class}: {$data[$primaryKey]}");
            }
        }
    }

    /**
     * Grabs a category ID (or creates one!) for a category name
     *
     * @param $name
     * @return int
     */
    public function getCategoryId($name)
    {
        // Hashing it as md5 to make sure invalid characters don't mess with our array
        if (isset($this->categories[md5($name)])) {
            return $this->categories[md5($name)];
        }
        $category = $this->modx->getObject('modCategory', array('category' => $name));
        if (!$category) {
            $category = $this->modx->newObject('modCategory');
            $category->fromArray(array(
                    'category' => $name,
            ));
            if (!$category->save()) {
                return 0;
            }
        }
        if ($category) {
            $this->categories[md5($name)] = $category->get('id');
            return $this->categories[md5($name)];
        }
        return 0;
    }
}
