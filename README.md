

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
- [Application components](#application-components)


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



## Application components

### Config

SlimApp `ConfigService` provides configuration to your application. All application configuration setting are stored in `conf/config.yaml` (the location may be changed by your app). The configuration file has one mandatory section and may contain other sections based on the required components:
```yaml
application:
    debug: false
```
Put any application's own configuration options either to the `application` section of the config file or to a new separate sections. It is a good strategy to provide a JSON schema fot the application configuration. It will help to validate the config file and check if all required options have been given. Specify the config file location and schema when setting up the main application object:
```php
use SlimApp\SlimApp;
class MyApp extends SlimApp
{
    const CONFIG_FILE = __DIR__  .  '/../conf/config.yaml';
    const CONFIG_SCHEMA = __DIR__  .  '/../schemas/config.json';
}
```
The config service is available in every controler that derives from `AbstractController` and it can be user to access any configuration property as its object propetries:
```php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use SlimApp\Controllers\AbstractController;

class MyController extends AbstractController
{
    public function webpage(Request $request, Response $response, array $args): Response 
    {
        $option1 = $this->config->application->option1 ?? FALSE;
        $option2 = $this->config->custom_section->option2 ?? FALSE;
    }
}
```


### Cache

SlimApp uses [memcached](https://memcached.org/), a distributed memory object caching system, as the caching backend. Mamcached provides an in-memory key-value storage for small chunks of arbitrary data (strings, objects). 

The cache service only activates if a configuration is provided (`cache` section exists in the config file) and if it is required by a controller. You can access the cache service in any controller if that derives from `AbstractController` and if it has declared logging as a required container dependency (`const REQUIRED_CONTAINERS = ['cache', ...];`). 

```php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use SlimApp\Controllers\AbstractController;

class MyController extends AbstractController
{
    const REQUIRED_CONTAINERS = ['cache'];
    public function webpage(Request $request, Response $response, array $args): Response 
    {
        $this->cache->set('key', $value);
    }
}
```

At the same time, you need to provide a configuration for caching service in the application's config file.

```yaml
cache:
    memcached_host: localhost                 # memcached server
    memcached_port: 11211                     # memcached server port
    namespace: my-app                         # namespace for your application
```
The complete schema for cache configuration can be found in `schemas/cache.json`.

Note that memcached is a global and independent service that may serve more applications. It is important that you use a unique `namespace` to isolate the cached data of your application from data of other applications.

### Database

SlimApp uses [Doctrine Database Abstraction Layer](https://www.doctrine-project.org/projects/dbal.html), a powerful object-oriented database abstraction layer, as the database backend. DBAL can connect to various type of database engines and provides universal interface for SQL operations. 

The database service only activates if a configuration is provided (`database` section exists in the config file) and if it is required by a controller. You can access the cache service in any controller if that derives from `AbstractController` and if it has declared database as a required container dependency (`const REQUIRED_CONTAINERS = ['database', ...];`). 

```php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use SlimApp\Controllers\AbstractController;

class MyController extends AbstractController
{
    const REQUIRED_CONTAINERS = ['database'];
    public function webpage(Request $request, Response $response, array $args): Response 
    {
        $this->db->fetchAssociative('SELECT * FROM table WHERE id=?', [$id]);
    }
}
```

At the same time, you need to provide a configuration for database service in the application's config file.

```yaml
database:
    drive: mysql                              # database driver
    host: localhost                           # database server
    dbname: database                          # database name
    user: !string                             # user name
    password: !string                         # user password
    ssl: true                                 # use secure connection
```
The complete schema for cache configuration can be found in `schemas/database.json`.


### LDAP

SlimApp provides access to LDAP directory using native PHP [LDAP functions](https://www.php.net/manual/en/book.ldap.php). LDAP (the Lightweight Directory Access Protocol), and is a protocol used to access "Directory Servers" that tipically store information about users and their accress rights.

The LDAP service only activates if a configuration is provided (`ldap` section exists in the config file) and if it is required by a controller. You can access the cache service in any controller if that derives from `AbstractController` and if it has declared database as a required container dependency (`const REQUIRED_CONTAINERS = ['ldap', ...];`). 

```php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use SlimApp\Controllers\AbstractController;

class MyController extends AbstractController
{
    const REQUIRED_CONTAINERS = ['ldap'];
    public function webpage(Request $request, Response $response, array $args): Response 
    {
        $user = $this->ldap->authenticate($login, $password);
    }
}
```

At the same time, you need to provide a configuration for LDAP service in the application's config file.

```yaml
ldap:
    server_uri: ldaps://ldap.company.com:636  # LDAP server URI
    timeout: 5                                # connection timeout [seconds]
    base_dn: ou=People,dc=company,dc=com      # base DN
    filter_login: (uid=%s)                    # login filter (how to apply login name)
    field_mapping:                            # field mapping (optional)
        login: uid                            # app field: ldap field
        email: mail                           # app field: ldap field
        last_name: sn                         # app field: ldap field
        first_name: givenname                 # app field: ldap field
```
The complete schema for cache configuration can be found in `schemas/ldap.json`.


### Logging

SlimApp uses [[seldaek/monolog](https://github.com/Seldaek/monolog)] as the logging tool. Monolog provides a plug-and-use [PSR-3](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md) compatible logging solution that allows to send your logs to files, sockets, inboxes, databases and various web services. 

SlimApp has support for two log handlers:
- the local file log handler, which streams logs to local file system, based on the configured log level
- the mail log handler, which email log reports to given recipients

The logger only activates if a configuration is provided (`logging` section exists in the config file) and if it is required by a controller. You can access the logger in any controller if that derives from `AbstractController` and if it has declared logging as a required container dependency (`const REQUIRED_CONTAINERS = ['log', ...];`). 

```php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use SlimApp\Controllers\AbstractController;

class MyController extends AbstractController
{
    const REQUIRED_CONTAINERS = ['log'];
    public function webpage(Request $request, Response $response, array $args): Response 
    {
        $this->log->error('message');
    }
}
```

At the same time, you need to provide a configuration for logging service in the application's config file.

```yaml
logging:
    name: MY-APP                              # name of your application
    handlers: [mail,file]                     # which logging handlers to activate
    mail:                                     # config section for mail handler
        level: warning                        # minimum log level
        subject: !text                     # report subject (see description)
        to: !email                            # recipient email
        from: !email                          # sender email
    file:                                     # config section for file handler
        level: info                           # minimum log level
        path: /tmp/slimapp.log                # log file path
```
The complete schema for logging configuration can be found in `schemas/logging.json`.

For `mail` handler, `subject` may contain placeholders for `monolog` fields (see [LineFormatter](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Formatter/LineFormatter.php)), for example 
```yaml
subject: "Log Report %channel%:%level_name% - %message%"
```

## Licence

This project is released under MIT licence.

