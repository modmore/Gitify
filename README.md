Gitify
======

The goal of Gitify is to provide a **two-way sync** of data typically stored in the MODX database, making it versionable with Git. To do this, it creates a representation of MODX objects in files. These files follow a certain [human and machine friendly format](https://gist.github.com/Mark-H/5acafdc1c364f70fa4e7), built from a block of YAML, followed by a separator, and then the main content (if there's a specific content field) below that.

The project configuration, which determines what data is written to file and build to the database, is stored in a `.gitify` file in the project root.

## Upgrading to v2 (:warn: in development)

Gitify v2 brings updated dependencies, additional functionality, and easier installation/updates via Composer.

The data file structure is unchanged, so you can safely update to v2. 

1. To upgrade **with the intention of contributing to Gitify**, you can keep your exiting git installation. 
   1. Bring it up to date with the master branch (`git fetch origin && git reset --hard origin/master`, or `git fetch upstream && git reset --hard upstream/master`)
   2. Install updated dependencies (`composer install`)
   3. Update your `$PATH` to point to the `bin` directory. This may be in your `~/.bash_profile` or `~/.zshrc` file. 
2. To upgrade **simply to use Gitify**, it's recommended to remove the v1 git-based installation completely, and instead install Gitify globally with Composer as described in the installation section below.

**Important to know:**

- Gitify v2 is now compatible with Gitify Watch v2. Make sure you've upgraded to the latest version.
- The minimum PHP version has been increased to 7.2.5.
- Documentation has not yet been updated for v2. This will happen soon.
- `Gitify` has changed to `gitify` and is now in a /bin subdirectory.

## Installation

````bash 
composer global require modmore/gitify:^2
````

If that does not make `gitify` available on your path, add the output of `composer global config bin-dir --absolute` to your path (i.e. in the `~/.bash_profile` or `~/.zshrc` file on Mac/Linux).

To update, use `composer global update modmore/gitify`. 

Alternatively, you can install Gitify local to a project with `composer require modmore/gitify:^2`. In that case you'll need to use `vendor/bin/gitify` as the command. 

When installing an alpha/dev version, if you haven't modified your global composer config before, it's possible you'll 
get an error message pertaining to your minimum-stability setting. (Composer defaults to stable, and we want an unstable version!)
To fix this, you'll need to set your global minimum stability with the following command:
```
composer global config minimum-stability alpha
```

### Manual Installation

Use the manual installation to build from source, useful if you intend to help make Gitify better.

````bash
$ git clone https://github.com/modmore/Gitify.git Gitify
$ cd Gitify
$ composer install --no-dev
$ chmod +x bin/gitify
````

Please see [the Installation documentation](https://docs.modmore.com/en/Open_Source/Gitify/Installation/index.html) for more details.


## Documentation

[Check the modmore Gitify documentation!](https://docs.modmore.com/en/Open_Source/Gitify/index.html) It contains information about the available commands and the way you would go about setting up a suitable workflow.

Please feel free to contribute to the wiki by editing existing pages, or adding new pages with extra information not covered elsewhere yet.

## Changes & History

Gitify adheres to [semver](http://semver.org). As we are before v1 right now, expect breaking changes and refactorings before the API stabilises.

For changes, please see the commit log or the [Changelog](CHANGELOG.md).

## License

The MIT License (MIT)

Copyright (c) 2014-2015 modmore | More for MODX

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
