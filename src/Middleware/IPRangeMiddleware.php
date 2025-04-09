<?php

namespace SlimApp\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Views\Twig;


class IPRangeMiddleware implements MiddlewareInterface
// Global middleware to allow access from defined network address ranges or addresses.
{
    protected ContainerInterface $container;
    protected ResponseFactory $responseFactory;


    public static function create(\Slim\App $app)
    {
        $controller = new static();
        $controller->container = $app->getContainer();
        $controller->responseFactory = $app->getResponseFactory();
        return $controller;
    }


    public function process(Request $request, RequestHandler $handler): Response
    {
        try {
            // check the application key
            $config = $this->container->get('config');
            $allowed_hosts = $config->application->allowed_hosts ?? [];
            if (!is_array($allowed_hosts)) {
                throw new \RuntimeException('invalid configuration: allowed_hosts');
            }

            // remote client IP address
            $client_ip = \PhpIP\IP::create($_SERVER['REMOTE_ADDR']);

            $request_allowed = ($_SERVER['SERVER_ADDR'] == $_SERVER['REMOTE_ADDR']); // allow localhost

            foreach ($allowed_hosts as $entry) {
                if ($request_allowed) break;

                $match = FALSE;
                if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $entry)) {
                    // entry is a single IP address
                    $match = ($entry == $_SERVER['REMOTE_ADDR']);
                } else
                if (preg_match('/\.\[a-z]{2,}$/', strtolower($entry))) {
                    // entry is a hostname
                    $entry_ip = gethostbyname($entry);
                    if ($entry_ip == $entry) continue;
                    $match = \PhpIP\IP::create(gethostbyname($entry))->matches($client_ip); // same IP
                } else 
                if (preg_match('/^\d+\.\d+\.\d+\.\d+\/\d+$/', $entry)) {
                    // entry is a ip range
                    $match = \PhpIP\IPBlock::create($entry)->containsIP($client_ip);
                } else 
                if ($entry == '*') {
                    // entry is a wild-card
                    $match = TRUE;
                }
                $request_allowed = $request_allowed || $match;
            }

            if ($request_allowed) {
                // process the request and exit function
                return $handler->handle($request);
            } else {
                // render error page
                $response = $this->responseFactory->createResponse();
                //$view = Twig::fromRequest($request);
                $twig = Twig::create(__DIR__ . '/../../templates');
                $response = $twig->render($response, 'http-errors/http-401-forbidden.twig.html', []);
                return $response->withStatus(401);
            }
   
        }
        catch (\Throwable $e) {
            // on any other exception, send a report to admin and return http-500 error response
            if ($this->container->has('log')) {
                $this->container->get('log')->critical("Exception in ".__METHOD__, ['allowed_hosts'=>$allowed_hosts], $e);
            }
            return $this->responseFactory->createResponse()->withStatus(500);
        }
    }
}
