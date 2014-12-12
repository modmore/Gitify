<?php

/**
 * Class GitifyExtract
 */
class GitifyExtract extends Gitify
{
    private $contentTypeExceptions = array("pdf");
    public $categories = array();

    /**
     * Runs the GitifyExtract command
     *
     * @param array $project
     */
    public function run(array $project = array())
    {
        if (!file_exists($project['path'] . 'config.core.php')) {
            echo "Error: there does not seem to be a MODX install present here.\n";
            return false;
        }

        if (!$this->loadMODX($project['path'])) {
            echo "Error: Could not load the MODX API\n";
            return false;
        }

        foreach ($project['data'] as $folder => $type) {
            switch (true) {
                case (!empty($type['type']) && $type['type'] == 'content'):
                    // "content" is a shorthand for contexts + resources
                    echo date('H:i:s') . " - Loading content into $folder/...\n";
                    $this->loadContent($project['data_directory'] . $folder, $type);

                    break;

                case (!empty($type['class'])):
                    echo date('H:i:s') . " - Loading " . $type['class'] . " into $folder/...\n";
                    if (isset($type['package'])) {
                        $this->getPackage($type['package'], $type);
                    }
                    $this->loadObjects($project['data_directory'] . $folder, $type);

                    break;
            }
        }
        echo "Done!\n";
        return true;
    }

    /**
     * Loads the Content, handling uris for naming etc.
     *
     * @param $folder
     * @param $options
     */
    public function loadContent($folder, $options)
    {
        $extension = (isset($options['extension'])) ? $options['extension'] : '.html';

        // Empty the current data
        $this->modx->getCacheManager()->deleteTree($folder, array('extensions' => ''));

        // Grab the contexts
        $contexts = $this->modx->getCollection('modContext');
        foreach ($contexts as $context) {
            /** @var modContext $context */
            $key = $context->get('key');

            // Grab the resources in the context
            $resources = $this->modx->getCollection('modResource', array('context_key' => $key));
            foreach ($resources as $resource) {
                /** @var modResource $resource */
                $file = $this->generate($resource, $options);

                // Somewhat normalize uris into something we can use as file path that makes (human) sense
                $uri = $resource->uri;
                if (substr($uri, -1) == '/') {
                    // Trim the trailing slash
                    $uri = rtrim($uri, '/');
                }
                else {
                    // Get rid of the extension by popping off the last part, and adding just the alias back.
                    $uri = explode('/', $uri);
                    array_pop($uri);
                    $uri[] = $resource->alias;
                    $uri = implode('/', $uri);
                }

                if (empty($uri)) $uri = $resource->id;

                // Write the file
                $fn = $folder . DIRECTORY_SEPARATOR . $key . DIRECTORY_SEPARATOR . $uri . $extension;
                $this->modx->getCacheManager()->writeFile($fn, $file);
            }
        }
    }

    /**
     * Loads all objects for a specified class, first clearing out the current data.
     *
     * @param $folder
     * @param $options
     */
    public function loadObjects($folder, array $options = array())
    {
        // Read the current files
        $before = $this->getAllFiles($options['path'] . $folder);
        $after = array();

        // Grab the stuff
        $c = $this->modx->newQuery($options['class']);
        if (isset($options['where'])) $c->where(array($options['where']));
        $collection = $this->modx->getCollection($options['class'], $c);

        $this->modx->getCacheManager();

        // Loop over stuff
        $pk = isset($options['primary']) ? $options['primary'] : '';
        foreach ($collection as $object) {
            /** @var xPDOObject $object */
            $file = $this->generate($object, $options);
            if( empty($pk) ) {
                $path = $object->getPrimaryKey();
            } else if (is_array($pk)) {
                $paths = array();
                foreach ($pk as $pkVal) {
                    $paths[] = $object->get($pkVal);
                }
                $path = implode('.',$paths);
            } else {
                $path = $object->get($pk);
            }

            $ext = (isset($options['extension'])) ? $options['extension'] : '.yaml';
            $fn = $folder . DIRECTORY_SEPARATOR . $path . $ext;
            $after[] = $fn;

            if (file_get_contents($fn) != $file) {
                $this->modx->cacheManager->writeFile($fn, $file);
            }
        }

        $old = array_diff($before, $after);
        foreach ($old as $oldFile) {
            unlink($oldFile);
        }
    }

    /**
     * @param xPDOObject|modElement $object
     * @param array $options
     * @return string
     */
    public function generate($object, array $options = array())
    {
        $isException = false;
        $fieldMeta = $object->_fieldMeta;
        $data = $object->toArray('', true, true);
        
        $contentType = explode('/',$object->contentType);
        $contentTypeExt = array_pop($contentType);
        
        if( !($isException = in_array($contentTypeExt, $this->contentTypeExceptions)) ) {
            // If there's a dedicated content field, we put that below the yaml for easier managing
            $content = '';
            if (method_exists($object, 'getContent')) {
                $content = $object->getContent();

                if (!empty($content)) {
                    foreach ($data as $key => $value) {
                        if ($value == $content) unset($data[$key]);
                    }
                }
            }
        }
        
        // Strip out keys that have the same value as the default, or are excluded per the .gitify
        $excludes = (isset($options['exclude_keys']) && is_array($options['exclude_keys'])) ? $options['exclude_keys'] : array();
        foreach ($data as $key => $value) {
            if (
                (isset($fieldMeta[$key]['default']) && $value === $fieldMeta[$key]['default']) //@fixme
                || in_array($key, $excludes)
            )
            {
                unset($data[$key]);
            }
        }

        if ($object instanceof modElement && !($object instanceof modCategory)) {
            // Handle string-based categories automagically
            if (isset($data['category']) && !empty($data['category']) && is_numeric($data['category'])) {
                $catId = $data['category'];
                $data['category'] = $this->getCategoryName($catId);
            }
        }

        $data = $this->expandJSON($data);

        $out = $this->toYAML($data);

        if (!empty($content)) {
            $out .= $this->sep;
            $out .= $content;
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

        $this->modx->addPackage($package, $path);
    }

    /**
     * Takes in an array of data, expanding JSON formatted data into an array to be nicely
     * transformed into YAML later.
     *
     * @param $data
     * @return mixed
     */
    public function expandJSON($data)
    {
        foreach ($data as $key => $value) {
            if (!empty($value) && is_string($value) && (strpos($value, '{') !== -1)) {
                $json = $this->modx->fromJSON($value);
                if (is_array($json)) $data[$key] = $this->expandJSON($json);
            }
            elseif (is_array($value)) {
                $data[$key] = $this->expandJSON($value);
            }
        }
        return $data;
    }

    public function getAllFiles($folder)
    {
        $files = array();
        try {
            $di = new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS);
            $it = new RecursiveIteratorIterator($di);
        } catch (Exception $e) {
            return array();
        }

        foreach($it as $file)
        {
            /** @var SplFileInfo $file */
            $files[] = $file->getPath() . DIRECTORY_SEPARATOR . $file->getFilename();
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
}
