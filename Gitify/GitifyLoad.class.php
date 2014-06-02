<?php

/**
 * Class GitifyLoad
 */
class GitifyLoad extends Gitify
{

    public function run(array $project = array())
    {
        if (!file_exists($project['path'] . 'config.core.php')) {
            echo "Error: there does not seem to be a MODX install present here.\n";
            exit(1);
        }

        if (!$this->loadMODX($project['path'])) {
            echo "Error: Could not load the MODX API\n";
            exit(1);
        }

        foreach ($project['data'] as $folder => $type) {
            switch (true) {
                case (is_string($type) && $type == 'content'):
                    // "content" is a shorthand for contexts + resources
                    echo date('H:i:s') . " - Loading content into $folder/...\n";
                    $this->loadContent($project['path'] . $folder);

                    break;

                case (is_array($type) && !empty($type['class'])):
                    echo date('H:i:s') . " - Loading " . $type['class'] . " into $folder/...\n";
                    $this->loadGeneric($project['path'] . $folder, $type);

                    break;
            }
        }

        echo "Done!\n";
        exit(0);
    }

    public function loadContent($folder)
    {
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
                $file = $this->generateResource($resource);

                // Somewhat normalize uris into something we can use as file path that makes (human) sense
                $uri = $resource->uri;
                if ($resource->isfolder) {
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
                $fn = $folder . DIRECTORY_SEPARATOR . $key . DIRECTORY_SEPARATOR . $uri . '.html';
                $this->modx->getCacheManager()->writeFile($fn, $file);
            }
        }
    }

    public function loadGeneric($folder, $type)
    {
        // Empty the current data
        $this->modx->getCacheManager()->deleteTree($folder, array('extensions' => ''));

        // Grab the stuff
        $c = $this->modx->newQuery($type['class']);
        if (isset($type['where'])) $c->where(array($type['where']));
        $collection = $this->modx->getCollection($type['class'], $c);

        // Loop over stuff
        $pk = isset($type['primary']) ? $type['primary'] : '';
        foreach ($collection as $object) {
            /** @var xPDOObject $object */
            $file = $this->generateGeneric($object, $type);
            $path = empty($pk) ? $object->getPrimaryKey() : $object->get($pk);

            $ext = (isset($type['extension'])) ? $type['extension'] : '.json';
            $fn = $folder . DIRECTORY_SEPARATOR . $path . $ext;
            $this->modx->getCacheManager()->writeFile($fn, $file);


        }





    }

    /**
     * @param modResource $resource
     * @return string
     */
    public function generateResource($resource)
    {
        $resourceMeta = $resource->get(array('id', 'class_key', 'pagetitle', 'longtitle', 'description', 'introtext', 'template', /*'alias',*/ 'menutitle', 'link_attributes', 'hidemenu', 'published', 'parent', 'content_type', 'content_dispo', 'menuindex', 'pub_date', 'unpub_date', 'isfolder', 'searchable', 'richtext', 'uri_override', 'uri', 'properties'));
        $out = $this->toJSON($resourceMeta);
        $out .= $this->sep;
        $out .= $resource->get('content');
        return $out;
    }

    /**
     * @param xPDOObject|modElement $object
     * @param array $options
     * @return string
     */
    public function generateGeneric($object, array $options = array())
    {
        $data = $object->toArray();
        $content = '';

        if (method_exists($object, 'getContent')) {
            $content = $object->getContent();

            if (!empty($content)) {
                foreach ($data as $key => $value) {
                    if ($value == $content) unset($data[$key]);
                }
            }
        }
        $out = $this->toJSON($data);

        if (!empty($content)) {
            $out .= $this->sep;
            $out .= $content;
        }
        return $out;
    }
}
