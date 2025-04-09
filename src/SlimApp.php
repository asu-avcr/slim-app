<?php

namespace SlimApp;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Firebase\JWT\JWT;

use Middleware\BodyJsonParserMiddleware;

// load utility function as this is not done by autoloader
require_once __DIR__ . "/Utils/Utils.php";


abstract class SlimApp
// Abstract main application class.
{
    const CONFIG_FILE = NULL;
    const CONFIG_SCHEMA = NULL;
    const TWIG_TEMPLATES_DIR = NULL;
    const SERVICE_LOGGING_CLASS = '\SlimApp\Services\LoggingService';
    const SERVICE_MAIL_CLASS = '\SlimApp\Services\MailService';
    const SERVICE_CONFIG_CLASS = '\SlimApp\Services\ConfigService';
    const SERVICE_DB_CLASS = '\SlimApp\Services\DatabaseService';
    const SERVICE_LDAP_CLASS = '\SlimApp\Services\LdapService';
    const SERVICE_CACHE_CLASS = '\SlimApp\Services\CacheService';


    public static function create()
    // Create the application.
    {
        return new static();
    }


    public function __construct()
    // Set up the application.
    {
        // create container (using PHP-DI) and application
        $container = new Container();
        $application = AppFactory::createFromContainer($container);

        $this->setup_services($container);
        $this->setup_error_handler($application);
        $this->setup_twig($application);

        $this->routes($application);
        $this->middleware($application);

        $application->run();
    }


    protected function setup_services(Container $container)
    // Set up and integrate services.
    {
        // add config servics to the container
        $container->set('config', function () {
            return new (static::SERVICE_CONFIG_CLASS)(static::CONFIG_FILE, static::CONFIG_SCHEMA);
        });

        $config = $container->get('config');

        // add logging service to the container
        $container->set('log', function () use ($container, $config) {
            return new (static::SERVICE_LOGGING_CLASS)($config->logging ?? NULL);
        });

        // add mail service to the container
        if (isset($config->mail)) {
            $container->set('mail', function () use ($container, $config) {
                return new (static::SERVICE_MAIL_CLASS)($config->mail);
            });
        }

        // add database service to the container
        if (isset($config->database)) {
            $container->set('db', function () use ($container, $config) {
                return new (static::SERVICE_DB_CLASS)($config->database);
            });
        }

        // add ldap service to the container
        if (isset($config->ldap)) {
            $container->set('ldap', function () use ($container, $config) {
                return new (static::SERVICE_LDAP_CLASS)($config->ldap);
            });
        }

        // add cache service to the container
        if (isset($config->cache)) {
            $container->set('cache', function () use ($container, $config) {
                return new (static::SERVICE_CACHE_CLASS)($config->cache);
            });
        }

        // add additional application services to the container
        $services = $this->services();
        foreach ($services as $name=>$service) {
            $container->set($name, function () use ($container, $config, $service) {
                return new ($service)($container, $config);
            });
        }
    }


    protected function setup_error_handler(\Slim\App $app)
    // Set up and integrate Twig template engine.
    {
        $config = $app->getContainer()->get('config');
        $debug = $config->application->debug ?? FALSE;

        // add error handling middleware
        $app->addErrorMiddleware($debug, !$debug, TRUE);
    }


    protected function setup_twig(\Slim\App $app)
    // Set up and integrate Twig template engine.
    {
        if(!static::TWIG_TEMPLATES_DIR) return;
        
        $config = $app->getContainer()->get('config');
        $debug = $config->application->debug ?? FALSE;

        // create Twig template engine
        $twig = Twig::create(static::TWIG_TEMPLATES_DIR, ['cache'=>FALSE, 'debug'=>$debug]);

        // define application filters
        $filters = $this->twig_filters();

        // define application filters
        $functions = $this->twig_functions();

        // define translation filter
        $functions['i18n'] = function ($context, $string) {
            $lang = $context['lang'] ?? 'cz';
            $i18n = $context['i18n'] ?? []; 
            return $i18n[$string][$lang] ?? '[['.$string.']]';
        };

        // define variable dump filter
        $filters['dump'] = function ($value) {
            return print_r($value,TRUE);
        };

        // add filters to Twig
        foreach($filters as $name=>$function) {
            $fct = new \ReflectionFunction($function);
            $twig->getEnvironment()->addFilter(
                new \Twig\TwigFilter($name, $function, ['needs_context'=>$fct->getNumberOfRequiredParameters()==2])
            );
        }

        // add functions to Twig
        foreach($functions as $name=>$function) {
            $fct = new \ReflectionFunction($function);
            $twig->getEnvironment()->addFunction(
                new \Twig\TwigFunction($name, $function, ['needs_context'=>$fct->getNumberOfRequiredParameters()==2])
            );
        }

        // add Twig middleware
        $app->add(TwigMiddleware::create($app, $twig));
    }


    protected function services(): array
    // Add application services.
    {
        return [];
        // template: ['myservice' => MyService::class];
    }

    protected function twig_filters(): array
    // Define custom Twig processing filters.
    // (descendats may override)
    {
        return [];
    }

    protected function twig_functions(): array
    // Define custom Twig processing functions.
    // (descendats may override)
    {
        return [];
    }

    protected function routes(\Slim\App $app)
    // Define application routes.
    // (descendats may override)
    {
        $app->get('/', function ($request, $response) {
            return $response->getBody()->write('OK');
        });    
    }

    protected function middleware(\Slim\App $app)
    // Add application global middleware.
    {
        // do not add any default middleware
        // template: $app->add(Middleware\BodyJsonParserMiddleware::create($app));
    }


}

