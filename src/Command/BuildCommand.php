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
    public $orphanedObjects;
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

            ->addOption(
                'no-cleanup',
                null,
                InputOption::VALUE_NONE,
                'With --no-cleanup specified the built-in orphan handling is disabled for this build. The orphan handling removes objects that no longer exist in files from the database. '
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

            $type['folder'] = $folder;

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
     * @param $type
     */
    public function buildContent($folder, $type)
    {
        if ($this->isForce) {
            $this->output->writeln('Forcing build, removing prior Resources...');
            $forceCriteria = $this->getPartitionCriteria($type['folder']);
            if (is_null($forceCriteria)) {
                $forceCriteria = array();
            }
            $this->modx->removeCollection('modResource', $forceCriteria);

            if (isset($type['truncate_on_force'])) {
                foreach ($type['truncate_on_force'] as $class) {
                    $this->output->writeln('> Truncating ' . $class . ' before force building Resources...');
                    $this->modx->removeCollection($class, array());
                }
            }
        }

        // Conflict handling
        $this->resetConflicts();
        $this->getExistingObjects('modResource', $this->getPartitionCriteria($type['folder']));

        $folder = GITIFY_WORKING_DIR . $folder;

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

        $type['class'] = 'modResource';
        $this->removeOrphans($type, 'uri');
        $this->resolveConflicts($folder, $type, true);
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

        if ($options['service']) {
            $path .= $package . '/';
            $this->modx->getService($package, $options['service'], $path);
	} else {
	    $this->modx->addPackage($package, $path);
	}
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

            // Normalize line-endings to \n to ensure consistency
            $file = str_replace("\r\n", "\n", $file);
            $file = str_replace("\r", "\n", $file);
            // Check if delimiter exists, otherwise add it to avoid WARN in explode()
            // (WARN @ Gitify/src/Command/BuildCommand.php : 246) PHP notice: Undefined offset: 1
            if (strpos($file, Gitify::$contentSeparator) === false) {
                $file = $file . Gitify::$contentSeparator;
            }
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
     * @param bool $isConflictResolution
     */
    public function buildSingleResource($data, $isConflictResolution = false) {
	$this->modx->setOption(\xPDO::OPT_SETUP, true);
        // Figure out the primary key - it's either uri or id in the case of a resource.
        if (!empty($data['uri'])) {
            $primary = array('uri' => $data['uri'], 'context_key' => $data['context_key']);
            $primaryKey = array('uri', 'context_key');
            $method = 'uri';
        }
        else {
            $primary = $data['id'];
            $primaryKey = 'id';
            $method = 'id';
        }

        if (!$isConflictResolution && $this->hasConflict('modResource', $primaryKey, $primary, $data)) {
            return;
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
            if (!isset($data[$field])) $data[$field] = isset($meta['default']) ? $meta['default'] : null;
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

            $pk = $object->getPrimaryKey();
            if (is_array($pk)) {
                $pk = json_encode($pk);
            }
            if (isset($this->orphanedObjects[$pk])) {
                unset($this->orphanedObjects[$pk]);
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

        $criteria = $this->getPartitionCriteria($type['folder']);
        if (is_null($criteria)) {
            $criteria = array();
        }

        if ($this->isForce) {
            $this->modx->removeCollection($type['class'], $criteria);

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

        // Reset the conflicts so we're good to go on new ones
        $this->resetConflicts();
        $this->getExistingObjects($type['class'], $criteria);

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
            $file = file_get_contents($file->getRealPath());

            // Normalize line-endings to \n to ensure consistency
            $file = str_replace("\r\n", "\n", $file);
            $file = str_replace("\r", "\n", $file);

            // Check if delimiter exists, otherwise add it to avoid WARN in explode()
            // (WARN @ Gitify/src/Command/BuildCommand.php : 407) PHP notice: Undefined offset: 1
            if (strpos($file, Gitify::$contentSeparator) === false) {
                $file = $file . Gitify::$contentSeparator;
            }
            // Get the raw data, and the content
            list($rawData, $content) = explode(Gitify::$contentSeparator, $file);

            // Turn the raw YAML data into an array
            $data = Gitify::fromYAML($rawData);
            if (!empty($content)) {
                $data['content'] = $content;
            }

            $this->buildSingleObject($data, $type);
        }

        $this->removeOrphans($type);

        $this->resolveConflicts($folder, $type);

        if (
            class_exists('\modX\Revolution\modNamespace') &&
            in_array($type['class'], ['modNamespace', '\modNamespace', '\modX\Revolution\modNamespace'], true)
        ) {
            $this->modx->getCacheManager()-> generateNamespacesCache('namespaces');
            \modX\Revolution\modNamespace::loadCache($this->modx);
        }
    }

    /**
     * Creates or updates a single xPDOObject.
     *
     * @param $data
     * @param $type
     * @param bool $isConflictResolution
     */
    public function buildSingleObject($data, $type, $isConflictResolution = false) {
	$this->modx->setOption(\xPDO::OPT_SETUP, true);
        $primaryKey = !empty($type['primary']) ? $type['primary'] : 'id';
        $class = $type['class'];

        $primary = $this->_getPrimaryKey($class, $primaryKey, $data);
        $showPrimary = (is_array($primary)) ? json_encode($primary) : $primary;

        if (!$isConflictResolution && $this->hasConflict($class, $primaryKey, $primary, $data)) {
            return;
        }

        $new = false;
        /** @var \xPDOObject|bool $object */
        if (!is_array($primaryKey)) {
            $primary = array($primaryKey => $primary);
        }
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

        $prefix = $isConflictResolution ? '  \ ' : '- ';
        if ($object->save()) {
            if ($this->output->isVerbose()) {
                $new = ($new) ? 'Created new' : 'Updated';
                $this->output->writeln("{$prefix}{$new} {$class}: {$showPrimary}");
            }

            $pk = $object->getPrimaryKey();
            if (is_array($pk)) {
                $pk = json_encode($pk);
            }
            if (isset($this->orphanedObjects[$pk])) {
                unset($this->orphanedObjects[$pk]);
            }
        }
        else {
            $new = ($new) ? 'new' : 'updated';
            $this->output->writeln("{$prefix}<error>Could not save {$new} {$class}: {$showPrimary}</error>");
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
     * @param array $criteria
     * @return array
     */
    public function getExistingObjects($class, $criteria = array())
    {
        $this->existingObjects = array();
        $this->orphanedObjects = array();

        if (!$this->isForce) {
            $iterator = $this->modx->getIterator($class, $criteria);
            foreach ($iterator as $object) {
                /** @var \xPDOObject $object */
                $key = $object->getPrimaryKey();
                if (is_array($key)) {
                    $key = implode('--', $key);
                }

                $this->existingObjects[$key] = $object->toArray();
                $pk = $object->getPrimaryKey();
                if (is_array($pk)) {
                    $pk = json_encode($pk);
                }
                $this->orphanedObjects[$pk] = 1;
            }
        }
    }

    public function resetConflicts()
    {
        $this->updatedObjects = array();
        $this->conflictingObjects = array();
    }

    /**
     * @param $folder
     * @param $type
     * @param bool $isResource
     * @throws \Exception
     */
    public function resolveConflicts($folder, $type, $isResource = false)
    {
        if (!empty($this->conflictingObjects)) {
            $runExtract = false;
            foreach ($this->conflictingObjects as $conflict) {
                $showOriginalPrimary = is_array($conflict['existing_object_primary']) ? json_encode($conflict['existing_object_primary']) : $conflict['existing_object_primary'];
                $this->output->writeln('- Attempting to resolve ID Conflict for <comment>' . $showOriginalPrimary . '</comment> with <comment>' . count($conflict['conflicts']) . '</comment> duplicate(s).');


                // Get the original/master copy of this conflict
                $getPrimary = $conflict['existing_object_primary'];
                if (!is_array($getPrimary)) {
                    $getPrimary = array('id' => $getPrimary);
                }
                $original = $this->modx->getObject($type['class'], $getPrimary);
                if ($original instanceof \xPDOObject) {
                    // Get the primary key definition of the class
                    $objectPrimaryKey = $original->getPK();

                    foreach ($conflict['conflicts'] as $dupe) {
                        $resolved = false;
                        $duplicateObject = $dupe['data'];
                        if (is_string($objectPrimaryKey) && $objectPrimaryKey === 'id') {
                            unset($duplicateObject[$objectPrimaryKey]);

                            $this->output->writeln("  \\ <comment>Duplicate #{$dupe['idx']}</comment>: resolving <comment>primary key conflict</comment> by building object with new auto incremented primary key. ");

                            if ($isResource) {
                                $this->buildSingleResource($duplicateObject, true);
                            }
                            else {
                                $this->buildSingleObject($duplicateObject, $type, true);
                            }
                            $resolved = $runExtract = true;
                        }

                        if (!$resolved) {
                            $this->output->writeln("  \ <error>Unable to resolve ID conflict.</error> The ID conflict will need to be solved manually. ");
                        }
                    }
                }
                else {
                    $this->output->writeln("  \ <comment>Can't load original {$type['class']} with primary {$showOriginalPrimary}</comment> assuming conflict was due to an orphaned object.");
                    $conflict = reset($conflict['conflicts']);
                    $duplicateObject = $conflict['data'];
                    if ($isResource) {
                        $this->buildSingleResource($duplicateObject, true);
                    }
                    else {
                        $this->buildSingleObject($duplicateObject, $type, true);
                    }
                }
            }

            if ($runExtract) {
                $this->output->writeln('- Re-extracting ' . basename($folder) . '; you will need to commit the changes manually.');
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
                $cmdOutput = array_map(function ($n) {
                    return '  \ ' . $n;
                }, $cmdOutput);
                $cmdOutput = implode("\n", $cmdOutput);

                $this->output->write($cmdOutput);
            }
        }
    }

    /**
     * @param $class
     * @param $primaryKey
     * @param $data
     * @return array
     */
    protected function _getPrimaryKey($class, $primaryKey, $data)
    {
        if (is_array($primaryKey)) {
            $primary = array();
            foreach ($primaryKey as $pkVal) {
                $primary[$pkVal] = isset($data[$pkVal]) ? $data[$pkVal] : $this->_getDefaultForField($class, $pkVal);
            }
        } else {
            $primary = $data[$primaryKey];
        }
        return $primary;
    }

    /**
     * @param $data
     * @param $internalPrimary
     * @param $primary
     */
    protected function registerConflict($data, $internalPrimary, $primary)
    {
        if (!isset($this->conflictingObjects[$internalPrimary])) {
            $this->conflictingObjects[$internalPrimary] = array(
                'existing_object_primary' => $primary,
                'conflicts' => array(),
            );
        }
        $this->conflictingObjects[$internalPrimary]['conflicts'][] = array(
            'idx' => count($this->conflictingObjects[$internalPrimary]['conflicts']) + 1,
            'data' => $data,
        );
    }

    /**
     * Checks against conflicts in the source and database. Returns true if there was a conflict, false if all's good.
     *
     * @param $class
     * @param $primaryKey
     * @param $primary
     * @param $data
     * @return bool
     */
    public function hasConflict($class, $primaryKey, $primary, $data)
    {
        $showPrimary = (is_array($primary)) ? json_encode($primary) : $primary;

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

        $prefix = ($class === 'modResource') ? '  \ ' : '- ';
        // First check - have we came across an object with this primary key before?
        if (isset($this->updatedObjects[$internalPrimary])) {
            $this->registerConflict($data, $internalPrimary, $primary);
            $this->output->writeln("$prefix<comment>Primary Key Duplicate found: duplicate {$class} with primary {$showPrimary}</comment>");

            return true;
        }
        // Second check - see if the object already exists in the database with a different real primary keys
        elseif (isset($this->existingObjects[$internalPrimary])) {
            $existingObjPrimary = $this->_getPrimaryKey($class, $primaryKey, $this->existingObjects[$internalPrimary]);
            if ($primary !== $existingObjPrimary) {
                $this->registerConflict($data, $internalPrimary, $existingObjPrimary);
                $showExistingObjPrimary = (is_array($existingObjPrimary)) ? json_encode($existingObjPrimary) : $existingObjPrimary;
                $this->output->writeln("{$prefix}<comment>Primary Key Conflict found: {$class} {$showPrimary} has the same primary key as {$showExistingObjPrimary}</comment>");
                return true;
            }
        }

        $this->updatedObjects[$internalPrimary] = $primary;
        return false;
    }

    /**
     * @param $type
     * @param bool $primary
     */
    public function removeOrphans($type, $primary = false)
    {
        if ($this->input->getOption('no-cleanup')) {
            $orphans = count($this->orphanedObjects);
            if ($orphans > 0) {
                $this->output->writeln("- Found <comment>{$orphans} orphaned {$type['class']}</comment> object(s), but the <comment>--no-cleanup</comment> flag was specified.");
            }
            return;
        }

        if (!$primary) {
            $primary = $type['primary'];
        }
        foreach ($this->orphanedObjects as $pk => $val) {
            $getPrimary = (json_decode($pk)) ? json_decode($pk) : $pk;
            $obj = $this->modx->getObject($type['class'], $getPrimary);
            if ($obj instanceof \xPDOObject) {
                $showPrimary = $this->_getPrimaryKey($type['class'], $primary, $obj->toArray());
                $showPrimary = (is_array($showPrimary)) ? json_encode($showPrimary) : $showPrimary;
                if ($obj->remove()) {
                    $this->output->writeln("- <info>Removed orphaned {$type['class']} with primary {$showPrimary}</info>");
                } else {
                    $this->output->writeln("- <comment>Could not remove orphaned {$type['class']} with primary {$showPrimary}</comment>");
                }
            }
        }
    }
}
