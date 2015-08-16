---
layout: page
title: "Структура файлов данных"
category: ru/config
lang: ru
order: 3
---

# Структура файлов с данными

Файл с данными - это комбинация YAML-блока с мета-данными и области содержимого, если у объекта задан метод `getContent()`.

Верх файла занимают мета-данные в YAML формате. Этот блок включает почти все поля объекта.

Разделитель состоит из пустой строки, за ним 5 тире (`-----`) и еще одной пустой строки. Все, что до разделителя - данные в YAML, все что после - содержимое (content).

## Важные замечания по форматированию в YAML

Важно помнить о некоторых важных вещах во время ручного редактирования файлов и последующей загрузке их в MODX.

1. Строки могут указываться как с кавычками, так и без, однако, как только в строке появляются не буквенно-цифровые символы (точки, слеши, скобки и т.д.), значения строк должны быть заключены в одинарные или двойные кавычки. Иначе вы получите ошибки разбора YAML во время сборки сайта.
2. Точка с запятой в конце строки _не нужна_; перехода на новую строку будет достаточно.
3. Используйте [YAML Linter](http://www.yamllint.com/), если у вас есть ошибки разбора YAML и вы не можете определить, что случилось. Не включайте разделитель или содержимое ниже в код для линтера.

## Примеры файлов с данными

Этот пример содержит только мета-данные (объект Redirect из дополнения Redirector):

```yaml
id: 2
pattern: ^redactor$
target: extras/redactor/
context_key: web
triggered: 257
triggered_first: '2014-08-02 06:32:29'
triggered_last: '2014-08-03 13:18:51'
```

Этот пример содержит мета-данные в yaml и содержимое плагина (плагин ClientConfig):

```php
id: 9
name: ClientConfig
description: 'Sets system settings from the Client Config CMP.'
properties: null

-----

<?php
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
 **/

$eventName = $modx->event->name;

switch($eventName) {
    case 'OnHandleRequest':
        /* Grab the class */
        $path = $modx->getOption(
            'clientconfig.core_path', 
            null, 
            $modx->getOption('core_path') . 'components/clientconfig/'
        );
        $path .= 'model/clientconfig/';
        $clientConfig = $modx->getService(
            'clientconfig',
            'ClientConfig', 
            $path
        );

        /* If we got the class (gotta be careful of failed migrations), 
        grab settings and go! */
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


