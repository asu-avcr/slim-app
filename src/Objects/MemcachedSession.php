<?php

namespace SlimApp\Objects;

use SlimApp\Services\ConfigService;


class MemcachedSession extends AbstractSession
// Session object that uses memcached for storing the content.
{
    protected const USE_CONFIGURAION = FALSE;
    protected const CONFIG_SCHEMA = NULL;

    protected \Memcached $memcached;
    protected string $namespace;


    protected function initialize(?string $session_token, ConfigService $config)
    {
        $this->memcached = new \Memcached();
        $this->memcached->addServer(
            $config->cache->memcached_host, 
            $config->cache->memcached_port
        );

        $this->namespace = $config->cache->namespace . '/' . 'session';
    }


    protected function create_token(mixed $value): string
    {
        return uniqid(more_entropy:TRUE);
    }


    public function content_decode(string $session_token)
    {
        $this->token = $session_token;
        $value = $this->memcached->get($this->namespace.'/'.$this->token);

        $errno = $this->memcached->getResultCode();
        if (!in_array($errno, [\Memcached::RES_SUCCESS,\Memcached::RES_NOTFOUND]) ) throw new \Exception('cannot retrieve memcached session');

        $this->value = $value ? $value : NULL;
    }


    public function content_encode(): string
    {
        $this->memcached->set($this->namespace.'/'.$this->token, $this->value, $this->ttl ? time()+$this->ttl : 0);
        if ($this->memcached->getResultCode() != \Memcached::RES_SUCCESS) throw new \Exception('cannot store memcached session');

        return $this->token;
    }


    public function validate(): bool
    {
        return $this->token && $this->value;
    }


}


