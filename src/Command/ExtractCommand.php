<?php
namespace modmore\Gitify\Command;

use modmore\Gitify\BaseCommand;
use modmore\Gitify\Gitify;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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

    protected $_options = array();
    protected $_class = '';
    protected $_fieldMeta = array();
    protected $_fieldAliases = array();
    protected $_tvNames = array();
    protected $_tvIds = array();

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
        // Make variables available to other methods
        $this->_options = $options;
        $this->_class = 'modResource';
        $this->_fieldMeta = $this->modx->getFieldMeta($this->_class);
        $this->_fieldAliases = $this->modx->getFieldAliases($this->_class);

        // Grab all TVs and prepare them for refering to later
        /** @var \modTemplateVar[] $tvs */
        $tvs = $this->modx->getCollection('modTemplateVar');
        foreach ($tvs as $tv) {
            $this->_tvNames[(string)$tv->get('name')] = $tv->get('id');
            $this->_tvIds[(int)$tv->get('id')] = $tv->get('name');
        }

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
            $c->select($this->modx->getSelectColumns('modResource'));
            $c->where($contextCriteria);
//            foreach ($this->_tvNames as $tvName => $tvId) {
//                $c->leftJoin('modTemplateVarResource', 'TV_' . $tvId, "`modResource`.`id` = `TV_{$tvId}`.`contentid` AND `TV_{$tvId}`.`tmplvarid` = {$tvId}");
//                $c->select("`TV_{$tvId}`.`value` as `tv_{$tvName}`");
//            }
            $c->sortby('uri', 'ASC');

            // Turn it into a PDO query
            $c->prepare();
            $sql = $c->toSQL();
            $stmt = $this->modx->query($sql);

            if (!$stmt) {
                var_dump($this->modx->errorInfo());
            }

            if ($stmt && $stmt->execute()) {
                $resources = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($resources as $resource) {
                    /** @var array $resource */
                    $file = $this->generateResource($resource);

                    // Somewhat normalize uris into something we can use as file path that makes (human) sense
                    $uri = $resource['uri'];
                    // Trim trailing slash if there is one
                    if (substr($uri, -1) === '/') {
                        $uri = rtrim($uri, '/');
                    }
                    else {
                        // Get rid of the extension by popping off the last part, and adding just the alias back.
                        $uri = explode('/', $uri);
                        array_pop($uri);

                        // The alias might contain slashes too, so cover that
                        $alias = explode('/', trim($resource['alias'], '/'));
                        $uri[] = end($alias);
                        $uri = implode(DIRECTORY_SEPARATOR, $uri);
                    }

                    if (empty($uri)) {
                        $uri = $resource->id;
                    }

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
            else {
                $this->output->writeln('Could not create SQL query to retrieve resources in ' . $contextKey);
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

        $this->modx->getCacheManager();

        // Determine the primary key for this object
        $pk = isset($options['primary']) ? $options['primary'] : $this->modx->getPK($options['class']);

        // Make variables available to other methods
        $this->_options = $options;
        $this->_class = $options['class'];
        $this->_fieldMeta = $this->modx->getFieldMeta($options['class']);
        $this->_fieldAliases = $this->modx->getFieldAliases($options['class']);

        // Grab the data objects we need to extract
        $data = array();

        // Prepare the query with xPDO
        $c = $this->modx->newQuery($this->_class);
        // Select all fields; without this line it will rename fields to "modSnippet.field_name" instead of just "field_name"
        $c->select($this->modx->getSelectColumns($this->_class));

        // Apply criteria if we have any
        if (!empty($criteria)) {
            $c->where(array($criteria));
        }

        $c->prepare();
        $stmt = $this->modx->query($c->toSQL());
        if ($stmt && $stmt->execute()) {
            $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            // Loop over stuff to generate
            foreach ($data as $item) {
                /** @var \array $item */
                $file = $this->generate($item);

                // Grab the primary key on the object for the file name, including support for composite primary keys
                if (is_array($pk)) {
                    $paths = array();
                    foreach ($pk as $pkVal) {
                        $paths[] = $item[$pkVal];
                    }
                    $path = implode('.', $paths);
                } else {
                    $path = $item[$pk];
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
        else {
            $this->output->writeln('Could not create SQL query to retrieve objects');
        }
    }

    /**
     * @param array $item
     * @return string
     */
    public function generate($item)
    {
        // Strip out keys that have the same value as the default, or are excluded per the .gitify
        $excludes = (isset($options['exclude_keys']) && is_array($options['exclude_keys'])) ? $options['exclude_keys'] : array();

        $meta = $item;

        // Keys that are excluded by the .gitify config
        $excludes = (isset($this->_options['exclude_keys']) && is_array($this->_options['exclude_keys'])) ? $this->_options['exclude_keys'] : array();

        foreach ($item as $key => $value) {
            $dbType = array_key_exists($key, $this->_fieldMeta) ? $this->_fieldMeta[$key]['dbtype'] : 'varchar';
            $phpType = array_key_exists($key, $this->_fieldMeta) ? $this->_fieldMeta[$key]['phptype'] : 'string';

            switch ($phpType) {
                case 'boolean' :
                    $value = (int) $value;
                    break;
                case 'integer' :
                    $value = (int) $value;
                    break;
                case 'float' :
                    $value = (float) $value;
                    break;
                case 'timestamp' :
                case 'datetime' :
                    if (preg_match('/int/i', $dbType)) {
                        $ts = (int)$value;
                    } elseif (in_array($value, $this->modx->driver->_currentTimestamps, true)) {
                        $ts = time();
                    } else {
                        $ts = strtotime($value);
                    }
                    if ($ts !== false && !empty($value)) {
                        $value= strftime('%Y-%m-%d %H:%M:%S', $ts);
                    }
                    break;
                case 'date' :
                    if (preg_match('/int/i', $dbType)) {
                        $ts = (int)$value;
                    } elseif (in_array($value, $this->modx->driver->_currentDates, true)) {
                        $ts = time();
                    } else {
                        $ts = strtotime($value);
                    }
                    if ($ts !== false && !empty($value)) {
                        $value= strftime('%Y-%m-%d', $ts);
                    }
                    break;
                case 'array' :
                    if (is_string($value)) {
                        $value = unserialize($value);
                    }
                    break;
                case 'json' :
                    if (is_string($value) && strlen($value) > 1) {
                        $value = $this->modx->fromJSON($value, true);
                    }
                    break;
            }
            $meta[$key] = $value;

            // Remove the key from the meta if it's the default or a null value where null is accepted
            if (array_key_exists($key, $this->_fieldMeta)) {
                if (array_key_exists('default', $this->_fieldMeta[$key])) {
                    $default = $this->_fieldMeta[$key]['default'];
                    if ($default === $value || (empty($value) && empty($default))) {
                        unset($meta[$key]);
                    }
                }
                elseif (array_key_exists('null', $this->_fieldMeta[$key]) && $this->_fieldMeta['null']) {
                    if (empty($value)) {
                        unset($meta[$key]);
                    }
                }
            }

            // Remove excluded keys
            if (in_array($key, $excludes, true)) {
                unset($meta[$key]);
            }
        }

        // The content is extracted into a separate part of the file. This should make it easier and cleaner
        // to edit in the file directly.
        // As the content may not always be in an actual `content` column, first check the field aliases.
        $contentKey = 'content';
        if (array_key_exists('content', $this->_fieldAliases)) {
            $contentKey = $this->_fieldAliases['content'];
        }

        // Grab the actual content
        $content = '';
        if (array_key_exists($contentKey, $this->_fieldMeta)) {
            $content = $meta[$contentKey];

            if (!empty($content)) {
                foreach ($meta as $key => $value) {
                    if ($value === $content) unset($meta[$key]);
                }
            }
        }

        // Some class-specific tweaks
        switch ($this->_class) {
            // For modElements we update the category to refer to it by name instead of ID
            case 'modTemplate':
            case 'modTemplateVar':
            case 'modChunk':
            case 'modSnippet':
            case 'modPlugin':
                if (array_key_exists('category', $meta) && is_numeric($meta['category']) && $meta['category'] > 0) {
                    $meta['category'] = $this->getCategoryName($meta['category']);
                }
                break;
        }

        // Turn the meta into YAML
        $output = Gitify::toYAML($meta);

        // If we have specific content, we attach it to the output
        if (!empty($content)) {
            $output .= Gitify::$contentSeparator . $content;
        }
        return $output;
    }

    public function generateResource($item)
    {
        $c = $this->modx->newQuery('modTemplateVarResource');
        $c->query['distinct'] = 'DISTINCT';
        $c->leftJoin('modTemplateVar', 'TemplateVar');
        $c->select($this->modx->getSelectColumns('modTemplateVarResource', 'modTemplateVarResource'));
        $c->select($this->modx->getSelectColumns('modTemplateVar', 'TemplateVar', 'tv_', array('id', 'name')));
        $c->select($this->modx->getSelectColumns('modTemplateVarTemplate', 'tvtpl', 'tvtpl_', array('tmplvarid', 'templateid')));
        $c->innerJoin('modTemplateVarTemplate','tvtpl',array(
            'tvtpl.tmplvarid = modTemplateVarResource.tmplvarid',
            'tvtpl.templateid' => $item['template'],
        ));
        $c->where(array(
            'contentid' => $item['id'],
        ));

        $c->prepare();
        $sql = $c->toSQL();
        $stmt = $this->modx->query($sql);
        if ($stmt && $stmt->execute()) {
            $tvs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $item['tvs'] = array();
            foreach ($tvs as $tv) {
                $name = (string)$tv['tv_name'];
                if (array_key_exists('exclude_tvs', $this->_options) && is_array($this->_options['exclude_tvs'])) {
                    if (!in_array($name, $this->_options['exclude_tvs'], true)) {
                        $item['tvs'][$name] = $tv['value'];
                    }
                } else {
                    $item['tvs'][$name] = $tv['value'];
                }
            }
            ksort($item['tvs']);
        }

        return $this->generate($item);
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
