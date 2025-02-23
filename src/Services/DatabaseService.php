<?php

namespace SlimApp\Services;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;



class DatabaseService
// DatabaseService connects to a backing database (supports MySql trough mysqli driver) and
// provides methods to access the database using Doctrine\DBAL framework.
{

    protected $db;

    public function __construct(?object $database_conf) 
    {
        if (!$database_conf) return;
        
        $connectionParams = [
            'driver' => $database_conf->driver,
            'host' => $database_conf->host,
            'dbname' => $database_conf->dbname,
            'user' => $database_conf->user,
            'password' => $database_conf->password,
        ];

        if (($database_conf->driver=='mysqli') && ($database_conf->ssl)) {
            $connectionParams['driverOptions'] = ['flags' => MYSQLI_CLIENT_SSL];
        }

        $this->db = DriverManager::getConnection($connectionParams);
    }


}
