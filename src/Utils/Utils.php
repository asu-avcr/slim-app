<?php

namespace SlimApp\Utils;


function json_array_to_object(array $array) 
// Recursively convert json data from array representation to object (stdClass) representation.
{
    $resultObj = new \stdClass;
    $resultArr = array();
    $hasIntKeys = false;
    $hasStrKeys = false;
    foreach ($array as $k => $v) {
        if (!$hasIntKeys) {
            $hasIntKeys = is_int($k);
        }
        if (!$hasStrKeys) {
            $hasStrKeys = is_string($k);
        }
        if ($hasIntKeys && $hasStrKeys) {
            $e = new \Exception('json_array_to_object: both integer and string keys are present');
            $e->vars = ['level' => $array];
            throw $e;
        }
        if ($hasStrKeys) {
            $resultObj->{$k} = is_array($v) ? json_array_to_object($v) : $v;
        } else {
            $resultArr[$k] = is_array( $v ) ? json_array_to_object($v) : $v;
        }
    }
    return ($hasStrKeys) ? $resultObj : $resultArr;
} 


function urlsafe_b64encode(string $string) 
// URL-safe base64 string encoding compatible with python base64's urlsafe methods
// https://www.php.net/manual/en/function.base64-encode.php#63543
{
    $data = base64_encode($string);
    $data = str_replace(array('+','/','='),array('_','$',''),$data);
    return $data;
}

function urlsafe_b64decode(string $string) 
// URL-safe base64 string decoding compatible with python base64's urlsafe methods
// https://www.php.net/manual/en/function.base64-encode.php#63543
{
    $data = str_replace(array('_','$'),array('+','/'),$string);
    $mod4 = strlen($data) % 4;
    if ($mod4) $data .= substr('====', $mod4);
    return base64_decode($data);
}


function encrypt(string $data, string $key) 
// encrypts $data to an URL-compatible string representation
// https://stackoverflow.com/questions/9262109/simplest-two-way-encryption-using-php
{
    $iv_size = openssl_cipher_iv_length('aes-128-cbc');
    $iv = openssl_random_pseudo_bytes($iv_size);
    
    if (mb_strlen($key,'8bit') < $iv_size) {
        $key = str_pad($key, $iv_size);
    } else {
        $key = mb_substr($key, 0, $iv_size, '8bit');
    }
    
    //TODO: and safe crypto implemantation with consistency self-check for both encrypt and decrypt should be used, see link above
    
    $ciphertext = openssl_encrypt(
        $data, 
        'aes-128-cbc', $key,
        OPENSSL_RAW_DATA, $iv
    );

    return urlsafe_b64encode($iv.$ciphertext);
}


function decrypt(string $string, string $key) 
// encrypts $data to an URL-compatible string representation
// https://stackoverflow.com/questions/9262109/simplest-two-way-encryption-using-php
{
    $data = urlsafe_b64decode($string, true);
    if ($data === FALSE) throw new Exception('invalid data');

    //TODO: and safe crypto implemantation with consistency self-check for both encrypt and decrypt should be used, see link above

    $iv_size = openssl_cipher_iv_length('aes-128-cbc');
    $iv = mb_substr($data, 0, $iv_size, '8bit');
    $ciphertext = mb_substr($data, $iv_size, null, '8bit');

    if (mb_strlen($key,'8bit') < $iv_size) {
        $key = str_pad($key, $iv_size);
    } else {
        $key = mb_substr($key, 0, $iv_size, '8bit');
    }

    $plaintext = openssl_decrypt(
        $ciphertext, 'aes-128-cbc', $key,
        OPENSSL_RAW_DATA, $iv
    );
    if (!$plaintext) throw new \Exception('invalid data');

    return $plaintext;
}


function json_encrypt(array $data, string $key) 
// encrypts $data to an URL-compatible string representation
// https://stackoverflow.com/questions/9262109/simplest-two-way-encryption-using-php
{
    if (!is_array($data)) throw new Exception('invalid data');
    return encrypt(json_encode($data, JSON_NUMERIC_CHECK+JSON_UNESCAPED_UNICODE), $key);
}


function json_decrypt(string $string, string $key) 
// decrypts $string into JSON data
{
    return json_decode(decrypt($string, $key), TRUE);
}


function random_string(int $length = 10) 
// Generate a random string of the given length.
// https://stackoverflow.com/a/4356295
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }

    return $randomString;
}


function random_string_ci(int $length = 10, bool $capitalize=FALSE) 
// Generate a random case-insensitive string of the given length.
// https://stackoverflow.com/a/4356295
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
    $charactersLength = strlen($characters);
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }

    return $capitalize ? strtoupper($randomString) : $randomString;
}



function random_string_base32(int $length = 16)
// Generate a random base32-like string of a given length.
// Note: no padding characters are uses in the result.
// Base32-compatible alphabet is used (A-Z2-7) 
{
    $keys = array_merge(range('A','Z'), range(2,7)); // No padding char
    $string = '';
    for ($i = 0; $i < $length; $i++) $string .= $keys[random_int(0, 31)];
    return $string;
}


function base32_encode(string $string): string
// Encode string based on Base32 encoding.
{
    $BASE32_TABLE = 'abcdefghijklmnopqrstuvwxyz234567';
    $out = '';
    $i = $v = $bits = 0;  
    $str_len = strlen($string);
    while ($i < $str_len) {
        $v |= ord($string[$i++]) << $bits;
        $bits += 8;
        while ($bits >= 5) {
            $out .= $BASE32_TABLE[$v & 31];
            $bits -= 5;
            $v >>= 5;
        }
    }
    if ($bits > 0) {
        $out .= $BASE32_TABLE[$v & 31];    
    }
    return strtoupper($out);
 }