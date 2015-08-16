---
layout: page
title: "Установка проекта Gitify"
category: ru/usage
lang: ru
order: 1
---

# Установка проекта Gitify

После того, как вы собрали сайт с помощью Gitify и закомитили данные в репозиторий, у вас настанет момент, когда нужно будет установить копию этого сайта. Эта заметка описывает этот процесс.

### Предпосылки

Для установки проекта для начала нужно установить Gitify:

* [Установка Gitify](/ru/installation/installation.html)

После того, как Gitify был установлен глобально, мы можем начать устанавливать проект:

### Клонирование и настройка

Сначала мы клонируем репозиторий в папку проекта:

```bash
git clone REPOSITORY-LINK PROJECT-NAME
```

После того, как проект был склонирован, перейдите в папку этого проекта. Теперь откройте файл `.gitify` и настройте по своему вкусу. Если вы используете не основной репозиторий пакетов, а подобный ModMore, вы должны отредактировать данные для авторизации для этого репозитория.

### Установка MODX

Теперь мы готовы попросить gitify установить последнюю версию MODX и все нужные пакеты:

```bash
Gitify install:modx
```

После того, как вы запустили эту команду, Gitify задаст несколько вопросов. Если вы еще не создали базу данных, вы можете вписать данные пользователя MySQL, который имеет права на создание базы банных.

Результат должен выглядеть так:

```bash
Downloading MODX from http://modx.com/download/latest/...
################################################################# 100.0%
Extracting zip...
Moving unzipped files out of temporary directory...
Please complete following details to install MODX. Leave empty to use the [default].
Database Name [gitify_test]: 
Database User [root]: root
Database Password: 
Hostname [robin]: localhost
Base URL [/]: /gitify_test/
Manager Language [en]: 
Manager User [gitify_test_admin]: admin
Database Password [generated]: 
Manager Email: gitify@gmail.com
Running MODX Setup...
Done! Time: 133,557ms | Memory Usage: 2.25 mb | Peak Memory Usage: 2.31 mb
```

### Установка пакетов

Свежий MODX установлен и готов к установке пакетов. Но устанавливать их по одному надоест, поэтому мы просто запустим следующую команду:

```bash
Gitify package:install --all
```

Эта команда устанавливает все пакеты, указанные в файле `.gitify`, результат должен выглядеть так:

```bash
Searching Provider for Ace...
Downloading Ace (1.5.0)...
Installing Ace...
Package Ace installed

Searching Provider for BigBrother...
Downloading Big Brother (1.1.0)...
Installing BigBrother...
Package BigBrother installed

Etc.    
```

### Сборка проекта

После того, как все пакеты установлены, можно завершить установку проекта этой командой:

```bash
Gitify build --force
```

Результат должен выглядеть так. Не беспокойтесь, если будет видно несколько ошибок, связанных с запросами MySQL, оно будет работать все равно.

```bash
Building modContext from contexts/...
Building modContextSetting from context_settings/...
Building content from content/...
Forcing build, removing prior Resources...
- Building web context...
Building modCategory from categories/...
Building modTemplate from templates/...
Building modTemplateVar from template_variables/...
Building modTemplateVarTemplate from template_variables_access/...
.....
Clearing cache...
Done! Time: 9,603ms | Memory Usage: 15.65 mb | Peak Memory Usage: 17.97 mb
```

### Все, ваш проект установлен!

### Права доступа

Если у вас *nix-система и у вас есть проблемы с отображением сайта, возможно это из-за прав доступа. Чтобы это исправить, вызовите следующие команды из корневой папки проекта:

```bash
chmod 777 assets
chmod 777 -R assets/components core/cache core/components core/packages
```

Если есть ошибки с сообщением "доступ запрещен", попробуйте использовать слово `sudo` перед каждой командой.
