<?php

namespace SlimApp\Services;

use Opis\JsonSchema\{Validator,ValidationResult,Schema,Errors\ErrorFormatter};

require __DIR__.'/../Utils/Utils.php';
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
    public function __construct(?string $config_file, ?string $config_schema) 
    {
        // return empty object if no config file is given
        if (!$config_file) return;

        if (!is_file($config_file)) {
            throw new \Exception("Invalid config file - $config_file");
        }

        if (!is_null($config_schema) && !is_file($config_schema)) {
            throw new \Exception("Invalid config schema - $config_schema");
        }
        // load configuration data in object representation
        $config_data = (object)json_array_to_object(
            yaml_parse_file($config_file) ?? []  // parsed as PHP arrays
        );

        $validator = new Validator();

        // validate configuration data against SlimApp schema
        $validation_result = $validator->validate(
            $config_data,
            file_get_contents(__DIR__ . '/../../schemas/config.json')
        );
        if (!$validation_result->isValid()) {
            throw new \Exception(print_r((new ErrorFormatter())->formatOutput($validation_result->error(),'verbose'),TRUE));
        }

        // validate configuration data against application's schema
        if ($config_schema) {
            $validation_result = $validator->validate(
                $config_data,
                file_get_contents($config_schema)
            );
            if (!$validation_result->isValid()) {
                throw new \Exception(print_r((new ErrorFormatter())->formatOutput($validation_result->error(),'verbose'),TRUE));
            }
        }

        // apply $config_data content to the object
        foreach (get_object_vars($config_data) as $key => $value) $this->{$key} = $value;

        if (isset($this->debug)) {
            $this->admin_email = $this->debug->admin_email;
            $this->debug = $this->debug->debug ?? FALSE;
        } else {
            $this->debug = FALSE;
        }
    }


    
}
