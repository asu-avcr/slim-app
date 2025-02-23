
# SlimApp

A web application boilerplate based on [Slim 4 PHP framework](https://www.slimframework.com/).

(Slim is a PHP micro framework that helps write web applications and APIs.)

This boilerplate helps with quickly setting up a ready-to-use application and includes some essential components:

* application configuration ([YAML](https://yaml.org/))
* logging (using [Monolog](https://github.com/Seldaek/monolog))
* database access (using [Doctrine DBAL](https://www.doctrine-project.org/projects/dbal.html))
* mailing (using [PHPMailer](https://github.com/PHPMailer/PHPMailer))
* authentification with optional two-factor [TOTP](https://en.wikipedia.org/wiki/Time-based_one-time_password) verification
* database and LDAP support as backends for authentification
* caching (using [Memcached](https://memcached.org/))
* html templating (using [TWIG](https://twig.symfony.com/) and [Bootstrap 5](https://getbootstrap.com/))
* multilanguage support

**Content:**
- [Installation and setup](#installation-and-basic-web-application-setup)


## Installation and basic web application setup

Here is a step-by-step guide to setup your own web application.

Use your command line and navigate to the root folder of your new project, make sure you have `[composer](https://getcomposer.org/)` installed on your system and run

```bash
$ composer require asu/slim-app
```

A new  composers's configuration file `composer.json` will be created, the application dependency will be written to it and all required package dependencies (including Slim 4 framework and other stuff) will be downloaded and stored in `vendor` folder.

**Optional**: Should you want to use a different folder for packages, you can add the following config option to `composer.json` (in this example the dependencies will go to `deps` folder)

```json
{
    "config": {
        "vendor-dir": "deps"
    }
}
```

Then remove the `vendor` folder and re-install the dependencies

``` bash
$ rm -rf vendor
$ composer install
```

Note that when you do this and if you use the provided examples, you will also need to change the symlinks to html templates and the location of `autoload.php` script in `src/app.php`.


### Bootstrap

To bootstrap a your new application, navigate to `vendor/asu/slim-app/examples` and choose one of the example application to start with. We will start with the most simple one. 

Copy the content of `vendor/asu/slim-app/examples/01-simple` to the root of your project

```bash
$ cp -r vendor/asu/slim-app/examples/01-simple/* .
```


#### Directory structure
```
+ PROJECT_DIR/
    + assets/                            # static assets directory
    + cache/                             # default cache directory
    + config/                            # config file direcotry
        - config.yaml                    # config file for your application
    + public/                            # web entry directory
    + src/                               # source code directory
        + controllers/                   # controller classes
        + middleware/                    # middleware classes
        + schemas/                       # schemas for configuration and web requests
        + services/                      # service classes
        + utils/                         # utilities
        + views/                         # web page templates
        - app.php                        # main application file (entry point)
    + vendor/                            # composer components directory
    - composer.json                      # composer config file
    - composer.lock                      # composer lock file
```

### Configuration file

The application configuration is stored in `conf/config.yaml`. Only a minimal configuration is required to get started
```yaml
application:
    admin_email: admin@domain.com
    debug: true # false for production
```
Configuration entries for other components are described bellow. This will do for now.

### Web Server setup

**Option 1. Use the PHP Built-In  web server (for development only)** 

Start a local webserver from the command-line using `php` providing e.g. `localhost:8000` as the address and port to bind to and `public` as the path to the server document root.

```bash
$ php -S localhost:8000 -t public/
```
Then you can navigate in your browser to [http://localhost:8000](http://localhost:8000).

**Option 2. Set up a dedicated web server (e.g. Apache)** 

Setting-up a virtual web server in Apache or Nginx is a more involved procedure which requires some experience. You can follow for example these guides:
1. [How to set up Apache virtual hosts on Ubuntu](https://www.digitalocean.com/community/tutorials/how-to-set-up-apache-virtual-hosts-on-ubuntu-20-04)
2. [How to set up Nginx virtual hosts with PHP on Ubuntu](https://www.theserverside.com/blog/Coffee-Talk-Java-News-Stories-and-Opinions/Nginx-PHP-FPM-config-example)

Here is the relevant part of a virtual host configuration for Apache 2:
```
<VirtualHost *:443>
    ServerName your-project.com
    <Directory /var/www/your-project>
        Options FollowSymLinks
        AllowOverride FileInfo
        Require all granted
    </Directory>
    DocumentRoot /var/www/your-project/public
    DirectoryIndex index.php
</VirtualHost>
```
Note: The web server shall publish the `/public` subfolder of the project.

### Running the app

At this point, your application should be ready and you can navigate your browser to its URL and see the homepage example.

Customization is possible in many ways. To start with, you can edit the html page templates: `src/views/homepage.twig.html` and `src/views/example.twig.html`, or you can try to add more routes and pages.

**Resources**:
1. [Slim 4 reference](https://www.slimframework.com/docs/v4/)
2. [Twig template engine](https://twig.symfony.com/)



## Licence

This project is released under MIT licence.

