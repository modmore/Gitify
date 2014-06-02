Gitify
======

Experiment to allow versioning/building a MODX site through Git.

The goal of Gitify is to provide a two-way sync of data typically stored in the MODX database with Git. To do this, it creates a representation of xPDOObject's in files. These files follow a certain a  human and machine friendly format with simple JSON at the top of the file, and the content (if there's any specific content field) below that. The configuration, which determines what data is written to file, is stored in a .gitify file in the project root.

## Creating a new Project

To create a new project, you can manually create a .gitify file, or run `php Gitify.php init [directory]` where `[directory]` is empty, or a directory relative to the position of the Gitify.php file. The .gitify file will be created in that directory. There needs to be config.core.php file in the same directory as the .gitify file, so either initialise Gitify in the root of a MODX site, or add the config.core.php file to point to a MODX core.

To do:

* The `php Gitify.php init` command will also need to offer to install a clean MODX install, to speed up along the process.
* Offer interactive shell options to tweak the generated .gitify file
* Make .gitify more powerful with more configuration, such as filters

## Load to File

When you added the .gitify file, you can tell Gitify to load the data to file. Simply run `php Gitify.php load [directory]` and you'll see the file representation of the various database objects.

To do:

* This process will need to be automatically executed when changes are made to resources, elements or other data that is written to file.
* Changes need to be automatically committed and, if a remote exists, pushed.
* Make sure nuking files and rebuilding them does not trigger changes to the file that could complicate git merges.

## Build to MODX

When you have the files, you can make edits and push them to a repository if you want, but with that you'll also need to be able of installing them in MODX sites. This is the build.

Building has not yet been added, but it will be along the lines of `php Gitify.php build [directory]`. That process will set up all the database records, updating changes, and clearing the cache. It will also try to handle possible ID issues for resources, where multiple resources exist with the same ID due to branch merges.


