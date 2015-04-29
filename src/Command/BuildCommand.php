<?php
namespace modmore\Gitify\Command;

use modmore\Gitify\BaseCommand;
use modmore\Gitify\Gitify;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Class BuildCommand
 *
 * Builds a MODX site from the files and configuration.
 *
 * @package modmore\Gitify\Command
 */
class BuildCommand extends BaseCommand
{
    public $categories = array();
    public $isForce = false;
    public $existingObjects = array();
    public $conflictingObjects = array();
    public $updatedObjects;
    protected $_metaCache = array();

    protected function configure()
    {
        $this
            ->setName('build')
            ->setDescription('Builds a MODX site from the files and configuration.')

            ->addOption(
                'skip-clear-cache',
                null,
                InputOption::VALUE_NONE,
                'When specified, it will skip clearing the cache after building.'
            )

            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'When specified, all existing content will be removed before rebuilding. Can be useful when having dealt with complex conflicts.'
            )

            ->addOption(
                'no-backup',
                null,
                InputOption::VALUE_NONE,
                'When using the --force attribute, Gitify will automatically create a full database backup first. Specify --no-backup to skip creating the backup, at your own risk.'
            )

            ->addArgument(
                'partitions',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Specify the data partition key (folder name), or keys separated by a space, that you want to build. '
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
        $this->isForce = $input->getOption('force');
        if ($this->isForce && !$input->getOption('no-backup')) {
            $backup = $this->getApplication()->find('backup');
            $arguments = array(
                'command' => 'backup'
            );
            $backupInput = new ArrayInput($arguments);
            if ($backup->run($backupInput, $output) !== 0) {
                $output->writeln('<error>Could not write backup. Try building without --force, or specify the --no-backup flag to force build without writing a backup.');
                return 1;
            }
        }

        $partitions = $input->getArgument('partitions');
        if (!$partitions || empty($partitions)) {
            $partitions = array_keys($this->config['data']);
        }
        foreach ($this->config['data'] as $folder => $type) {
            if (!in_array($folder, $partitions, true)) {
                if ($output->isVerbose()) {
                    $output->writeln('Skipping ' . $folder);
                }
                continue;
            }

            switch (true) {
                case (!empty($type['type']) && $type['type'] == 'content'):
                    // "content" is a shorthand for contexts + resources
                    $output->writeln("Building content from $folder/...");
                    $this->buildContent($this->config['data_directory'] . $folder, $type);

                    break;

                case (!empty($type['class'])):
                    $doing = $this->isForce ? 'Force building' : 'Building';
                    $output->writeln("{$doing} {$type['class']} from {$folder}/...");
                    if (isset($type['package'])) {
                        $this->getPackage($type['package'], $type);
                    }
                    $this->buildObjects($this->config['data_directory'] . $folder, $type);

                    break;
            }
        }

        if (!$input->getOption('skip-clear-cache')) {
            $output->writeln('Clearing cache...');
            $this->modx->getCacheManager()->refresh();
        }

        $output->writeln('Done! ' . $this->getRunStats());
        return 0;
    }

    /**
     * Loads the Content, handling uris for naming etc.
     *
     * @param $folder
     * @param $options
     */
    public function buildContent($folder, $options)
    {
        if ($this->isForce) {
            $this->output->writeln('Forcing build, removing prior Resources...');
            $this->modx->removeCollection('modResource', array());

            if (isset($options['truncate_on_force'])) {
                foreach ($options['truncate_on_force'] as $class) {
                    $this->output->writeln('> Truncating ' . $class . ' before force building Resources...');
                    $this->modx->removeCollection($class, array());
                }
            }
        }

        $folder = getcwd() . DIRECTORY_SEPARATOR . $folder;
        $directory = new \DirectoryIterator($folder);
        foreach ($directory as $path => $info) {
            /** @var \SplFileInfo $info */
            $name = $info->getBasename();

            // Ignore dotfiles/folders
            if (substr($name, 0, 1) == '.') continue;

            if (!$info->isDir()) {
                //$output->writeln('Expecting directory, got ' . $info->getType() . ': ' . $name);
                continue;
            }

            $context = $this->modx->getObject('modContext', array('key' => $name));
            if (!$context) {
                $this->output->writeln('- Context ' . $name . ' does not exist. Perhaps you\'re missing contexts data?');
                continue;
            }

            $this->output->writeln('- Building ' . $name . ' context...');

            $path = $info->getRealPath();
            $this->buildResources($name, new \RecursiveDirectoryIterator($path));
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

    /**
     * Loops over resource files to create the resources.
     *
     * @param $context
     * @param $iterator
     */
    public function buildResources($context, $iterator)
    {
        $resources = array();
        $parents = array();
        foreach ($iterator as $fileInfo) {
            if (substr($fileInfo->getBasename(), 0, 1) == '.') {
                continue;
            }
            /** @var \SplFileInfo $fileInfo */
            if ($fileInfo->isDir()) {
                $parents[] = $fileInfo;
            }
            elseif ($fileInfo->isFile()) {
                $resources[] = $fileInfo;
            }
        }

        // create the resources first
        /** @var \SplFileInfo $resource */
        foreach ($resources as $resource) {
            $file = file_get_contents($resource->getRealPath());

            list($rawData, $content) = explode(Gitify::$contentSeparator, $file);

            try {
                $data = Gitify::fromYAML($rawData);
            } catch (ParseException $Exception) {
                $this->output->writeln('<error>Could not parse ' . $resource->getBasename() . ': ' . $Exception->getMessage() .'</error>');
                continue;
            }
            if (!empty($content)) {
                $data['content'] = $content;
            }
            $data['context_key'] = $context;

            $this->buildSingleResource($data);
        }

        // Then loop over all subs
        foreach ($parents as $parentResource) {
            $this->buildResources($context, new \RecursiveDirectoryIterator($parentResource.'/'));
        }
    }

    /**
     * Creates or updates a single Resource
     *
     * @param $data
     */
    public function buildSingleResource($data) {
        // Figure out the primary key - it's either uri or id in the case of a resource.
        if (!empty($data['uri'])) {
            $primary = array('uri' => $data['uri'], 'context_key' => $data['context_key']);
            $method = 'uri';
        }
        else {
            $primary = $data['id'];
            $method = 'id';
        }

        // Grab the resource, or create a new one.
        $new = false;
        $object = ($this->isForce) ? false : $this->modx->getObject('modResource', $primary);
        if (!($object instanceof \modResource)) {
            $object = $this->modx->newObject('modResource');
            $new = true;
        }

        // Ensure all fields have a value
        foreach ($object->_fieldMeta as $field => $meta) {
            if (!isset($data[$field])) $data[$field] = $meta['default'];
        }

        // Set the fields on the resource
        $object->fromArray($data, '', true, true);

        // Process stored TVs
        if (isset($data['tvs'])) {
            foreach($data['tvs'] as $key => $value) {
                $object->setTVValue($key, $value);
            }
        }

        // Save it!
        if ($object->save()) {
            if ($this->output->isVerbose()) {
                $new = ($new) ? 'Created new' : 'Updated';
                $this->output->writeln("- {$new} resource from {$method}: {$data[$method]}");
            }
        }
        else {
            $new = ($new) ? 'new' : 'updated';
            $this->output->writeln("- <error>Could not save {$new} resource from {$method}: {$data[$method]}</error>");
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

        if (!file_exists(GITIFY_WORKING_DIR . $folder)) {
            $this->output->writeln('> Skipping ' . $type['class'] . ', ' . $folder. ' does not exist.');
            return;
        }

        if ($this->isForce) {
            $this->modx->removeCollection($type['class'], array());

            if (isset($type['truncate_on_force'])) {
                foreach ($type['truncate_on_force'] as $class) {
                    $this->output->writeln('> Truncating ' . $class . ' before force building ' . $type['class'] . '...');
                    $this->modx->removeCollection($class, array());
                }
            }

            /**
             * @deprecated 2015-03-30
             *
             * Deprecated in favour of specifying truncate_on_force in the .gitify file.
             */
            switch ($type['class']) {
                // $modx->removeCollection does not automatically remove related objects, which in the case
                // of modCategory results in orphaned modCategoryClosure objects. Normally, this is okay, because
                // Gitify recreates the objects with the same ID. But Categories automatically add the closure on
                // save, which then throws a bunch of errors about duplicate IDs. Worst of all, it _removes_ the
                // category object if it can't save the closure, causing the IDs to go all over the place.
                // So in this case, we make sure all category closures are wiped too.
                case 'modCategory':
                    $this->modx->removeCollection('modCategoryClosure', array());
                    break;
            }
        }

        $directory = new \DirectoryIterator(GITIFY_WORKING_DIR . $folder);

        $this->existingObjects = ($this->isForce) ? array() : $this->getExistingObjects($type['class']);
        $this->updatedObjects = array();
        $this->conflictingObjects = array();

        foreach ($directory as $file) {
            /** @var \SplFileInfo $file */
            $name = $file->getBasename();

            // Ignore dotfiles/folders
            if (substr($name, 0, 1) == '.') continue;

            if (!$file->isFile()) {
                $this->output->writeln('- Skipping ' . $file->getType() . ': ' . $name);
                continue;
            }

            // Load the file contents
            $fileContents = file_get_contents($file->getRealPath());

            // Get the raw data, and the content
            list($rawData, $content) = explode(Gitify::$contentSeparator, $fileContents);

            // Turn the raw YAML data into an array
            $data = Gitify::fromYAML($rawData);
            if (!empty($content)) {
                $data['content'] = $content;
            }

            $this->buildSingleObject($data, $type);
        }

        if (!empty($this->conflictingObjects)) {
            $runExtract = false;
            foreach ($this->conflictingObjects as $conflict) {
                $this->output->writeln('- Resolving ID Conflict for <comment>' . $this->modx->toJSON($conflict['existing_object']) . '</comment> with <comment>' . count($conflict['conflicts']) . '</comment> duplicate(s).');

                $original = $this->modx->getObject($type['class'], $conflict['existing_object']);
                if ($original instanceof \xPDOObject) {
                    $primary = $type['primary'];
                    $actualPrimary = $original->getPK();

                    // First resolution attempt
                    // If the primary in the gitify file isn't the same as the actual primary key(s),
                    // we can resolve the conflict by unsetting the actual primary key
                    if ($primary !== $actualPrimary) {
                        $originalActualPrimary = $original->get($actualPrimary);

                        foreach ($conflict['conflicts'] as $dupe) {
                            $resolved = false;
                            $dupeData = $dupe['data'];
                            if (is_array($actualPrimary)) {
                                $dupePrimary = array();
                                $fieldMeta = $this->modx->getFieldMeta($type['class']);
                                foreach ($actualPrimary as $key) {
                                    $default = isset($fieldMeta[$key]['default']) ? $fieldMeta[$key]['default'] : null;
                                    $dupePrimary[$key] = (isset($dupeData[$key])) ? $dupeData[$key] : $default;
                                }
                            }
                            else {
                                $dupePrimary = $dupeData[$actualPrimary];
                            }
                            if (!empty($dupePrimary) && $dupePrimary == $originalActualPrimary) {
                                if (is_array($actualPrimary)) {
                                    foreach ($actualPrimary as $key) {
                                        unset($dupeData[$key]);
                                    }
                                }
                                else {
                                    unset($dupeData[$actualPrimary]);
                                }

                                $this->output->writeln("  \ - <comment>Duplicate #{$dupe['idx']}</comment>: resolving <comment>primary key conflict</comment> by building object with new auto incremented primary key ");
                                $this->buildSingleObject($dupeData, $type, false);
                                $resolved = $runExtract = true;
                            }

                            if (!$resolved) {
                                $this->output->writeln("  \ - <error>Unable to resolve ID conflict; situation does not match any expected conflict scenario.</error> The ID conflict will need to be solved manually. ");
                            }
                        }
                    }
                }
            }

            if ($runExtract) {
                $this->output->writeln('- Running extract for ' . basename($folder) . '; you will need to commit the changes manually.');
                $command = $this->getApplication()->find('extract');
                $inputArray = array(
                    'command' => 'extract',
                    'partitions' => array(basename($folder)),
                );
                $input = new ArrayInput($inputArray);
                $output = new BufferedOutput();
                $command->run($input, $output);

                $cmdOutput = $output->fetch();
                $cmdOutput = explode("\n", $cmdOutput);
                $cmdOutput = array_map(function($n) { return '  \ ' . $n; }, $cmdOutput);
                $cmdOutput = implode("\n", $cmdOutput);

                $this->output->write($cmdOutput);
            }
        }
    }

    /**
     * Creates or updates a single xPDOObject.
     *
     * @param $data
     * @param $type
     * @param bool $checkDuplicates
     */
    public function buildSingleObject($data, $type, $checkDuplicates = true) {
        $primaryKey = !empty($type['primary']) ? $type['primary'] : 'id';
        $class = $type['class'];

        if (is_array($primaryKey)) {
            $primary = array();
            foreach ($primaryKey as $pkVal) {
                $primary[$pkVal] = isset($data[$pkVal]) ? $data[$pkVal] : $this->_getDefaultForField($class, $pkVal);
            }
            $showPrimary = json_encode($primary);
        }
        else {
            $primary = array($primaryKey => $data[$primaryKey]);
            $showPrimary = $data[$primaryKey];
        }

        if ($checkDuplicates) {
            // Get the primary to match for ID conflict resolution
            $classPrimary = $this->modx->getPK($class);
            if (is_array($classPrimary)) {
                $internalPrimary = array();
                foreach ($classPrimary as $classPrimaryField) {
                    $fieldMeta = $this->modx->getFieldMeta($class);
                    $default = isset($fieldMeta[$classPrimaryField]['default']) ? $fieldMeta[$classPrimaryField]['default'] : null;
                    $internalPrimary[$classPrimaryField] = (isset($data[$classPrimaryField])) ? $data[$classPrimaryField] : $default;
                }
                $internalPrimary = implode('--', $internalPrimary);
            } else {
                $internalPrimary = $data[$classPrimary];
            }

            if (isset($this->updatedObjects[$internalPrimary])) {
                if (!isset($this->conflictingObjects[$internalPrimary])) {
                    $this->conflictingObjects[$internalPrimary] = array(
                        'existing_object' => $this->updatedObjects[$internalPrimary],
                        'conflicts' => array(),
                    );
                }
                $this->conflictingObjects[$internalPrimary]['conflicts'][] = array(
                    'idx' => count($this->conflictingObjects[$internalPrimary]['conflicts']) + 1,
                    'data' => $data,
                );

                $this->output->writeln("<comment>- Potential ID Conflict found affecting {$class} {$showPrimary}</comment>; will attempt to resolve after completing build process.");

                return;
            }
            $this->updatedObjects[$internalPrimary] = $primary;
        }

        $new = false;
        /** @var \xPDOObject|bool $object */
        $object = ($this->isForce) ? false : $this->modx->getObject($class, $primary);
        if (!($object instanceof \xPDOObject)) {
            $object = $this->modx->newObject($class);
            $new = true;
        }

        if ($object instanceof \modElement && !($object instanceof \modCategory)) {
            // Handle string-based category names automagically
            if (isset($data['category']) && !empty($data['category']) && !is_numeric($data['category'])) {
                $catName = $data['category'];
                $data['category'] = $this->getCategoryId($catName);
            }
        }

        $object->fromArray($data, '', true, true);

        if ($object->save()) {
            if ($this->output->isVerbose()) {
                $new = ($new) ? 'Created new' : 'Updated';
                $this->output->writeln("- {$new} {$class}: {$showPrimary}");
            }
        }
        else {
            $new = ($new) ? 'new' : 'updated';
            $this->output->writeln("- <error>Could not save {$new} {$class}: {$showPrimary}</error>");
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

    /**
     * Looks at the field meta to find the default value.
     *
     * @param string $class The xPDOObject to grab adefault value for
     * @param string $field The field in the xPDOObject to grab a default value for
     * @return null
     */
    protected function _getDefaultForField($class, $field)
    {
        if (!isset($this->_metaCache[$class])) {
            $this->_metaCache[$class] = $this->modx->getFieldMeta($class);
        }

        if (isset($this->_metaCache[$class][$field]) && isset($this->_metaCache[$class][$field]['default'])) {
            return $this->_metaCache[$class][$field]['default'];
        }
        return null;
    }

    /**
     * Returns an array of all current objects in the database, per key => array
     *
     * @param $class
     * @return array
     */
    public function getExistingObjects($class)
    {
        $objects = array();

        $iterator = $this->modx->getIterator($class);
        foreach ($iterator as $object) {
            /** @var \xPDOObject $object */
            $key = $object->getPrimaryKey();
            if (is_array($key)) {
                $key = implode('--', $key);
            }

            $objects[$key] = $object->toArray();
        }

        return $objects;
    }
}
