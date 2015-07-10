---
layout: page
title: "Data File Structure"
category: en/config
lang: en
order: 3
---

# Data File Structure

Data files are formatted in a combination of YAML and a content area if the object has a getContent method defined.

The top of the file contains the YAML meta data. This includes most of the objects' fields. 

The separator is a blank line, followed by five dashes (`-----`) and another blank line. Anything before the separated is the YAML data, everything below it is the content.

## YAML Formatting Notes

When manually editing the data files, to build into MODX later, it's important to keep a few things in mind.

1. Strings can be edited with or without quotes, however as soon as there's any non-alphanumeric characters inside the string (dots, slashes, brackets etc), there should be single or double quotes around the value. Otherwise, you might get a YAML parse error on build.
2. _No_ commas at the end of a line; the line break is enough.
3. Use the [YAML Linter](http://www.yamllint.com/) if you get a YAML parse error but can't figure out what's causing it. Don't include the separator or content below it though.

## Example data files

This example only has simple meta data (Redirector Redirect object):

```yaml
id: 2
pattern: ^redactor$
target: extras/redactor/
context_key: web
triggered: 257
triggered_first: '2014-08-02 06:32:29'
triggered_last: '2014-08-03 13:18:51'
```

This example shows the YAML meta data, followed by the content (the ClientConfig plugin):

```yaml
id: 9
name: ClientConfig
description: 'Sets system settings from the Client Config CMP.'
properties: null

-----

/**
 * ClientConfig
 *
 * Copyright 2011 by Mark Hamstra <hello@markhamstra.com>
 *
 * ClientConfig is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * ClientConfig is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * ClientConfig; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * @package clientconfig
 *
 * @var modX $modx
 * @var int $id
 * @var string $mode
 * @var modResource $resource
 * @var modTemplate $template
 * @var modTemplateVar $tv
 * @var modChunk $chunk
 * @var modSnippet $snippet
 * @var modPlugin $plugin
*/

$eventName = $modx->event->name;

switch($eventName) {
    case 'OnHandleRequest':
        /* Grab the class */
        $path = $modx->getOption('clientconfig.core_path', null, $modx->getOption('core_path') . 'components/clientconfig/');
        $path .= 'model/clientconfig/';
        $clientConfig = $modx->getService('clientconfig','ClientConfig', $path);

        /* If we got the class (gotta be careful of failed migrations), grab settings and go! */
        if ($clientConfig instanceof ClientConfig) {
            $settings = $clientConfig->getSettings();

            /* Make settings available as [[++tags]] */
            $modx->setPlaceholders($settings, '+');

            /* Make settings available for $modx->getOption() */
            foreach ($settings as $key => $value) {
                $modx->setOption($key, $value);
            }
        }
        break;
}

return;
```


