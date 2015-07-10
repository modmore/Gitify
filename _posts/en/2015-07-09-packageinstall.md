---
layout: page
title: "package:install"
category: en/commands
lang: en
order: 6
---

# gitify package:install

Renamed from `Gitify install:package` in v0.8. Installs the last version of a MODX Package with the PackageName you specified, or all packages defined in your `.gitify` file when specifying the `--all` flag. 

````
Usage:
 package:install [--all] [-i|--interactive] [package_name]

Arguments:
 PackageName          The MODX Package to install. Installs the last stable release of the Package.

Options:
 --all                 When specified, all packages defined in the .gitify config will be installed.
 --interactive (-i)    When --all and --interactive are specified, all packages defined in the .gitify config will be installed interactively. Installing a single package is always done interactively. 
 --help (-h)           Display this help message.
 --verbose (-v|vv|vvv) Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug.
 --version (-V)        Display the Gitify version.

````