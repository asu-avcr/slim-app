<?php

namespace SlimApp\Middleware;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Exception\HttpBadRequestException;
use SlimApp\Services\ConfigService;
use SlimApp\Objects\AbstractSession;


abstract class AbstractSessionMiddleware implements MiddlewareInterface
// Global abstract middleware to handle session management.
{
    const REQUEST_SESSION_ATTRIBUTE = 'session';

    protected object $container;
    protected ResponseFactory $responseFactory;
    protected string $session_object_class;
    protected array $ignored_paths = [];

    public function __construct(\Slim\App $app, string $session_object_class, array $ignored_paths=[], ...$other_params)
    // Params:
    //   $session_object_class - class name of the session object to use
    //   $ignored_paths - list of route paths that shall be ignored by the middleware processing
    //   $other_params - any other parameters needed by descendants
    {
        $this->container = $app->getContainer();
        $this->responseFactory = $app->getResponseFactory();
        $this->session_object_class = $session_object_class;
        $this->ignored_paths = array_map('strtolower', $ignored_paths);
        foreach ($other_params as $key=>$arg) $this->{$key} = $arg;
    }


    public static function create(\Slim\App $app, string $session_object_class, array $ignored_paths=[], ...$other_params)
    // Static create function.
    {
        return new static($app, $session_object_class, $ignored_paths, ...$other_params);
    }


    public function process(Request $request, RequestHandler $handler): Response
    // Process requests.
    {
        $ignore = FALSE;
        $route_path = strtolower($request->getUri()->getPath());
        foreach ($this->ignored_paths as $ignpath) {
            $ignore = $ignore || fnmatch($ignpath, $route_path);
        }

        try {
            // get cookie content
            $session_token = $this->get_session_content($request);

            // create session object
            try {
                $session = !empty($session_token) && !$ignore 
                    ? new ($this->session_object_class)($session_token, $this->container->get('config'))
                    : new ($this->session_object_class)(NULL, $this->container->get('config'));
            } catch (\Exception $e) {
                throw new HttpBadRequestException($request, 'invalid session token');
            }

            assert($session instanceof AbstractSession, 'invalid session class');

            // validate the session 
            // - if OK (or if the path shall be ignored), pass the request processing to the next handler
            // . if not OK, return invalid response 
            if ($ignore || $session->validate()) {
                // assign auth_token to the request as an attribute and
                // invoke the next middleware in the row to get a response
                $response = $handler->handle(
                    $request->withAttribute(static::REQUEST_SESSION_ATTRIBUTE, $session)
                );
                
                // after the request has beed processed, set the session to the response object
                if (!$session->null) $response = $this->set_session_content($response, $session);
            } else {
                $response = $this->response_invalid();
            }

            return $response;
        }
        catch (HttpBadRequestException $e) {
            // return http-401 (unauthorized) error response
            $response = $this->responseFactory->createResponse();
            $response->getBody()->write('Bad Request - ' . $e->getMessage());
            return $response->withStatus(400);
        }
        catch (\Throwable $e) {
            // on any other exception, send a report to admin and return http-500 error response
            $log = $this->container->get('log');
            if ($log) $log->error($e->getMessage(), ['class'=>static::class, 'exception'=>$e]);
            return $this->responseFactory->createResponse()->withStatus(500);
        }
    }


    protected abstract function get_session_content(Request $request): string|null;
    // Get session content from the request.


    protected abstract function set_session_content(Response $response, AbstractSession $session): Response;
    // Get session content for the response.


    protected function response_invalid(): Response
    {
        // return 400-bad request
        $response = $this->responseFactory->createResponse();
        $response->getBody()->write('Bad Request - Invalid session');
        return $response->withStatus(400);
    }


}
