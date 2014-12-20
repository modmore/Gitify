Gitify
======

Experiment to allow versioning/building a MODX site through Git.

The goal of Gitify is to provide a **two-way sync** of data typically stored in the MODX database, making it versionable with Git. To do this, it creates a representation of MODX objects in files. These files follow a certain a [human and machine friendly format](https://gist.github.com/Mark-H/5acafdc1c364f70fa4e7), built from a block of YAML, followed by a separator, and then the main content (if there's a specific content field) below that.

The project configuration, which determines what data is written to file and build to the database, is stored in a `.gitify` file in the project root.

- [Installation](#installation)
- [Creating a new Project](#creating-a-new-project)
- [Extract to File](#extract-to-file)
- [Build to MODX](#build-to-modx)
- [Installing a fresh MODX instance](#installing-a-fresh-modx-instance)
- [The .gitify File](#the-gitify-file)
    - [Third party packages (models)](#third-party-packages-models)
    - [Composite Primary Keys](#composite-primary-keys)
- [Changes & History](#changes--history)
- [License](#license)

## Installation

New as of v0.2 is that dependencies are managed via [Composer](https://getcomposer.org/), most notably it has been rebuilt on top of Symfony's Console component to provide a more feature-packed base to build from. [Follow these instructions if you haven't installed Composer before](https://getcomposer.org/doc/00-intro.md)

To get started with Gitify, it's easiest to set up a local clone of this repository. After that, run Composer to download the dependencies, and finally make the Gitify file executable to run it.

```` shell
$ git clone https://github.com/modmore/Gitify.git Gitify
$ cd Gitify
$ composer install
$ chmod +x Gitify
````

At this point you should be able to type `./Gitify` and get a response like the following:

```` shell
Gitify version 0.2.0

Usage:
  [options] command [arguments]

Options:
  --help           -h Display this help message.
  --quiet          -q Do not output any message.
  --verbose        -v|vv|vvv Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug.
  --version        -V Display this application version.
  --ansi              Force ANSI output.
  --no-ansi           Disable ANSI output.
  --no-interaction -n Do not ask any interactive question.

Available commands:
  build          Builds a MODX site from the files and configuration.
  extract        Extracts data from the MODX site, and stores it in human readable files for editing and committing to a VCS.
  help           Displays help for a command
  init           Generates the .gitify file to set up a new Gitify project. Optionally installs MODX as well.
  list           Lists commands
install
  install:modx   Downloads, configures and installs a fresh MODX installation.
````

If that's working as expected, the next step is to add the Gitify executable to your PATH so you can run Gitify in any directory. Edit your `~/.bash_profile` and add the following, with the right path of course:

````
export PATH=/path/to/Gitify/Gitify:$PATH
````

Restart your terminal and you should be good to go.

For successfull installing of MODX by `Gitify install:modx` command you should have installed **unzip** command in your system. For Debian/Ubuntu you can use `sudo apt-get install unzip`.

## Creating a new Project

To create a new project, you can manually create a `.gitify` file, but it's easiest to run `Gitify init`. You will be asked to provide a data directory (relative to the working directory) as well as a few other options as to what to put in the `.gitify` file. The `.gitify` file will be created in the directory you called Gitify from.

There needs to be config.core.php file in the same directory as the `.gitify` file, so either initialise Gitify in the root of a MODX site, add the config.core.php file to point to a MODX core, or run `Gitify install:modx` to set up a fresh MODX installation.

## Extract to File

When you added the `.gitify` file, you can tell Gitify to extract the data to file. Simply run `Gitify extract` and you'll see the file representation of the various database objects show up in the data directory specified.

Wishlist:

* Add a plugin (or package) to automatically extract when changes are made from the manager

## Build to MODX

When you have the files, you can make edits and push them to a repository if you want, but with that you'll also need to be able of installing them in MODX sites. This is the build process, where Gitify takes your files and tries to set them up in your database.

To build, simply call `Gitify build`. If you have a bunch of conflicts and you are not getting the expected results, you can force the build (which will then wipe the content first) by calling `Gitify build --force`. By default the command will also clear the MODX cache after building, to skip this specify the `--skip-clear-cache` flag like `Gitify build --skip-clear-cache`.

## Installing a fresh MODX instance

To quickly set up a fresh MODX installation, use the command `Gitify install:modx` in the folder you want MODX to be installed in. It is possible to choose the version to install, by specifying it as the parameter, for example to install 2.3.1, call `Gitify install:modx 2.3.1-pl`.

Once the MODX zip has been downloaded, it will be extracted and prepared for installation. Gitify will ask you for details about the database, URLs and manager user so be sure to have those ready.

## The `.gitify` File

To define what to export, to where and how, we're using a `.gitify` file formatted in YAML.

An example `.gitify` may look like this:

```` yaml
data_directory: _data/
data:
    contexts:
        class: modContext
        primary: key
    content:
        type: content
        exclude_keys:
            - createdby
            - createdon
            - editedby
            - editedon
    templates:
        class: modTemplate
        primary: templatename
        extension: .html
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
````

The `.gitify` file structure is real simple. There are root nodes for `data_directory` (the relative path where to store the files) and `data`. `data` contains an array of directories to create. These directories then specify either a `type` that has special processing going on (i.e. `content`), or a `class`. The `primary` field determines the key to use in the name of the generated files. This defaults to `id`, but in many cases you may want to use the `name` as that is more human friendly.

By default files will be created with a `.yaml` extension, but if you want you can override that with a `extension` property. This can help with certain data and syntax highlighting in IDEs.

### Third party packages (models)

When a certain class is not part of the core models, you can define a `package` as well. This will run `$modx->addPackage` before attempting to load the data. The path is determined by looking at a `[package].core_path` setting suffixed with `model/`, `[[++core_path]]components/[package]/model/`or a hardcoded `package_path` property. For example, you could use the following in your `.gitify` file to load [ContentBlocks](http://modmo.re/cb) Layouts &amp; Fields:

```` yaml
data:
    cb_fields:
        class: cbField
        primary: name
        package: contentblocks
    cb_layouts:
        class: cbLayout
        primary: name
````

As it'll load the package into memory, it's only required to add the package once. For clarify, it can't hurt to add it to each `data` entry that uses it.

### Composite Primary Keys

When an object doesn't have a single primary key, or you want to get fancy, it's possible to define a composite primary key, by setting the `primary` attribute to an array. For example, like this:

```` yaml
data:
    chunks:
        class: modChunk
        primary: [category, name]
        extension: .html
````

That would grab the category and the name as primary keys, and join them together with a dot in the file name. This is a pretty bad example, and you shouldn't really use it like this.

## Changes & History

Gitify adheres to [semver](http://semver.org). As we are before v1 right now, expect breaking changes and refactorings before the API stabilises.

For changes, please see the commit log or the [Changelog](CHANGELOG.md).

## License

The MIT License (MIT)

Copyright (c) 2014 modmore | More for MODX

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
