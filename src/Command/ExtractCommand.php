<?php
namespace modmore\Gitify\Command;

use modmore\Gitify\BaseCommand;
use modmore\Gitify\Gitify;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use xPDO\xPDOIterator;

/**
 * Class BuildCommand
 *
 * Builds a MODX site from the files and configuration.
 *
 * @package modmore\Gitify\Command
 */
class ExtractCommand extends BaseCommand
{
    public $categories = array();

    protected $_useResource = null;
    protected $_resource = false;

    protected function configure()
    {
        $this
            ->setName('extract')
            ->setDescription('Extracts data from the MODX site, and stores it in human readable files for editing and committing to a VCS.')

            ->addArgument(
                'partitions',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Specify the data partition key (folder name), or keys separated by a space, that you want to extract. '
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
        // load modResource dependency
        $this->modx->loadClass('modResource');

        $partitions = $input->getArgument('partitions');
        if (!$partitions || empty($partitions)) {
            $partitions = array_keys($this->config['data']);
        }
        foreach ($this->config['data'] as $folder => $options) {
            if (!in_array($folder, $partitions, true)) {
                if ($output->isVerbose()) {
                    $output->writeln('Skipping ' . $folder);
                }
                continue;
            }
            $options['folder'] = $folder;

            switch (true) {
                case (!empty($options['type']) && $options['type'] == 'content'):
                    // "content" is a shorthand for contexts + resources
                    $this->extractContent(GITIFY_WORKING_DIR . $this->config['data_directory'] . $folder, $options);

                    break;

                case (!empty($options['class'])):
                    if (isset($options['package'])) {
                        $this->getPackage($options['package'], $options);
                    }
                    $this->extractObjects(GITIFY_WORKING_DIR . $this->config['data_directory'] . $folder, $options);

                    break;
            }
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
    public function extractContent($folder, $options)
    {
        // Read the current files
        $before = $this->getAllFiles($folder);
        $after = array();
        $extension = (isset($options['extension'])) ? $options['extension'] : '.html';

        $criteria = $this->getPartitionCriteria($options['folder']);

        // Display what we're about to do here
        $resourceCount = $this->modx->getCount('modResource', $criteria);
        $contextCount = $this->modx->getCount('modContext', array('key:!=' => 'mgr'));
        $this->output->writeln("Extracting content into {$options['folder']} ({$resourceCount} resources across {$contextCount} contexts)...");

        // Grab the contexts
        $contexts = $this->modx->getIterator('modContext');
        foreach ($contexts as $context) {
            /** @var \modContext $context */
            $contextKey = $context->get('key');

            // Prepare the criteria for this context
            $contextCriteria = ($criteria) ? $criteria : array();
            $contextCriteria['context_key'] = $contextKey;

            // Grab the count
            $count = $this->modx->getCount('modResource', $contextCriteria);
            $this->output->writeln("- Extracting resources from {$contextKey} context ({$count} resources)...");

            // Grab the resources in the context
            $c = $this->modx->newQuery('modResource');
            $c->where($contextCriteria);
            $c->sortby('uri', 'ASC');
            $resources = $this->modx->getIterator('modResource', $c);
            if (isset($options['limit_per_parent'])) {
                $resources = $this->limitPerParent($options['limit_per_parent'], $resources);
            }
            foreach ($resources as $resource) {
                /** @var \modResource $resource */
                $file = $this->generate($resource, $options);

                // Somewhat normalize uris into something we can use as file path that makes (human) sense
                $uri = $resource->uri;
                // Trim trailing slash if there is one
                if (substr($uri, -1) == '/')
                {
                    $uri = rtrim($uri, '/');
                }
                else
                {
                    // Get rid of the extension by popping off the last part, and adding just the alias back.
                    $uri = explode('/', $uri);
                    array_pop($uri);

                    // The alias might contain slashes too, so cover that
                    $alias = explode('/', trim($resource->alias, '/'));
                    $uri[] = end($alias);
                    $uri = implode(DIRECTORY_SEPARATOR, $uri);
                }

                if (empty($uri)) $uri = $resource->id;

                // Write the file
                $fn = $folder . DIRECTORY_SEPARATOR . $contextKey . DIRECTORY_SEPARATOR . $uri . $extension;

                $fn = $this->normalizePath($fn);

                $after[] = $fn;

                // Only write stuff if it doesn't exist already, or is not the same
                $written = false;
                if (!file_exists($fn) || file_get_contents($fn) != $file) {
                    $this->modx->cacheManager->writeFile($fn, $file);
                    $written = true;
                }

                // If we're in verbose mode (-v/--verbose), output a message with what we did
                if ($this->output->isVerbose()) {
                    $this->output->writeln('  \ ' . ($written ? "Generated {$uri}{$extension}" : "Skipped {$uri}{$extension}, no change"));
                }
            }
        }

        // Clean up removed object files
        $old = array_diff($before, $after);
        foreach ($old as $oldFile)
        {
            unlink($oldFile);
            // If in verbose mode, let it be known to the world
            if ($this->output->isVerbose()) {
                $oldFileName = substr($oldFile, strlen($folder));
                $this->output->writeln("  \\ Removed {$oldFileName}, no longer exists");
            }
        }
    }

    /**
     * @param array{limit?: int, sort_by?: string, sort_dir?: string} $options
     *
     * @return array|xPDOIterator
     */
    private function limitPerParent(array $options, xPDOIterator $resources)
    {
        if (!is_numeric($options['limit']) || iterator_count($resources) === 0) {
            return $resources;
        }
        $limit = $options['limit'];
        $sortField = $options['order_by'] ?? 'id';
        $sortDir = strtolower($options['order_dir'] ?? 'asc');
        if (!in_array($sortDir, ['desc', 'asc'], true)) {
            $sortDir = 'asc';
        }
        // group by parent
        $grouped = [];
        foreach ($resources as $resource) {
            $grouped[$resource->get('parent')][] = $resource;
        }
        // sort
        foreach ($grouped as &$toSort) {
            uasort(
                $toSort,
                static function (\modResource $resourceA, \modResource $resourceB) use ($sortField, $sortDir): int {
                    $fieldA = $resourceA->get($sortField);
                    $fieldB = $resourceB->get($sortField);
                    if ($fieldA === $fieldB) {
                        return 0;
                    }
                    if ($sortDir === 'asc') {
                        // a is "less", we want it first (so lower score)
                        return $fieldA < $fieldB ? -1 : 1;
                    }

                    // a is less, we want it after (descending)
                    return $fieldA < $fieldB ? 1 : -1;
                }
            );
        }

        // keep only needed amount
        $kept = [];
        foreach ($grouped as $children) {
            array_push($kept, ...array_slice($children, 0, $limit));
        }

        return $kept;
    }

    /**
     * Loads all objects for a specified class, first clearing out the current data.
     *
     * @param $folder
     * @param $options
     */
    public function extractObjects($folder, array $options = array())
    {
        $criteria = $this->getPartitionCriteria($options['folder']);

        $count = $this->modx->getCount($options['class'], $criteria);
        $this->output->writeln("Extracting {$options['class']} into {$options['folder']} ({$count} records)...");

        // Read the current files
        $before = $this->getAllFiles($folder);
        $after = array();

        // Grab the stuff
        $c = $this->modx->newQuery($options['class']);
        if (!empty($criteria)) {
            $c->where(array($criteria));
        }
        $collection = $this->modx->getCollection($options['class'], $c);

        $this->modx->getCacheManager();

        // Loop over stuff to generate
        $pk = isset($options['primary']) ? $options['primary'] : '';
        foreach ($collection as $object) {
            /** @var \xPDOObject $object */
            $file = $this->generate($object, $options);

            // Grab the primary key on the object, including support for composite primary keys
            if (empty($pk)) {
                $path = $object->getPrimaryKey();
            }
            elseif (is_array($pk)) {
                $paths = array();
                foreach ($pk as $pkVal) {
                    $paths[] = $object->get($pkVal);
                }
                $path = implode('.' , $paths);
            }
            else {
                $path = $object->get($pk);
            }

            $path = $this->filterPathSegment($path);
            $path = str_replace('/', '-', $path);

            $ext = (isset($options['extension'])) ? $options['extension'] : '.yaml';
            $fn = $folder . DIRECTORY_SEPARATOR . $path . $ext;

            $fn = $this->normalizePath($fn);

            $after[] = $fn;

            $written = false;
            if (!file_exists($fn) || file_get_contents($fn) != $file) {
                $this->modx->cacheManager->writeFile($fn, $file);
                $written = true;
            }
            if ($this->output->isVerbose()) {
                $this->output->writeln($written ? "- Generated {$path}{$ext}" : "- Skipped {$path}{$ext}, no change");
            }
        }

        // Clean up removed object files
        $old = array_diff($before, $after);
        foreach ($old as $oldFile)
        {
            unlink($oldFile);
            // If in verbose mode, let it be known to the world
            if ($this->output->isVerbose()) {
                $oldFileName = substr($oldFile, strlen($folder));
                $this->output->writeln("- Removed {$oldFileName}, no longer exists");
            }
        }
    }

    /**
     * @param \xPDOObject|\modElement $object
     * @param array $options
     * @return string
     */
    public function generate($object, array $options = array())
    {
        // Strip out keys that have the same value as the default, or are excluded per the .gitify
        $excludes = (isset($options['exclude_keys']) && is_array($options['exclude_keys'])) ? $options['exclude_keys'] : array();

        $fieldMeta = $object->_fieldMeta;
        $data = $this->objectToArray($object, $options);

        // If there's a dedicated content field, we put that below the yaml for easier managing,
        // unless the object is a modStaticResource, calling getContent on a static resource can break the
        // extracting because it tries to return the (possibly binary) file.
        // the same problem with modDashboardWidget, it's have custom getContent method
        $content = '';
        if (method_exists($object, 'getContent')
            && !($object instanceof \modStaticResource)
            && !($object instanceof \modDashboardWidget)
            && !in_array('content', $excludes)
        ) {
            $content = $object->getContent();

            if (!empty($content)) {
                foreach ($data as $key => $value) {
                    if ($value === $content) unset($data[$key]);
                }
            }
        }

        foreach ($data as $key => $value) {
            if (
                (isset($fieldMeta[$key]['default']) && $value === $fieldMeta[$key]['default']) //@fixme
                || in_array($key, $excludes)
            )
            {
                unset($data[$key]);
            }
        }

        $out = Gitify::toYAML($data);

        if (!empty($content)) {
            $out .= Gitify::$contentSeparator . $content;
        }
        return $out;
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
     * Loops over a folder to get all the files in it. Uses for cleaning up old files.
     *
     * @param $folder
     * @return array
     */
    public function getAllFiles($folder)
    {
        $files = array();
        try {
            $di = new \RecursiveDirectoryIterator($folder, \RecursiveDirectoryIterator::SKIP_DOTS);
            $it = new \RecursiveIteratorIterator($di);
        } catch (\Exception $e) {
            return array();
        }

        foreach($it as $file)
        {
            /** @var \SplFileInfo $file */
            $file_path = $file->getPath() . DIRECTORY_SEPARATOR . $file->getFilename();
            $files[] = $this->normalizePath($file_path);
        }
        return $files;
    }


    /**
     * Turns a category ID into a name
     *
     * @param $id
     * @return string
     */
    public function getCategoryName($id) {
        if (isset($this->categories[$id])) return $this->categories[$id];

        $category = $this->modx->getObject('modCategory', $id);
        if ($category) {
            $this->categories[$id] = $category->get('category');
            return $this->categories[$id];
        }
        return '';
    }

    /**
     * Turns the object into an array, and do some more processing depending on the object.
     *
     * @param \xPDOObject $object
     * @pram array $options
     * @return array
     */
    protected function objectToArray(\xPDOObject $object, array $options = array())
    {
        $data = $object->toArray('', true, true);
        switch (true) {
            // Handle TVs for resources automatically
            case $object instanceof \modResource:
                /** @var \modResource $object */
                $tvs = array();
                $templateVars = $object->getTemplateVars();
                foreach ($templateVars as $tv) {
                    /** @var \modTemplateVar $tv */
                    $name = $tv->get('name');
                    if (isset($options['exclude_tvs']) && is_array($options['exclude_tvs'])) {
                        if (!in_array($name, $options['exclude_tvs'])) {
                            $tvs[$tv->get('name')] = $tv->get('value');
                        }
                    }
                    else {
                        $tvs[$tv->get('name')] = $tv->get('value');
                    }
                }
                ksort($tvs);
                $data['tvs'] = $tvs;
                break;

            // Handle string-based categories automagically on elements
            case $object instanceof \modElement && !($object instanceof \modCategory):
                if (isset($data['category']) && !empty($data['category']) && is_numeric($data['category'])) {
                    $data['category'] = $this->getCategoryName($data['category']);
                }
                break;
        }

        return $data;
    }

    /**
     * Uses the modResource::filterPathSegment method if available for cleaning a file path.
     * When it is not available (pre MODX 2.3) it uses a fake resource to call its cleanAlias method
     *
     * @param $path
     * @return string
     */
    protected function filterPathSegment($path)
    {
        if ($this->_useResource === null) {
            $resource = $this->modx->newObject('modResource');
            if (method_exists($resource, 'filterPathSegment')) {
                $this->_useResource = false;
            }
            else {
                $this->_useResource = true;
                $this->_resource = $resource;
            }
        }

        $options = array(
            'friendly_alias_lowercase_only' => false,
        );

        if ($this->_useResource) {
            return $this->_resource->cleanAlias($path, $options);
        }
        return \modResource::filterPathSegment($this->modx, $path, $options);
    }
}
