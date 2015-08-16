---
layout: page
title: "Файл .gitify"
category: ru/config
lang: ru
order: 1
---

# Файл .gitify

Чтобы указать, что экспортировать, где и как, мы используем файл `.gitify` формата YAML.

Пример файла `.gitify` может выглядеть так:

```yaml
data_directory: _data/
data:
    contexts:
        class: modContext
        primary: key
    content:
        type: content
        exclude_keys:
            - editedby
            - editedon
    templates:
        class: modTemplate
        primary: templatename
        extension: .html
    categories:
        class: modCategory
        primary: category
        truncate_on_force:
            - modCategoryClosure
    template_variables:
        class: modTemplateVar
        primary: name
    chunks:
        class: modChunk
        primary: name
        extension: .html
    snippets:
        class: modSnippet
        primary: name
        extension: .php
    plugins:
        class: modPlugin
        primary: name
        extension: .php
```

Структура `.gitify` файла очень проста. В нем есть корневые узлы `data_directory` (относительный путь к месту, где хранятся файлы), `backup_directory`, `data` и `packages`. 

`data` состоит из массива, который мы называем "Разделы". Раздел - это обычно имя папки, которая содержит все файлы этого типа, а так же может использоваться в командах `Gitify extract` и `Gitify build`. Каждый раздел описывает один тип `type`, который будет обрабатываться определенным способом (сейчас доступен как тип только `content`), или класс `class`, который является производным от xPDOObject и который вы хотите использовать. Поле `primary` определяет ключ, на основе которого будет дано имя генерируемому файлу. По умолчанию это `id`, но во многих случаях вы можете использовать `name`, так понятнее для людей. `primary` используется для имен файлов, а так же участвует в автоматическом разрешении конфликтов с ID.

По умолчанию файлы будут созданы с расширением `.yaml`, но вы можете поменять его через свойство `extension`, если хотите. Это иногда может помочь с подстветкой синтаксиса в IDE.

Так же каждый раздел может включать свойство `where`. Оно содержит массив, который может быть превращен в валидный запрос xPDO (xPDO Criteria).

Когда используется GitifyWatch, в корне файла конфигурации gitify добавляется узел `environments`. Подробнее об этом смотрите в документации к GitifyWatch.

### Сторонние дополнения (модели)

Если определенный класс не является частью ядра MODX, вы так же можете указать свойство `package`. Gitify выполнит `$modx->addPackage` перед попыткой загрузить данные. Путь определяется через системную настройку `[package].core_path`, дополненную строкой `model/`, через системную настройку `[[++core_path]]components/[package]/model/` или через непосредственное указание пути в свойстве `package_path`. Для примера, вы можете дополнить ваш файл `.gitify` для загрузки Layouts & Fields из [ContentBlocks](http://modmo.re/cb):

```yaml
data:
    cb_fields:
        class: cbField
        primary: name
        package: contentblocks
    cb_layouts:
        class: cbLayout
        primary: name
```

Так как пакет будет загружен в память, то требуется добавить пакет только 1 раз, но ничего не мешает добавить это свойство ко всем записям в `data`, использующих этот пакет.

### Взаимодействие с замыканиями

Замыкание - это отдельная таблица в базе данных, которую ядро или строннее дополнение может использовать, чтобы хранить информацию об иерархии в удобном формате. Часто эти данные генерируются автоматически при создании новых объектов, что может привести к ошибкам и другим проблемам при сборке сайта, особенно когда используется флаг `--force`.

Чтобы решить эту проблему, в версии v0.6.0 добавился параметр `truncate_on_force`, который позволяет определить массив имен классов, таблицы которых нужно очистить при форсированной сборке. Очистка таблицы замыканий перед форсированной сборкой позволяет быть уверенным, что  модель сможет правильно создать строки в таблице и избежать ошибок.

Ниже два примера использования `truncate_on_force`:

```yaml
data:
    categories:
        class: modCategory
        primary: category
        truncate_on_force:
            - modCategoryClosure
    quip_comments:
        class: quipComment
        package: quip
        primary: [thread, id]
        truncate_on_force: 
            - quipCommentClosure
```

### Составные первичные ключи

Когда у объекта не одинарный первичный ключ или когда вы хотите добавить возможность работы с именами файлов, есть возможность задать составной первичный ключ, задав свойство `primary` как массив. Например, так:

```yaml
data:
    chunks:
        class: modChunk
        primary: [category, name]
        extension: .html
```

В этом примере категория и имя будут использованы как первичный ключ и имя файла будет состоять из категории и имени, разделенных точкой. Это очень плохой пример и на самом деле вам так делать не нужно.

### Установка пакетов MODX

Вы так же можете указать пакеты, которые нужно установить. Делается это так:

```yaml
packages:
    modx.com:
        service_url: http://rest.modx.com/extras/
        packages:
            - ace
            - wayfinder
    modmore.com:
        service_url: https://rest.modmore.com/
        username: username
        api_key: .modmore.com.key
        packages:
            - clientconfig
```

Когда указывается `api_key` у провайдера, как в примере выше, значение ключа берется из файла, имя которого указано в свойстве `api_key`. Таким образом значение `.modmore.com.key` в примере выше загружает файл `/path/to/your/base/directory/.modmore.com.key`. Файлы с ключами должны быть исключены из контроля версий c помощью файла .gitignore, и вы так же должны защитить эти файлы от прямого чтения с помощью .htaccess или хранить их за пределами webroot.

Для установки пакетов, которые вы добавили в раздел `packages` в вашем файле `.gitify` просто запустите команду `Gitify package:install --all`. Эта команда попробует установить все пакеты, которые были указаны, пропуская только те, которые уже установлены.
