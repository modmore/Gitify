---
layout: page
title: Установка
category: ru/installation
lang: ru
order: 1
published: true
---


# Установка

Начиная с версии 0.2 зависимости управляются через [Composer](https://getcomposer.org/). Стоит отметить, что Gitify был создан на базе компонента Console из Symfony, который предоставляет богатые возможности для разработки. [Следуйте этим инструкция, если вы никогда ранее не устанавливали Composer](https://getcomposer.org/doc/00-intro.md)

Для начала работы с Gitify, проще всего установить локальную копию этого репозитория. После этого запустить Composer и скачать завивисимости и в завершение сделать файл Gitify исполняемым.

```bash
git clone https://github.com/modmore/Gitify.git Gitify
cd Gitify
composer install
chmod +x Gitify
``` 

Теперь вы должны иметь возможность набрать `./Gitify` и получить ответ, как показано ниже:

```bash
Gitify version 1.0.0

Usage:
  [options] command [arguments]

Options:
  --help           -h Display this help message.
  --verbose        -v|vv|vvv Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug.
  --version        -V Display the Gitify version.

Available commands:
  build          Builds a MODX site from the files and configuration.
  extract        Extracts data from the MODX site, and stores it in human readable files for editing and committing to a VCS.
  help           Displays help for a command
  init           Generates the .gitify file to set up a new Gitify project. Optionally installs MODX as well.
  list           Lists commands
install
  install:modx   Downloads, configures and installs a fresh MODX installation.

```

Если все работает, как ожидалось, следующим шагом будет добавить Gitify в переменную PATH вашего окружения, что даст возможность запускать Gitify из любой папки. Откройте ваш файл `~/.bash_profile` и добавьте строки из примера, указав правильный путь к папке с Gitify (не к файлу):

```bash
export PATH=/path/to/Gitify/:$PATH

```

Перезапустите ваш терминал и теперь все должно работать.

Для успешной становки MODX с помощью команды `Gitify modx:install` у вас в системе должна быть установлена команда **unzip**. Для Debian/Ubuntu вы можете использовать `sudo apt-get install unzip` для ее установки.