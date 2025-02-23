<?php

namespace SlimApp\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use SlimApp\Objects\AbstractSession;



class AuthCookieSessionMiddleware extends CookieSessionMiddleware
// Authorization cookie-based session.
// This object overrides the response_invalid() method so it redirects unauthorized requests to
// a given redirect path (a login page typically). 
{
    protected string $redirect_path;


    protected function response_invalid(): Response
    {
        $response = $this->responseFactory->createResponse();
        if ($this->redirect_path) {
            // redirect to path with 302-found
            return $response
                ->withHeader('Location', $this->redirect_path)
                ->withStatus(302);
        } else {
            // return 401-unauthorized
            $response->getBody()->write('Unauthorized');
            return $response->withStatus(401);
        }
    }



}
