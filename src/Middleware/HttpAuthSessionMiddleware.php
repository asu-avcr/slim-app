<?php

namespace SlimApp\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Exception\HttpBadRequestException;
use SlimApp\Objects\AbstractSession;



class HttpAuthSessionMiddleware extends AbstractSessionMiddleware
// Session middleware that uses HTTP Authorization header for storing session data.
{
    protected function get_session_content(Request $request): string|null
    // Get the content from the cookie.
    {
        $auth = $request->getHeader('Authorization'); // should return array
        if (!is_array($auth) || (count($auth) > 1)) return new HttpBadRequestException($request, 'invalid auth header');
        return (count($auth) == 1) ? $auth[0] : NULL;
    }


    protected function set_session_content(Response $response, AbstractSession $session): Response
    {
        // nothing to do here
        return $response;
    }

}
