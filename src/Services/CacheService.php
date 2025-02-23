<?php

namespace SlimApp\Services;


const MEMCACHED_SUCCESS     = \Memcached::RES_SUCCESS;
const MEMCACHED_NOTFOUND    = \Memcached::RES_NOTFOUND;

class CacheService
{
    private static $memcached = NULL;
    private string $namespace = '';

    public function __construct(?object $cache_conf) 
    {
        if (!$cache_conf) return;

        $memcached_host = $cache_conf->memcached_host;
        $memcached_port = $cache_conf->memcached_port;
        $namespace = $cache_conf->namespace;

        if (!self::$memcached) {
            self::$memcached = new \Memcached();
            self::$memcached->addServer($memcached_host, $memcached_port);
        }

        if (!empty($namespace)) {
            $this->namespace = str_ends_with($namespace,'/') ? $namespace : $namespace.'/';
        }
    }


    //---------------------------------------------------------
    // private
    //---------------------------------------------------------

    private function check_result_code(array $allowed_result_codes): int
    {
        $result = self::$memcached->getResultCode();
        if (!in_array($result, $allowed_result_codes)) {
            // log error
        }
        return $result;
    }


    //---------------------------------------------------------
    // public
    //---------------------------------------------------------


    public function set(string $key, mixed $value, int $expiration = 0): bool
    {
        if (!self::$memcached) throw new \Exception("SlimApp\Services\CacheService has been used without configuration");

        self::$memcached->set($this->namespace . $key, $value, $expiration ? time()+$expiration : 0);
        $result = $this->check_result_code([MEMCACHED_SUCCESS]);
        return ($result == MEMCACHED_SUCCESS);
    }


    public function get(string $key, &$value): bool
    {
        if (!self::$memcached) throw \Exception("SlimApp\Services\CacheService has been used without configuration");

        $value = self::$memcached->get($this->namespace . $key);
        $result = $this->check_result_code([MEMCACHED_SUCCESS,MEMCACHED_NOTFOUND]);
        return ($result == MEMCACHED_SUCCESS);
    }


    public function delete(string $key): bool
    {
        if (!self::$memcached) throw new \Exception("SlimApp\Services\CacheService has been used without configuration");

        self::$memcached->delete($this->namespace . $key);
        $result = $this->check_result_code([MEMCACHED_SUCCESS]);
        return ($result == MEMCACHED_SUCCESS);
    }


    public function touch(string $key, int $expiration)
    {
        if (!self::$memcached) throw new \Exception("SlimApp\Services\CacheService has been used without configuration");

        self::$memcached->touch($this->namespace . $key, $expiration ? time()+$expiration : 0);
        $result = $this->check_result_code([MEMCACHED_SUCCESS,MEMCACHED_NOTFOUND]);
        return ($result == MEMCACHED_SUCCESS);
    }


    public function exists(string $key): bool
    {
        if (!self::$memcached) throw new \Exception("SlimApp\Services\CacheService has been used without configuration");

        self::$memcached->get($this->namespace . $key);
        $result = $this->check_result_code([MEMCACHED_SUCCESS,MEMCACHED_NOTFOUND]);
        return ($result == MEMCACHED_SUCCESS);
    }


}

