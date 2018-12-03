Gitify
======

The goal of Gitify is to provide a **two-way sync** of data typically stored in the MODX database, making it versionable with Git. To do this, it creates a representation of MODX objects in files. These files follow a certain [human and machine friendly format](https://gist.github.com/Mark-H/5acafdc1c364f70fa4e7), built from a block of YAML, followed by a separator, and then the main content (if there's a specific content field) below that.

The project configuration, which determines what data is written to file and build to the database, is stored in a `.gitify` file in the project root.

## Quick Installation

```` shell
$ git clone https://github.com/modmore/Gitify.git Gitify
$ cd Gitify
$ composer install --no-dev
$ chmod +x Gitify
````

Please see [the Installation wiki](https://github.com/modmore/Gitify/wiki/1.-Installation) for more details.


## Documentation

[Check the Wiki!](https://github.com/modmore/Gitify/wiki) It contains information about the available commands and the way you would go about setting up a suitable workflow.

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
