<?php

namespace SlimApp\Objects;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use SlimApp\Services\ConfigService;


class JWTSession extends AbstractSession
// Session object based on JWT (JSON Web Token).
{
    protected const USE_CONFIGURAION = TRUE;
    protected const CONFIG_SCHEMA = 'schemas/jwt-session.json';
    protected const DEFAULT_JWT_ALGORITHM = 'HS512';
    protected const DEFAULT_SESSION_TTL = 120;

    protected string $jwt_secret_key;
    protected string $jwt_algorithm;


    public function initialize(?string $session_token, ConfigService $config)
    {
        $this->jwt_secret_key = $config->application->session->jwt_secret_key ?? '';
        $this->jwt_algorithm = $config->application->session->jwt_algorithm ?? static::DEFAULT_JWT_ALGORITHM;
        if (!$this->jwt_secret_key) throw \RuntimeError('Missing configuration option: application->session->secret_key');
    }


    protected function create_token(mixed $value): string|null
    {
        return NULL;
    }


    public function content_decode(string $session_token)
    // Obtain and decode the content of the session from the storage.
    {
        list($auth_method, $auth_token) = explode(' ', $session_token);

        if (($auth_method != 'Bearer') || empty($auth_token)) {
            throw new \Exception('invalid auth header');
        }

        list($headersB64, $payloadB64, $sig) = explode('.', $auth_token);
        $payload = json_decode(base64_decode($payloadB64), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('invalid auth header');
        }

        // decode $auth_token and check the signature
        try {
            // note: JWT::decode returns a PHP object (stdClass)
            $this->token = NULL;
            $this->value = (array)JWT::decode($auth_token, new Key($this->jwt_secret_key, $this->jwt_algorithm)); 
        } catch (\Exception $e) {
            throw new \Exception('invalid auth header');
        }
    }


    public function content_encode(): string
    {
        $content = array_merge($this->value??[], [
            'iat' => time(),
            'exp' => time() + $this->ttl,
        ]);
        return JWT::encode($content, $this->jwt_secret_key, $this->jwt_algorithm);
    }


    public function validate(): bool
    {
        return !empty($this->value) && ($this->value['exp']??0 > time());
    }


}


