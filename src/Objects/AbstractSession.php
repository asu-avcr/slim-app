<?php

namespace SlimApp\Objects;

use SlimApp\Services\ConfigService;


abstract class AbstractSession
// Abstract session object.
{
    protected bool $refresh = FALSE;            // should the session refresh when sending response
    protected int $ttl = 300;                   // lifetime of the session (expiration interval)
    protected ?string $token = NULL;            // session token (session identifier)
    protected ?array $value = NULL;             // session data

    public function __construct(?string $session_token, ConfigService $config)
    {
        if ($session_token) $this->content_decode($session_token);
        $this->refresh = $config->application->session_autorefresh ?? FALSE;
        $this->ttl = $config->application->session_ttl ?? 300;
    }


    public abstract function content_decode(string $session_token);
    // Obtain and decode the content of the session from the storage.


    public abstract function content_encode(): string;
    // Encode the content of the session for the storage.


    public function validate(): bool
    // Validate the session based on content. 
    {
        return TRUE;
    }


    protected abstract function create_token(array $value): string;
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

