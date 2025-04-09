<?php

namespace SlimApp\Services;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;



class DatabaseService extends AbstractService
// DatabaseService connects to a backing database (supports MySql trough mysqli driver) and
// provides methods to access the database using Doctrine\DBAL framework.
{
    const CONFIG_SCHEMA = 'schemas/database.json';

    protected $db;

    public function initialize() 
    {
        $connectionParams = [
            'driver' => $this->config->driver,
            'host' => $this->config->host,
            'dbname' => $this->config->dbname,
            'user' => $this->config->user,
            'password' => $this->config->password,
        ];

        if (($this->config->driver=='mysqli') && ($this->config->ssl)) {
            $connectionParams['driverOptions'] = ['flags' => MYSQLI_CLIENT_SSL];
        }

        $this->db = DriverManager::getConnection($connectionParams);
    }


    public function __get($property)
    // property getter
    {
        switch ($property) {
            case 'connection':
                return $this->db;
        }

        // if no match has been reached
        throw new \Exception(__CLASS__."::get: unknown property `$property`");
    }



}
