<?php

namespace SlimApp\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;


class NoCacheMiddleware implements MiddlewareInterface
// Global middleware to prevent response caching in browsers.
{

    public static function create(\Slim\App $app)
    {
        return new static();
    }


    public function process(Request $request, RequestHandler $handler): Response
    {
        // process the request first
        $response = $handler->handle($request);

        // set the response headers to prevent caching
        return $response
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0');
    }
}
