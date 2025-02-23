<?php

namespace SlimApp\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use SlimApp\Objects\AbstractSession;



class CookieSessionMiddleware extends AbstractSessionMiddleware
// Session middleware that uses http cookie for storing session data.
{
    protected string $cookie_name;

    public function __construct(\Slim\App $app, string $session_object_class, array $ignored_paths=[], ...$other_params)
    {
        parent::__construct($app, $session_object_class, $ignored_paths, ...$other_params);
        assert(!empty($this->cookie_name), 'cookie name not set');
    }

    #[\Override]
    protected function get_session_content(Request $request): string|null
    // Get the content from the cookie.
    {
        return $_COOKIE[$this->cookie_name] ?? NULL;
    }


    #[\Override]
    protected function set_session_content(Response $response, AbstractSession $session): Response
    // Get the cookie for the response.
    {
        if ($session->refresh) {
            setcookie(
                $this->cookie_name, 
                $session->content_encode(), 
                [
                    'expires' => time() + $session->ttl, 
                    'path' => '/', 
                    'domain' => $_SERVER['HTTP_HOST'],
                    'secure' => TRUE, 
                    'httponly' => TRUE,
                    'samesite' => 'Strict',
                ]
            );
        }
        return $response;
    }

}
