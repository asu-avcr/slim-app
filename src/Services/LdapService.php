<?php

namespace SlimApp\Services;


class LDAPErrorException extends \Exception {
    public function __construct(string $message, ?array $params=NULL, bool $soft=FALSE, int $code=0, Throwable $previous=null) {
        if (!empty($params)) $message .= "(".json_encode($params).")";
        parent::__construct($message, $code, $previous);
        $this->soft = $soft;
        // log any non-soft LDAP error
        //if (!$this->soft) \ASIS\log_exception($this);
    }
}


class LdapService extends AbstractService
// DatabaseService connects to a backing database (supports MySql trough mysqli driver) and
// provides methis to store/retrieve information about orders, payments etc.
// Access to the database is abstracted with Doctrine\DBAL framework.
{
    const CONFIG_SCHEMA = 'schemas/ldap.json';

    const ERR_LDAP_INVALID_URI      = 'ldap_invalid_uri';
    const ERR_LDAP_UNAVALABLE       = 'ldap_unavailable';
    const ERR_LDAP_INVALID_SEARCH   = 'ldap_invalid_search';
    const ERR_LDAP_AMBIGUOUS_SEARCH = 'ldap_ambiguous_search';
    const ERR_LDAP_INVALID_CREDENTIALS = 'ldap_invalid_credentials';
    const ERR_LDAP_INVALID_FIELD    = 'ldap_invalid_field';

    // protected int $last_ldap_errno = 0;
    // protected string $last_ldap_error = '';

    public function initialize() 
    {
        // noop
    }


    //---------------------------------------------------------------------------
    // protected/private methods
    //---------------------------------------------------------------------------

    // protected function check_result(\LDAP\Connection $ldap_conn, mixed $result, bool $raise_exception=FALSE): bool
    // {
    //     if ($result === FALSE) {
    //         $this->last_ldap_errno = ldap_errno($ldap_conn);
    //         $this->last_ldap_error = ldap_error($ldap_conn);
    //         if ($raise_exception) throw new LDAPErrorException($this->last_ldap_error, params:['errno'=>$this->last_ldap_errno], soft:FALSE, code:$this->last_ldap_errno);
    //         return FALSE;
    //     } else {
    //         $this->last_ldap_errno = 0;
    //         $this->last_ldap_error = '';
    //         return TRUE;
    //     }
    // }


    protected function ldap_host_ping($host, $port, $timeout=2): bool
    {
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($socket) {
            @fclose($socket);   // explicitly close socket
            return TRUE;
        }
        return FALSE;
    }


    protected function ldap_connect(): \LDAP\Connection|null
    {
        $ldap_conn = NULL;
        $timeout = $this->config->timeout ?? 5;

        // parse URI to components
        $uri = parse_url($this->config->server_uri);
        $ldap_scheme = $uri['scheme'] ?? 'ldaps';
        $ldap_host   = $uri['host'];
        $ldap_port   = $uri['port'] ?? 636;

        // iterate over all host IP addresses
        if ($this->ldap_host_ping($ldap_host, $ldap_port, $timeout)) {
            $ldap_conn = @ldap_connect("{$ldap_scheme}://{$ldap_host}:{$ldap_port}");
            if (!$ldap_conn) throw new LDAPErrorException(self::ERR_LDAP_INVALID_URI, soft:FALSE);
            // set options for LDAP connection
            ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldap_conn, LDAP_OPT_NETWORK_TIMEOUT, $timeout);
            ldap_set_option($ldap_conn, LDAP_OPT_TIMELIMIT, $timeout);
        }

        if (!$ldap_conn) throw new LDAPErrorException(self::ERR_LDAP_UNAVALABLE, soft:FALSE);

        return $ldap_conn;
    }
        

    protected function ldap_disconnect(?\LDAP\Connection $ldap_conn) 
    {
        if ($ldap_conn) @ldap_close($ldap_conn);
    }


    protected function ldap_search(\LDAP\Connection $ldap_conn, string $base_dn, string $filter): array
    {
        assert($ldap_conn, 'ldap: no connection');

        // perform a single-level search on the given base_dn
        $search_result = @ldap_search($ldap_conn, $base_dn, $filter, timelimit:10);
        if (!$search_result) throw new LDAPErrorException(self::ERR_LDAP_INVALID_SEARCH, soft:FALSE, code:ldap_errno($ldap_conn));

        // obtain searched entries
        $raw_entries = @ldap_get_entries($ldap_conn, $search_result);
        if (!$raw_entries) throw new LDAPErrorException(self::ERR_LDAP_INVALID_SEARCH, soft:FALSE, code:ldap_errno($ldap_conn));

        // make an array of entries
        $entries = [];
        for ($i=0; $i<$raw_entries['count']; $i++) $entries[] = $raw_entries[$i];

        return $entries;
    }


    protected function ldap_bind(\LDAP\Connection $ldap_conn, string $dn, string $password, bool $throw=FALSE): bool
    {
        assert($ldap_conn, 'ldap: no connection');
        assert(!empty($password), 'ldap: no password');

        $res = @ldap_bind($ldap_conn, $dn, $password);

        if (!$res && $throw) throw new LDAPErrorException(self::ERR_LDAP_INVALID_CREDENTIALS, soft:FALSE, code:ldap_errno($ldap_conn)); 

        return $res;
    }


    protected function ldap_search_login(\LDAP\Connection $ldap_conn, string $login): array|false
    {
        assert($ldap_conn, 'ldap: no connection');

        $login = trim(strtolower($login));
        $filter = str_replace('%s', ldap_escape($login,flags:LDAP_ESCAPE_FILTER), $this->config->filter_login);

        $entries = $this->ldap_search($ldap_conn, $this->config->base_dn, $filter);

        // more then one result is a problem
        if (count($entries) > 1) {
            throw new LDAPErrorException(self::ERR_LDAP_AMBIGUOUS_SEARCH, ['login'=>$login,'filter'=>$filter], soft:FALSE, code:ldap_errno($ldap_conn));
        }

        // return single entry
        return (!empty($entries)) ? $entries[0] : FALSE;
    }


    protected function ldap_modify(\LDAP\Connection $ldap_conn, string $dn, array $entry): array
    {
        assert($ldap_conn, 'ldap: no connection');

        // verify that $dn matches exactly a single ldap entry
        $entries = $this->ldap_search($ldap_conn, $dn, '(objectClass=*)');
        if (!$entries || (count($entries) != 1)) {
            throw new LDAPErrorException(self::ERR_LDAP_INVALID_SEARCH, ['dn'=>$dn], soft:FALSE, code:ldap_errno($ldap_conn));
        }

        if (!@ldap_modify($ldap_conn, $dn, $entry)) {
            throw new LDAPErrorException(self::ERR_LDAP_UPDATE_FAILED, soft:FALSE, code:ldap_errno($ldap_conn));
        }

        // re-read the record 
        $entries = $this->ldap_search($ldap_conn, $dn, '(objectClass=*)');
        if (!$entries || (count($entries) != 1)) {
            throw new LDAPErrorException(self::ERR_LDAP_INVALID_SEARCH, ['dn'=>$dn], soft:FALSE, code:ldap_errno($ldap_conn));
        }

        // return updated entry
        return $entries[0];
    }


    protected function ldap_entry_to_json(array $ldap_entry): array
    {
        $result = [];
        foreach ($ldap_entry as $key=>$value) {
            // skip numerical keys
            if (is_numeric($key)) continue;
            // // convert camel-case to snake-case field name (complexFormField -> complex_form_field)
            // $key = ltrim(strtolower(preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '_$0', $key)), '_');
            // convert single-item array value to scalar
            if (is_object($value)) $value = (array) $value;
            if (is_array($value)) {
                unset($value['count']);
                if (count($value) == 1) $value = $value[0];
            }
            // add to result
            $result[$key] = $value;
        }

        // make field name mapping 
        foreach ($this->config->field_mapping??[] as $name=>$ldap_field) {
            $result[$name] = $ldap_entry[$ldap_field][0] ?? NULL;
        }

        // convert numeric string values to numbers 
        foreach ($result as $name=>$value) {
            if (is_numeric($value)) $result[$name] = +$value;
        }

        return $result;
    }


    protected function authenticate_return_info(\LDAP\Connection $ldap_conn, string $dn) : array|false
    {
        return $this->ldap_entry_to_json(
            $this->ldap_search($ldap_conn, $dn, '(objectClass=*)')[0]
        );
    }



    //---------------------------------------------------------------------------
    // public methods
    //---------------------------------------------------------------------------


    public function authenticate(string $login, #[\SensitiveParameter] string $password) : array|false
    // User authentication with LDAP.
    // Ask LDAP server to authenticate a user identified by his login and password.
    // On success, return user personal number (pid).
    // @param login        user login
    // @param password     user password
    // @param tfa          TOTP code (unused if 0)
    // @result User personal number or FALSE.
    {
        if (empty($login) || empty($password)) return FALSE;

        $ldap_conn = NULL;
        try {
            $ldap_conn = $this->ldap_connect();
            $entry = $this->ldap_search_login($ldap_conn, $login);
            if ($entry) {
                // verify credentials, return FALSE if not valid
                if (!$this->ldap_bind($ldap_conn, $entry['dn'], $password, FALSE)) {
                    return FALSE;
                };

                return $this->authenticate_return_info($ldap_conn, $entry['dn']);
            }
        } finally {
            $this->ldap_disconnect($ldap_conn);
        }

        // return false on invalid credentials
        return FALSE;
    }


    public function user_info(string $user_dn, #[\SensitiveParameter] ?string $password=NULL) : array|false
    {
        if (empty($user_dn)) return FALSE;

        $ldap_conn = NULL;
        try {
            $ldap_conn = $this->ldap_connect();

            // perform bind if password is given
            if ($password) {
                // verify credentials, return FALSE if not valid
                if (!$this->ldap_bind($ldap_conn, $user_dn, $password, FALSE)) {
                    return FALSE;
                };
            }

            $entries = $this->ldap_search($ldap_conn, $user_dn, '(objectClass=*)');
            if (!$entries || (count($entries) > 1)) {
                throw new LDAPErrorException(self::ERR_LDAP_INVALID_SEARCH, ['dn'=>$dn], soft:FALSE, code:ldap_errno($ldap_conn));
            }

            // return updated entry
            return $this->ldap_entry_to_json($entries[0]);
        } finally {
            $this->ldap_disconnect($ldap_conn);
        }

        // return false on invalid credentials
        return FALSE;
    }


    public function modify_attribute(string $user_dn, #[\SensitiveParameter] string $password, array $entry): array
    {
        $ldap_conn = NULL;
        try {
            $ldap_conn = $this->ldap_connect();
            $this->ldap_bind($ldap_conn, $user_dn, $password, TRUE);
            $entry = $this->ldap_modify($ldap_conn, $user_dn, $entry);
            return $this->ldap_entry_to_json($entry);
        } finally {
            $this->ldap_disconnect($ldap_conn);
        }
    }

}
