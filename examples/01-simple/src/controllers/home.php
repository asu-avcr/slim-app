<?php

namespace MyApplication\Controllers;


use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Views\Twig;
use Slim\Routing\RouteCollectorProxy;
use SlimApp\Controllers\AbstractController;


class HomeController extends AbstractController
{

    public static function routes(\Slim\App $app)
    // add routes handled by this controller
    {
        // home page
        $app->get('/', [static::class, 'homepage']);

        // example page
        $app->get('/example', [static::class, 'example_page']); 
    }



    public function homepage(Request $request, Response $response, array $args): Response
    {
        $twig = Twig::fromRequest($request);
        return $twig->render($response, 'homepage.twig.html');
    }



    public function example_page(Request $request, Response $response, array $args): Response
    {
        $twig = Twig::fromRequest($request);
        return $twig->render($response, 'example.twig.html', [
            'param1' => 'value1',
            'param2' => 'value2',
            'param3' => 'value3',
            'server_vars' => $_SERVER,
        ]);
    }

}
