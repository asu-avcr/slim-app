<?php

namespace SlimApp\Objects;

use Opis\JsonSchema\{Validator,ValidationResult,Errors\ErrorFormatter};



class JsonObject implements \JsonSerializable 
// Base class for implementation of logical objects.
{

    protected const JSON_SCHEMA = NULL;
    // JSON schema definition (name of the file).


    public static function create_from_json(object $json): static 
    // Populate object's protected/public member fields from JSON data.
    // Uses ReflectionClass class to make a list of object's properties and assigns
    // the values from JSON data. Every object's property must be present in the 
    // JSON data; if the property is of a non-scaler type (object), a resursive call
    // to create_from_json() is done. JSON data is validated before conversion, if the schema 
    // is provided.
    {
        $obj = new static();

        // validate input json data against schema (if defined)
        if (static::JSON_SCHEMA !== NULL) {
            $validator = new Validator();
            $validation_result = $validator->validate(
                $json, 
                file_get_contents(static::JSON_SCHEMA)
            );
            if (!$validation_result->isValid()) {
                $error = (new ErrorFormatter())->formatFlat($validation_result->error());
                throw new \Exception(implode(';',$error));
            }
        }

        $reflect = new \ReflectionClass($obj);
        $props   = $reflect->getProperties(\ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PUBLIC);
        foreach ($props as $prop) {
            // make sure each object's property has a value in json data, unless it has a default value
            $key = $prop->getName();
            if (!property_exists($json,$key)) {
                if (isset($obj->{$key})) continue;
                throw new \Exception(static::class . ':: missing property ' . $key);
            }

            // set the property value
            // for non-scalar data types call create_from_json()
            // \DateTime fields are handled separately
            $rp = new \ReflectionProperty($obj, $key);
            $prop_type = (string) $rp->getType();
            if (class_exists($prop_type)) {
                if ($prop_type == 'DateTime') {
                    $obj->{$key} = \DateTime::createFromFormat('Y-m-d', $json->{$key})->setTime(0,0);
                } else {
                    $obj->{$key} = $prop_type::create_from_json($json->{$key});
                }
            } else {
                $obj->{$key} = $json->{$key};
            }
        }
        return $obj;
    }


    public function jsonSerialize(): mixed 
    // Serialize object data to JSON (implements \JsonSerializable).
    // Uses reflection class to compile all protected and public object members in a recursive way.
    // If self::JSON_SCHEMA is defined, validates the result agains the schema.
    {
        $serialize_data = [];

        $reflect = new \ReflectionClass($this);
        $props   = $reflect->getProperties(\ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PUBLIC);
        foreach ($props as $prop) {
            $key = $prop->getName();
            if  ($this->{$key} instanceof JsonSerializable)
                $value = $this->{$key}->jsonSerialize();
            else 
                $value = $this->{$key};
            $serialize_data[$key] = $value;
        }

        // validate input json data against schema (if defined)
        if (self::JSON_SCHEMA !== NULL) {
            $validator = new Validator();
            $validation_result = $validator->validate(
                $serialize_data, 
                file_get_contents(self::JSON_SCHEMA)
            );
            if (!$validation_result->isValid()) {
                $error = (new ErrorFormatter())->formatFlat($validation_result->error());
                throw new \Exception(implode(';',$error));
            }
        }
        
        return $serialize_data;
    }


    //-------------------------------------------
    // getters & setters
    //-------------------------------------------

    public function __isset($property)
    {
        return (property_exists($this,$property) && isset($this->{$property}));
    }


    public function __get($property)
    // property getter
    {
        if (property_exists($this,$property)) {
            return $this->{$property};
        } else {
            throw new \Exception(__CLASS__."::get: unknown property `$property`");
        }
    }

}
