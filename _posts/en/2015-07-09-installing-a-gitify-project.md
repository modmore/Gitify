---
layout: page
title: "Installing a Gitify project"
category: en/usage
lang: en
order: 1
---

# Installing a Gitify project

When you built a site with Gitify, and committed the data to a repository, you will at some point need to use the data to set up a copy of that site. This tutorial outlines that process. 

### Prerequisites

To install a gitify project you first have to install Gitify:

* [Install Gitify](/en/installation/installation.html)

After it has been installed globally we can start installing the project:

### Cloning and Configuring

First we clone the repository inside a new project folder:

```bash
git clone REPOSITORY-LINK PROJECT-NAME
```

Once your project has been cloned go into that projects directory. 
Now open `.gitify` and configure it to your liking. If you are using a custom repository like the one from ModMore you should edit the repository details to authenticate to that repository.

### Installing MODX

Now we are ready to tell gitify to install the latest MODX and all needed packages:

```bash
Gitify install:modx
```

After you've used this command Gitify will ask you some questions, if you didn't create a database yet you can put in the details of a MySQL user who has the rights to create databases.

The result should look like this:

```bash
Downloading MODX from http://modx.com/download/latest/...
################################################################### 100.0%
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
### Installing the packages

MODX has now been freshly installed and is now ready to have extras installed. But installing them one by one is going to be a hassle so we're just gonna execute this command:

```bash
Gitify install:package --all
```

This command installs all the packages defined in `.gitify`, the result should look something like this:

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

### Building the project

After all the packages are installed we are ready to complete the project installation with this command:

```bash
Gitify build --force
```

The result should look something like this, don't worry if it generates some errors about MySQL queries it should still work.

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

### Your project has now been installed!

### Permissions

If you are on a *nix machine and got issues with pages staying white it probably has to do with permissions. To fix this, please use these commands in the root of your project:

```bash
chmod 777 assets
chmod 777 -R assets/components core/cache core/components core/packages
```

If it gives you permission denied errors try to use `sudo` in front of every command.
