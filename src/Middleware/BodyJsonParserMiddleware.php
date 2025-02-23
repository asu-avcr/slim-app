<?php

namespace SlimApp\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;


class BodyJsonParserMiddleware implements MiddlewareInterface
// Global middleware to parse JSON POST data.
{
    protected object $container;
    protected ResponseFactory $responseFactory;


    public static function create(\Slim\App $app)
    {
        $controller = new static();
        $controller->container = $app->getContainer();
        $controller->responseFactory = $app->getResponseFactory();
        return $controller;
    }


    public function process(Request $request, RequestHandler $handler): Response
    // If Content-Type=application/json, convert the body content to JSON object.
    {
        $contentType = $request->getHeaderLine('Content-Type');

        if (strstr($contentType, 'application/json')) {
            $contents = json_decode(file_get_contents('php://input'), false);
            // note: $contents is a PHP object (stdClass)
            if (json_last_error() === JSON_ERROR_NONE) {
                $request = $request->withParsedBody($contents);
            }
        }

        return $handler->handle($request);
    }
}
