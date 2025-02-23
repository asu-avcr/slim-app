<?php

namespace MyApplication;


// import dependencies
require __DIR__ . '/../vendor/autoload.php';

// import all application components
require 'controllers/_index_.php';


use SlimApp\SlimApp;


class MyApp extends SlimApp
{
    // override class constant with actual location of the config file and html templates
    const CONFIG_FILE = __DIR__ . '/../conf/config.yaml';
    const TWIG_TEMPLATES_DIR = __DIR__ . '/views';


    protected function routes(\Slim\App $app)
    // Call `routes()` method of each application controller to set up routes.
    {
        // define application routes
        Controllers\HomeController::routes($app);
    }

}

// run the application
MyApp::create();

