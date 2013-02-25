Project setup
=============

Requirements
------------

- PHP >= 5.3.3
- PHP extensions :
    - SQLite3 or PDO_SQLite (for the profiler)
    - Optionaly :
        - [gd][1] (for simple icon creation)
        - [ffmpeg][2] (for video thumbnail creation)
- A global installation of [composer][3] (for the dependency management)
- A global installation of [less][4] as a [node][5] module (for the less files compilation)

Quick start
-----------

- Clone the project and its associated plugins with `git clone --recursive https://github.com/claroline/Claroline.git`
- Checkout the master branch of each plugin with `git submodule foreach git checkout master`
- Create an *app/config/local/parameters.yml* file according to *app/config/local/parameters.yml.dist*
  (currently only db settings are required)
- Use the automatic install script : `php app/dev/raw_install`
- Make the *app/cache*, *app/logs*, *app/config/local*, *files*, *test* and
  *src/core/Claroline/CoreBundle/Resources/public/css/themes* directories (and their children)
  writable from the command line and the webserver (for further explanation on common permissions
  issues and solutions with Symfony, read [this][6])
- Open your browser and go to *[site]/web/app.php* (prod) or *[site]/web/app_dev.php* (dev)

Quick update
------------

To update your installation to the last development state, use :

    git pull
    git submodule update --recursive

Then launch the installation script mentioned above : `php app/dev/raw_install`

***Warning*** : this is a quick dev tool, it will drop existing databases (both prod and test)
and create new ones.

Plugin installation
-------------------

You can install or uninstall a plugin with :

- `php app/console claroline:plugin:install [vendor] [bundle short name]`
- `php app/console claroline:plugin:uninstall [vendor] [bundle short name]`

A new plugin can be added to the module list with :

`git submodule add http://github.com/vendor/SomeBundle.git src/plugin/Vendor/SomeBundle`

Development tools
=================

Testing
-------

In order to run the test suite you must have [phpunit][7] installed on your system.

- Run the complete test suite with : `phpunit -c app`
- Run the tests for a single directory with : `phpunit -c app src/core/Claroline/CoreBundle`

Build / Static analysis
-----------------------

The *app/build/tools* directory gathers configuration files for various php tools (PHPMD,
PHPCS, Ant, etc.). You can install and use them locally (see their respective documentation
for usage) or visit our continuous integration server [here][8].

Miscellaneous
-------------

To have the core Less and TwigJs assets automatically processed and dumped when they have changed,
you can run the provided [watchr][9] script :

`watchr src/core/Claroline/CoreBundle/Resources/watchr/refresh_assets.rb`

Documentation
=============

For development documentation, see [Claroline/CoreBundle/Resources/doc/index.md][10].


[1]: http://www.php.net/manual/en/book.image.php
[2]: http://ffmpeg-php.sourceforge.net/
[3]: http://getcomposer.org/doc/00-intro.md
[4]: http://lesscss.org/#-server-side-usage
[5]: http://nodejs.org/
[6]: http://symfony.com/doc/current/book/installation.html#configuration-and-setup
[7]: http://www.phpunit.de/manual/current/en/index.html
[8]: http://dev.claroline.net:8080/job/Claronext/
[9]: https://github.com/mynyml/watchr
[10]: src/core/Claroline/CoreBundle/Resources/doc/index.md