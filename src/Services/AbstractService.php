<?php

namespace SlimApp\Services;

use Opis\JsonSchema\Validatior;
use Opis\JsonSchema\ValidationResult;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\Errors\ErrorFormatter;


abstract class AbstractService
// Logging service based on Monolog library.
// It can do whatever Monolog can.
{
    protected const USE_CONFIGURAION = TRUE;
    protected const CONFIG_SCHEMA = NULL;

    protected object $config;

    public final function __construct(?object $config) 
    {
        if (static::USE_CONFIGURAION) {
            if (!$config) throw new \RuntimeException('Configuration is required for '.static::class);
            $this->validate_config($config);
        }

        $this->config = $config;

        $this->initialize();
    }

    protected function validate_config(object $config)
    {
        $schema_path = realpath(__DIR__ . '/../../' . static::CONFIG_SCHEMA);
        if (!static::CONFIG_SCHEMA) throw new \RuntimeException('Configuration schema for '.static::class.' is not given.');
        if (!$schema_path || !is_file($schema_path)) throw new \RuntimeException('Configuration schema for '.static::class.' is missing.');

        $validator = new \Opis\JsonSchema\Validator();

        // validate configuration data against schema
        $validation_result = $validator->validate(
            $config, file_get_contents($schema_path)
        );
        if (!$validation_result->isValid()) {
            throw new \RuntimeException('Invalid config for '.static::class.': '.implode(';',(new ErrorFormatter())->formatFlat($validation_result->error())));
        }
    }


    protected abstract function initialize();

}

