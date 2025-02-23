<?php

namespace SlimApp\Services;

use Opis\JsonSchema\{Validator,ValidationResult,Schema,Errors\ErrorFormatter};

use function SlimApp\Utils\json_array_to_object;


class ConfigService
// ConfigService loads a YAML configuration file, validates its content
// against both given application schema and an internal SlimApp schema 
// and sets the configuration file content as ConfigService class members.
// Eg. $config->{name of the property in the yaml file}
// Params:
//   $config_file - path to the configuration file (required)
//   $config_schema - path to the configuration file JSON schema (optional)
// Note: $config_schema is optional, it may be used to validate the application 
// configuration options. However, the config file is always validated for 
// the proper syntax of the SlimApp's services options (database connection, mailer), 
// if they are given
{
    const CONFIG_SCHEMA = 'schemas/config.json';

    public function __construct(string $config_file, ?string $application_config_schema) 
    {
        $slimapp_schema_path = realpath(__DIR__ . '/../../' . static::CONFIG_SCHEMA);
        if (!$slimapp_schema_path || !is_file($slimapp_schema_path)) {
            throw new \RuntimeException('Configuration schema for '.static::class.' is missing.');
        }

        if (!is_file($config_file)) {
            throw new \RuntimeException("Invalid config file - $config_file");
        }

        if (!is_null($application_config_schema) && !is_file($application_config_schema)) {
            throw new \RuntimeException("Invalid application config schema - $application_config_schema");
        }
        // load configuration data in object representation
        $config_data = (object)json_array_to_object(
            yaml_parse_file($config_file) ?? []  // parsed as PHP arrays
        );

        $validator = new Validator();

        // validate configuration data against SlimApp schema
        $validation_result = $validator->validate(
            $config_data, file_get_contents($slimapp_schema_path)
        );
        if (!$validation_result->isValid()) {
            throw new \RuntimeException('Invalid config for '.static::class.': '.implode(';',(new ErrorFormatter())->formatFlat($validation_result->error())));
        }

        // validate configuration data against application's schema
        if ($application_config_schema) {
            $validation_result = $validator->validate(
                $config_data, file_get_contents($application_config_schema)
            );
            if (!$validation_result->isValid()) {
                throw new \RuntimeException('Invalid config for '.static::class.': '.implode(';',(new ErrorFormatter())->formatFlat($validation_result->error())));
            }
        }

        // apply $config_data content to the object
        foreach (get_object_vars($config_data) as $key => $value) $this->{$key} = $value;

        // make shortcut for debug
        $this->debug = $this->application->debug ?? FALSE;
    }


    
}
