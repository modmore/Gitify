Gitify
======

Experiment to allow versioning/building a MODX site through Git.

The goal of Gitify is to provide a two-way sync of data typically stored in the MODX database with Git. To do this, it creates a representation of xPDOObject's in files. These files follow a certain a [human and machine friendly format](https://gist.github.com/Mark-H/5acafdc1c364f70fa4e7) with simple JSON at the top of the file, and the content (if there's any specific content field) below that. The configuration, which determines what data is written to file, is stored in a `.gitify` file in the project root.

## Installation

To get started with Gitify, it's easiest to set up a local clone of this repository. After that, make the Gitify file executable.

```` shell
$ git clone https://github.com/modmore/Gitify.git Gitify
$ cd Gitify
$ chmod +x Gitify
````

At this point you should be able to type `./Gitify` and get a response like the following:

````
Usage: Gitify [command] [options]

Commands:
    init            starts a new Gitify project, creating a `.gitify` configuration
                    file and optionally installing MODX
    load            loads the MODX site into the repository per the `.gitify` configuration file
    build           builds the site from the repository into MODX
    install-modx    installs the latest MODX version into the current directory
````

If that's working as expected, you will probably want to add the Gitify executable to your PATH so you can run Gitify in any directory. Edit your .bash_profile and add the following:

````
export PATH=/path/to/Gitify/Gitify:$PATH
````

Restart your terminal and you should be good to go.

## Creating a new Project

To create a new project, you can manually create a `.gitify` file, but it's easiest to run `Gitify init`. You will be asked to provide a project name, a data directory (relative to the working directory) as well as a few other options as to what to put in the `.gitify` file. The `.gitify` file will be created in the directory you called Gitify from.

There needs to be config.core.php file in the same directory as the `.gitify` file, so either initialise Gitify in the root of a MODX site, or add the config.core.php file to point to a MODX core. You can also add a `path` property to the `.gitify` file that points to a MODX root.

## Load to File

When you added the `.gitify` file, you can tell Gitify to load the data to file. Simply run `Gitify load` and you'll see the file representation of the various database objects show up in the data directory specified.

To do:

* This process will need to be automatically executed when changes are made to resources, elements or other data that is written to file.
* Changes need to be automatically committed and, if a remote exists, pushed.
* Make sure nuking files and rebuilding them does not trigger changes to the file that could complicate git merges.

## Build to MODX

When you have the files, you can make edits and push them to a repository if you want, but with that you'll also need to be able of installing them in MODX sites. This is the build.

Building has not yet been added, but it will be along the lines of `Gitify build`. That process will set up all the database records, updating changes, and clearing the cache. It will also try to handle possible ID issues for resources, where multiple resources exist with the same ID due to branch merges.


## The `.gitify` File

To define what to export, to where and how, we're using a `.gitify` file formatted in YAML.

An example `.gitify` may look like this:

```` yaml
name: Project Name
data_directory: project_data_directory/
data:
  content:
    type: content
    exclude_keys: [createdby, createdon, editedby, editedon]
  categories:
    class: modCategory
    primary: category
  templates:
    class: modTemplate
    primary: name
  template_variables:
    class: modTemplateVar
    primary: name
  chunks:
    class: modChunk
    primary: name
  snippets:
    class: modSnippet
    primary: name
    extension: .php
  plugins:
    class: modPlugin
    primary: name
    extension: .php
````

The `.gitify` file structure is real simple. There are root notes for `name` (the project name), `data_directory` (the relative path where to store the files) and `data`. `data` contains an array of directories to create. These directories then specify either a `type` that has special processing going on (i.e. `content`), or a `class`. The `primary` field determines the key to use in the name of the generated files. This defaults to `id`, but in many cases you may want to use the `name` as that is more human friendly.

By default files will be created with a `.yaml` extension, but if you want you can override that with a `extension` property. This can help with certain data and syntax highlighting in IDEs.

When a certain class is not part of the core models, you can define a `package` as well. This will run `$modx->addPackage` before attempting to load the data. The path is determined by looking at a `[package].core_path` setting suffixed with `model/`, or a defined `package_path` property.

By adding a `where` property you can filter the objects that will be loaded.
