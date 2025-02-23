<?php

namespace SlimApp\Controllers;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Views\Twig;
use Opis\JsonSchema\{Validator,ValidationResult,Errors\ErrorFormatter};



abstract class AbstractController
// Abstract page controller object.
{
    const REQUIRED_CONTAINERS = ['log'];

    protected ContainerInterface $container;
    protected ?object $config;

    // constructor receives container instance
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->config = $container->get('config');

        // load required containers
        foreach (static::REQUIRED_CONTAINERS as $c) {
            if ($container->has($c))
                $this->{$c} = $container->get($c);
            else
                throw new \RuntimeException("Missing required container '{$c}' - did you provide configuration for it?");
        }
    }


    abstract public static function routes(\Slim\App $app);
    // definition of controller routes must be given by each descendant


    public static function cron(ContainerInterface $container)
    // Run cron tasks.
    {
        $obj = new static($container);
        //$obj->cron_task();
    }


    protected function browser_prefered_language(array $available_languages, string $http_accept_language, string $default_language, array $replacements=[]): string
    // Detect preffered browser language that matches the available set of localizatons.
    // https://gist.github.com/Xeoncross/dc2ebf017676ae946082
    // Params:
    //   $available_languages - set of supported languages (RFC 1766), eg. ['en','cs']
    //   $http_accept_language - the content of HTTP 'Accepl-Language' header, eg. 'en-US,en;q=0.9,zh-CN;q=0.8,zh;q=0.7'
    //   $default_language - default language to use if the is no match between $available_languages and $http_accept_language
    //   $replacements - optional mapping from RFC-1766, eg. $replacements=['cs'=>'cz'] replaces 'cs' by 'cz' in the result
    // Returns:
    //   a string with a language tag
    {
        $available_languages = array_flip($available_languages);
        $langs = array();
        preg_match_all('~([\w-]+)(?:[^,\d]+([\d.]+))?~', strtolower($http_accept_language), $matches, PREG_SET_ORDER);
        foreach($matches as $match) {
            list($a, $b) = explode('-', $match[1]) + array('', '');
            $value = isset($match[2]) ? (float) $match[2] : 1.0;
            if(isset($available_languages[$match[1]])) {
                $langs[$match[1]] = $value;
                continue;
            }
            if(isset($available_languages[$a])) {
                $langs[$a] = $value - 0.1;
            }
        }
        arsort($langs);
        $lang = key($langs) ?? $default_language;
        return $replacements[$lang] ?? $lang;
    }


    public function language_redirect(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    // Detect preffered browser language and redirect to the locale URI.
    // https://gist.github.com/Xeoncross/dc2ebf017676ae946082
    {
        echo 'eeeee';
        // detect language, change cs->cz
        $lang = 'en';//$this->browser_prefered_language(['cs','en'], $_SERVER["HTTP_ACCEPT_LANGUAGE"], 'en');
        if ($lang == 'cs') $lang = 'cz';

        // redirect to the login page
        return $response
            ->withHeader('Location', "/$lang")
            ->withStatus(302);  // 302-found
    }


    protected function validate_data(object $data, string $schema_file, &$errors = NULL): bool
    // Validate the data against the given schema.
    // $data: data object
    // $schema_file: a file containing json schema for the request (relative to schemas folder)
    // $errors: on output, any errors from the validator are stored in the given variable as an array
    {
        assert((bool)($data instanceof \stdClass), 'data shall be provided as PHP objects');

        $validator = new Validator();
        $validation_result = $validator->validate(
            $data, 
            file_get_contents($schema_file)
        );

        if (!$validation_result->isValid()) {
            $errors = (new ErrorFormatter())->format($validation_result->error());
        }

        return (bool) $validation_result->isValid();
    }


    protected function make_url(string $path)
    // Compile full URL from the given path.
    {
        if (!str_starts_with($path, '/')) $path = '/' . $path;
        return $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $path;
    }


    protected function report_exception(\Throwable $e)
    // Report exception by email.
    { 
        $this->log->error($e->getMessage(), ['class'=>static::class, 'exception'=>$e]);
    }


    protected function jwt_encode_data(array $data, string $secret): string
    // Create JWT token containing given data.
    {
        return JWT::encode($data, $secret, 'HS256');
    }


    protected function response_json(ResponseInterface $response, array $data): ResponseInterface
    // Respond with JSON data.
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }

    
    protected function response_400_invalid(ResponseInterface $response, ?Twig $view, \Exception $e, bool $report_error = FALSE): ResponseInterface
    // Respond with 400-invalid.
    // If $report_error is set, report the error by email.
    // If $view is set, render error webpage.
    {
        if ($report_error) $this->report_exception($e);
        if ($view) $view->render($response, 'http-errors/http-400-invalid.twig.html', []);
        return $response->withStatus(400);
    }


    protected function response_400_invalid_json(ResponseInterface $response, string $error, ?array $details=NULL): ResponseInterface
    // Respond with 400-invalid with application/json content type.
    // $error is a short string description of the error.
    // $details is an array with optional content.
    {
        $data = ['error' => $error];
        if ($details) $data['details'] = $details;

        $response->getBody()->write(
            json_encode($data)
        );

        return $response
            ->withHeader('Content-Type', 'aplication/json')
            ->withStatus(400);
    }


    protected function response_401_unauthorized(ResponseInterface $response): ResponseInterface
    // Respond with 401-unauthorized.
    // No page content is provided.
    {
        $response->getBody()->write('Unauthorized');
        return $response->withStatus(401);
    }


    protected function response_410_gone(ResponseInterface $response, ?Twig $view): ResponseInterface
    // Respond with 410-gone.
    // If $view is set, render error webpage.
    {
        if ($view) return $view->render($response, 'http-errors/http-410-gone.twig.html', []);
        return $response->withStatus(410);
    }


    protected function response_429_too_many(ResponseInterface $response, ?Twig $view): ResponseInterface
    // Respond with 410-gone.
    // If $view is set, render error webpage.
    {
        if ($view) return $view->render($response, 'http-errors/http-429-too-many-requests.twig.html', []);
        return $response->withStatus(429);
    }


    protected function response_500_server_error(ResponseInterface $response, ?Twig $view, \Throwable $e): ResponseInterface
    // Respond with 500-server-error and report the error by email.
    // If $view is set, render error webpage.
    {
        $this->report_exception($e);
        if ($view) {
            $view->render($response, 'http-errors/http-500-server-error.twig.html', [
                'debug' => $this->config->application->debug ?? FALSE,
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        } else {
            $debug_info = "\n\n" . ($this->config->application->debug??FALSE ? $e->getMessage() . "\n\n" . $e->getTraceAsString() : NULL);
            $response->getBody()->write('Internal Application Error' . $debug_info);
        }
        return $response->withStatus(500);
    }

}
