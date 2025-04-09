<?php

namespace SlimApp\Objects;

use Opis\JsonSchema\Validator;
use Opis\JsonSchema\Errors\ErrorFormatter;
use SlimApp\Services\ConfigService;


abstract class AbstractSession
// Abstract session object.
{
    protected const USE_CONFIGURAION = TRUE;
    protected const CONFIG_SCHEMA = NULL;
    protected const DEFAULT_SESSION_AUTOREFRESH = FALSE;
    protected const DEFAULT_SESSION_TTL = 600;

    protected bool $refresh;                    // should the session refresh when sending response
    protected int $ttl;                         // lifetime of the session (expiration interval)
    protected ?string $token = NULL;            // session token (session identifier)
    protected ?array $value = NULL;             // session data

    final public function __construct(?string $session_token, ConfigService $config)
    {
        if (static::USE_CONFIGURAION) {
            if (!$config) throw new \RuntimeException('Configuration is required for '.static::class);
            $this->validate_config($config);
        }

        $this->refresh = $config->application->session->autorefresh ?? static::DEFAULT_SESSION_AUTOREFRESH;
        $this->ttl     = $config->application->session->ttl ?? static::DEFAULT_SESSION_TTL;

        $this->initialize($session_token, $config);

        if ($session_token) $this->content_decode($session_token);
    }


    protected abstract function initialize(?string $session_token, ConfigService $config);


    protected function validate_config(object $config)
    {
        $schema_path = realpath(__DIR__ . '/../../' . static::CONFIG_SCHEMA);
        if (!static::CONFIG_SCHEMA) throw new \RuntimeException('Configuration schema for '.static::class.' is not given.');
        if (!$schema_path || !is_file($schema_path)) throw new \RuntimeException('Configuration schema for '.static::class.' is missing.');

        $validator = new Validator();

        // validate configuration data against schema
        $validation_result = $validator->validate(
            $config, file_get_contents($schema_path)
        );
        if (!$validation_result->isValid()) {
            throw new \RuntimeException('Invalid config for '.static::class.': '.implode(';',(new ErrorFormatter())->formatFlat($validation_result->error())));
        }
    }


    public abstract function content_decode(string $session_token);
    // Obtain and decode the content of the session from the token.


    public abstract function content_encode(): string;
    // Encode the content of the session for the storage.


    public function validate(): bool
    // Validate the session based on content. 
    {
        return TRUE;
    }


    protected abstract function create_token(array $value): string|null;
    // Create session token (its identifier). 

    
    public function new(array $value) 
    // Start new session and assign a value to it.
    // Switch refresh flag on to make sure the session will be seto to the response.
    {   
        $this->refresh = TRUE;
        $this->token = $this->create_token($value);
        $this->value = $value;
    }

    public function __get($property) 
    {
        // first, eval generic properties
        switch ($property) {
            case 'null':  return is_null($this->token);
            case 'value':  return $this->value;
            case 'ttl':  return $this->ttl;
            case 'refresh':  return $this->refresh;
        }

        // second, eval $value fields if it is an array
        if (is_array($this->value) && array_key_exists($property,$this->value)) return $this->value[$property];
        
        // throw an error if property does not exist
        throw new \Exception('invalid property');
    }
    
}

