<?php

/**
 * Constants defining possible errors
 */
define('ERR_INTERNAL', 1);
define('ERR_NO_USER', 2);
define('ERR_WRONG_PW', 3);
define('ERR_AS_DATA_INCONSIST', 4);
define('ERR_AS_INTERNAL', 5);
define('ERR_AS_ATTRIBUTE', 6);

// not defined in earlier PHP versions
if (!defined('LDAP_OPT_DIAGNOSTIC_MESSAGE')) {
    define('LDAP_OPT_DIAGNOSTIC_MESSAGE', 0x0032);
}

/**
 * The LDAP class holds helper functions to access an LDAP database.
 *
 * @author Andreas Aakre Solberg, UNINETT AS. <andreas.solberg@uninett.no>
 * @author Anders Lund, UNINETT AS. <anders.lund@uninett.no>
 * @package SimpleSAMLphp
 */
class SimpleSAML_Auth_LDAP {


    /**
     * LDAP link identifier.
     *
     * @var resource
     */
    protected $ldap = null;

    /**
     * LDAP user: authz_id if SASL is in use, binding dn otherwise
     */
    protected $authz_id = null;

    /**
     * Timeout value, in seconds.
     *
     * @var int
     */
    protected $timeout = 0;

    /**
     * Private constructor restricts instantiation to getInstance().
     *
     * @param string $hostname
     * @param bool $enable_tls
     * @param bool $debug
     * @param int $timeout
     * @param int $port
     * @param bool $referrals
     */
    // TODO: Flesh out documentation
    public function __construct($hostname, $enable_tls = TRUE, $debug = FALSE, $timeout = 0, $port = 389, $referrals = TRUE) {

        // Debug
        SimpleSAML_Logger::debug('Library - LDAP __construct(): Setup LDAP with ' .
                        'host=\'' . $hostname .
                        '\', tls=' . var_export($enable_tls, true) .
                        ', debug=' . var_export($debug, true) .
                        ', timeout=' . var_export($timeout, true) .
                        ', referrals=' . var_export($referrals, true));

        /*
         * Set debug level before calling connect. Note that this passes
         * NULL to ldap_set_option, which is an undocumented feature.
         *
         * OpenLDAP 2.x.x or Netscape Directory SDK x.x needed for this option.
         */
        if ($debug && !ldap_set_option(NULL, LDAP_OPT_DEBUG_LEVEL, 7)) {
                SimpleSAML_Logger::warning('Library - LDAP __construct(): Unable to set debug level (LDAP_OPT_DEBUG_LEVEL) to 7');
        }

        /*
         * Prepare a connection for to this LDAP server. Note that this function
         * doesn't actually connect to the server.
         */
        $this->ldap = @ldap_connect($hostname, $port);
        if ($this->ldap === FALSE) {
            throw $this->makeException('Library - LDAP __construct(): Unable to connect to \'' . $hostname . '\'', ERR_INTERNAL);
        }

        // Enable LDAP protocol version 3
        if (!@ldap_set_option($this->ldap, LDAP_OPT_PROTOCOL_VERSION, 3)) {
            throw $this->makeException('Library - LDAP __construct(): Failed to set LDAP Protocol version (LDAP_OPT_PROTOCOL_VERSION) to 3', ERR_INTERNAL);
        }

        // Set referral option
        if (!@ldap_set_option($this->ldap, LDAP_OPT_REFERRALS, $referrals)) {
            throw $this->makeException('Library - LDAP __construct(): Failed to set LDAP Referrals (LDAP_OPT_REFERRALS) to '.$referrals, ERR_INTERNAL);
        }

        // Set timeouts, if supported
        // (OpenLDAP 2.x.x or Netscape Directory SDK x.x needed)
        $this->timeout = $timeout;
        if ($timeout > 0) {
            if (!@ldap_set_option($this->ldap, LDAP_OPT_NETWORK_TIMEOUT, $timeout)) {
                SimpleSAML_Logger::warning('Library - LDAP __construct(): Unable to set timeouts (LDAP_OPT_NETWORK_TIMEOUT) to ' . $timeout);
            }
            if (!@ldap_set_option($this->ldap, LDAP_OPT_TIMELIMIT, $timeout)) {
                SimpleSAML_Logger::warning('Library - LDAP __construct(): Unable to set timeouts (LDAP_OPT_TIMELIMIT) to ' . $timeout);
            }
        }

        // Enable TLS, if needed
        if (stripos($hostname, "ldaps:") === FALSE and $enable_tls) {
            if (!@ldap_start_tls($this->ldap)) {
                throw $this->makeException('Library - LDAP __construct(): Unable to force TLS', ERR_INTERNAL);
            }
        }
    }


    /**
     * Convenience method to create an LDAPException as well as log the
     * description.
     *
     * @param string $description
     * The exception's description
     * @return Exception
     */
    private function makeException($description, $type = NULL) {
        $errNo = 0x00;

        // Log LDAP code and description, if possible
        if (empty($this->ldap)) {
            SimpleSAML_Logger::error($description);
        } else {
            $errNo = @ldap_errno($this->ldap);
        }

        // Decide exception type and return
        if ($type) {
            if ($errNo !== 0) {
                // Only log real LDAP errors; not success
                SimpleSAML_Logger::error($description . '; cause: \'' . ldap_error($this->ldap) . '\' (0x' . dechex($errNo) . ')');
            } else {
                SimpleSAML_Logger::error($description);
            }

            switch ($type) {
                case ERR_INTERNAL:// 1 - ExInternal
                    return new SimpleSAML_Error_Exception($description, $errNo);
                case ERR_NO_USER:// 2 - ExUserNotFound
                    return new SimpleSAML_Error_UserNotFound($description, $errNo);
                case ERR_WRONG_PW:// 3 - ExInvalidCredential
                    return new SimpleSAML_Error_InvalidCredential($description, $errNo);
                case ERR_AS_DATA_INCONSIST:// 4 - ExAsDataInconsist
                    return new SimpleSAML_Error_AuthSource('ldap', $description);
                case ERR_AS_INTERNAL:// 5 - ExAsInternal
                    return new SimpleSAML_Error_AuthSource('ldap', $description);
            }
        } else {
            if ($errNo !== 0) {
                $description .= '; cause: \'' . ldap_error($this->ldap) . '\' (0x' . dechex($errNo) . ')';
                if (@ldap_get_option($this->ldap, LDAP_OPT_DIAGNOSTIC_MESSAGE, $extendedError) && !empty($extendedError)) {
                    $description .= '; additional: \'' . $extendedError . '\'';
                }
            }
            switch ($errNo) {
                case 0x20://LDAP_NO_SUCH_OBJECT
                    SimpleSAML_Logger::warning($description);
                    return new SimpleSAML_Error_UserNotFound($description, $errNo);
                case 0x31://LDAP_INVALID_CREDENTIALS
                    SimpleSAML_Logger::info($description);
                    return new SimpleSAML_Error_InvalidCredential($description, $errNo);
                case -1://NO_SERVER_CONNECTION
                    SimpleSAML_Logger::error($description);
                    return new SimpleSAML_Error_AuthSource('ldap', $description);
                default:
                    SimpleSAML_Logger::error($description);
                    return new SimpleSAML_Error_AuthSource('ldap', $description);
            }
        }
    }


    /**
     * Search for DN from a single base.
     *
     * @param string $base
     * Indication of root of subtree to search
     * @param string|array $attribute
     * The attribute name(s) to search for.
     * @param string $value
     * The attribute value to search for.
     * @return string
     * The DN of the resulting found element.
     * @throws SimpleSAML_Error_Exception if:
     * - Attribute parameter is wrong type
     * @throws SimpleSAML_Error_AuthSource if:
     * - Not able to connect to LDAP server
     * - False search result
     * - Count return false
     * - Searche found more than one result
     * - Failed to get first entry from result
     * - Failed to get DN for entry
     * @throws SimpleSAML_Error_UserNotFound if:
     * - Zero entries was found
     */
    private function search($base, $attribute, $value) {

        // Create the search filter
        $attribute = self::escape_filter_value($attribute, FALSE);
        $value = self::escape_filter_value($value);
        $filter = '';
        foreach ($attribute AS $attr) {
            $filter .= '(' . $attr . '=' . $value. ')';
        }
        $filter = '(|' . $filter . ')';

        // Search using generated filter
        SimpleSAML_Logger::debug('Library - LDAP search(): Searching base \'' . $base . '\' for \'' . $filter . '\'');
        // TODO: Should aliases be dereferenced?
        $result = @ldap_search($this->ldap, $base, $filter, array(), 0, 0, $this->timeout);
        if ($result === FALSE) {
            throw $this->makeException('Library - LDAP search(): Failed search on base \'' . $base . '\' for \'' . $filter . '\'');
        }

        // Sanity checks on search results
        $count = @ldap_count_entries($this->ldap, $result);
        if ($count === FALSE) {
            throw $this->makeException('Library - LDAP search(): Failed to get number of entries returned');
        } elseif ($count > 1) {
            // More than one entry is found. External error
            throw $this->makeException('Library - LDAP search(): Found ' . $count . ' entries searching base \'' . $base . '\' for \'' . $filter . '\'', ERR_AS_DATA_INCONSIST);
        } elseif ($count === 0) {
            // No entry is fond => wrong username is given (or not registered in the catalogue). User error
            throw $this->makeException('Library - LDAP search(): Found no entries searching base \'' . $base . '\' for \'' . $filter . '\'', ERR_NO_USER);
        }


        // Resolve the DN from the search result
        $entry = @ldap_first_entry($this->ldap, $result);
        if ($entry === FALSE) {
            throw $this->makeException('Library - LDAP search(): Unable to retrieve result after searching base \'' . $base . '\' for \'' . $filter . '\'');
        }
        $dn = @ldap_get_dn($this->ldap, $entry);
        if ($dn === FALSE) {
            throw $this->makeException('Library - LDAP search(): Unable to get DN after searching base \'' . $base . '\' for \'' . $filter . '\'');
        }
        // FIXME: Are we now sure, if no excepton has been thrown, that we are returning a DN?
        return $dn;
    }


    /**
     * Search for a DN.
     *
     * @param string|array $base
     * The base, or bases, which to search from.
     * @param string|array $attribute
     * The attribute name(s) searched for.
     * @param string $value
     * The attribute value searched for.
     * @param bool $allowZeroHits
     * Determines if the method will throw an exception if no hits are found.
     * Defaults to FALSE.
     * @return string
     * The DN of the matching element, if found. If no element was found and
     * $allowZeroHits is set to FALSE, an exception will be thrown; otherwise
     * NULL will be returned.
     * @throws SimpleSAML_Error_AuthSource if:
     * - LDAP search encounter some problems when searching cataloge
     * - Not able to connect to LDAP server
     * @throws SimpleSAML_Error_UserNotFound if:
     * - $allowZeroHits er TRUE and no result is found
     *
     */
    public function searchfordn($base, $attribute, $value, $allowZeroHits = FALSE) {

        // Traverse all search bases, returning DN if found
        $bases = SimpleSAML\Utils\Arrays::arrayize($base);
        $result = NULL;
        foreach ($bases AS $current) {
            try {
                // Single base search
                $result = $this->search($current, $attribute, $value);
                // We don't hawe to look any futher if user is found
                if (!empty($result)) {
                    return $result;
                }
                // If search failed, attempt the other base DNs
            } catch (SimpleSAML_Error_UserNotFound $e) {
                // Just continue searching
            }
        }
        // Decide what to do for zero entries
        SimpleSAML_Logger::debug('Library - LDAP searchfordn(): No entries found');
        if ($allowZeroHits) {
            // Zero hits allowed
            return NULL;
        } else {
            // Zero hits not allowed
            throw $this->makeException('Library - LDAP searchfordn(): LDAP search returned zero entries for filter \'(' .
                $attribute . ' = ' . $value . ')\' on base(s) \'(' . join(' & ', $bases) . ')\'', 2);
        }
    }


    /**
     * This method was created specifically for the ldap:AttributeAddUsersGroups->searchActiveDirectory()
     * method, but could be used for other LDAP search needs. It will search LDAP and return all the entries.
     *
     * @throws Exception
     * @param string|array $bases
     * @param string|array $filters Array of 'attribute' => 'values' to be combined into the filter, or a raw filter string
     * @param string|array $attributes Array of attributes requested from LDAP
     * @param bool $and If multiple filters defined, then either bind them with & or |
     * @param bool $escape Weather to escape the filter values or not
     * @return array
     */
    public function searchformultiple($bases, $filters, $attributes = array(), $and = TRUE, $escape = TRUE) {

        // Escape the filter values, if requested
        if ($escape) {
            $filters = $this->escape_filter_value($filters, FALSE);
        }

        // Build search filter
        $filter = '';
        if (is_array($filters)) {
            foreach ($filters as $attribute => $value) {
                $filter .= "($attribute=$value)";
            }
            if (count($filters) > 1) {
                $filter = ($and ? '(&' : '(|') . $filter . ')';
            }
        } elseif (is_string($filters)) {
            $filter = $filters;
        }

        // Verify filter was created
        if ($filter == '' || $filter == '(=)') {
            throw $this->makeException('ldap:LdapConnection->search_manual : No search filters defined', ERR_INTERNAL);
        }

        // Verify at least one base was passed
        $bases = (array) $bases;
        if (empty($bases)) {
            throw $this->makeException('ldap:LdapConnection->search_manual : No base DNs were passed', ERR_INTERNAL);
        }

        // Search each base until result is found
        $result = FALSE;
        foreach ($bases as $base) {
            $result = @ldap_search($this->ldap, $base, $filter, $attributes, 0, 0, $this->timeout);
            if ($result !== FALSE) {
            	break;
            }
        }

        // Verify that a result was found in one of the bases
        if ($result === FALSE) {
            throw $this->makeException(
                'ldap:LdapConnection->search_manual : Failed to search LDAP using base(s) [' .
                implode('; ', $bases) . '] with filter [' . $filter . ']. LDAP error [' .
                ldap_error($this->ldap) . ']'
            );
        } elseif (@ldap_count_entries($this->ldap, $result) < 1) {
            throw $this->makeException(
                'ldap:LdapConnection->search_manual : No entries found in LDAP using base(s) [' .
                implode('; ', $bases) . '] with filter [' . $filter . ']',
                ERR_NO_USER
            );
        }

        // Get all results
        $results = ldap_get_entries($this->ldap, $result);
        if ($results === FALSE) {
            throw $this->makeException(
                'ldap:LdapConnection->search_manual : Unable to retrieve entries from search results'
            );
        }

        // parse each entry and process its attributes
        for ($i = 0; $i < $results['count']; $i++) {
            $entry = $results[$i];

            // iterate over the attributes of the entry
            for ($j = 0; $j < $entry['count']; $j++) {
                $name = $entry[$j];
                $attribute = $entry[$name];

                // decide whether to base64 encode or not
                for ($k = 0; $k < $attribute['count']; $k++) {
                    // base64 encode binary attributes
                    if (strtolower($name) === 'jpegphoto' || strtolower($name) === 'objectguid') {
                        $results[$i][$name][$k] = base64_encode($attribute[$k]);
                    }
                }
            }
        }

        // Remove the count and return
        unset($results['count']);
        return $results;
    }


    /**
     * Bind to LDAP with a specific DN and password. Simple wrapper around
     * ldap_bind() with some additional logging.
     *
     * @param string $dn
     * The DN used.
     * @param string $password
     * The password used.
     * @param array $sasl_args
     * Array of SASL options for SASL bind
     * @return bool
     * Returns TRUE if successful, FALSE if
     * LDAP_INVALID_CREDENTIALS, LDAP_X_PROXY_AUTHZ_FAILURE,
     * LDAP_INAPPROPRIATE_AUTH, LDAP_INSUFFICIENT_ACCESS
     * @throws SimpleSAML_Error_Exception on other errors
     */
    public function bind($dn, $password, array $sasl_args = NULL) {
        $authz_id = null;

        if ($sasl_args != NULL) {
            if (!function_exists('ldap_sasl_bind')) {
                $ex_msg = 'Library - missing SASL support';
                throw $this->makeException($ex_msg);
            }

            // SASL Bind, with error handling
            $authz_id = $sasl_args['authz_id'];
            $error = @ldap_sasl_bind($this->ldap, $dn, $password,
                $sasl_args['mech'],
                $sasl_args['realm'],
                $sasl_args['authc_id'],
                $sasl_args['authz_id'],
                $sasl_args['props']);
        } else {
            // Simple Bind, with error handling
            $authz_id = $dn;
            $error = @ldap_bind($this->ldap, $dn, $password);
        }

        if ($error === TRUE) {
            // Good
            $this->authz_id = $authz_id;
            SimpleSAML_Logger::debug('Library - LDAP bind(): Bind successful with DN \'' . $dn . '\'');
            return TRUE;
        }

        /* Handle errors
         * LDAP_INVALID_CREDENTIALS
         * LDAP_INSUFFICIENT_ACCESS */
        switch(ldap_errno($this->ldap)) {
            case 32:	// LDAP_NO_SUCH_OBJECT
                // no break
            case 47:	// LDAP_X_PROXY_AUTHZ_FAILURE
                // no break
            case 48:	// LDAP_INAPPROPRIATE_AUTH
                // no break
            case 49:	// LDAP_INVALID_CREDENTIALS
                // no break
            case 50:	// LDAP_INSUFFICIENT_ACCESS
                return FALSE;
                break;
        default;
            break;
        }

        // Bad
        throw $this->makeException('Library - LDAP bind(): Bind failed with DN \'' . $dn . '\'');
    }


    /**
     * Applies an LDAP option to the current connection.
     *
     * @throws Exception
     * @param $option
     * @param $value
     * @return void
     */
    public function setOption($option, $value) {

        // Attempt to set the LDAP option
        if (!@ldap_set_option($this->ldap, $option, $value)) {
            throw $this->makeException(
	        'ldap:LdapConnection->setOption : Failed to set LDAP option [' .
                $option . '] with the value [' . $value . '] error: ' . ldap_error($this->ldap),
                ERR_INTERNAL
            );
        }

        // Log debug message
        SimpleSAML_Logger::debug(
            'ldap:LdapConnection->setOption : Set the LDAP option [' .
            $option . '] with the value [' . $value . ']'
        );
    }


    /**
     * Search a given DN for attributes, and return the resulting associative
     * array.
     *
     * @param string $dn
     * The DN of an element.
     * @param string|array $attributes
     * The names of the attribute(s) to retrieve. Defaults to NULL; that is,
     * all available attributes. Note that this is not very effective.
     * @param int $maxsize
     * The maximum size of any attribute's value(s). If exceeded, the attribute
     * will not be returned.
     * @return array
     * The array of attributes and their values.
     * @see http://no.php.net/manual/en/function.ldap-read.php
     */
    public function getAttributes($dn, $attributes = NULL, $maxsize = NULL) {

        // Preparations, including a pretty debug message...
        $description = 'all attributes';
        if (is_array($attributes)) {
            $description = '\'' . join(',', $attributes) . '\'';
        } else {
            // Get all attributes...
            // TODO: Verify that this originally was the intended behaviour. Could $attributes be a string?
            $attributes = array();
        }
        SimpleSAML_Logger::debug('Library - LDAP getAttributes(): Getting ' . $description . ' from DN \'' . $dn . '\'');

        // Attempt to get attributes
        // TODO: Should aliases be dereferenced?
        $result = @ldap_read($this->ldap, $dn, 'objectClass=*', $attributes, 0, 0, $this->timeout);
        if ($result === false) {
            throw $this->makeException('Library - LDAP getAttributes(): Failed to get attributes from DN \'' . $dn . '\'');
        }
        $entry = @ldap_first_entry($this->ldap, $result);
        if ($entry === false) {
            throw $this->makeException('Library - LDAP getAttributes(): Could not get first entry from DN \'' . $dn . '\'');
        }
        $attributes = @ldap_get_attributes($this->ldap, $entry);  // Recycling $attributes... Possibly bad practice.
        if ($attributes === false) {
            throw $this->makeException('Library - LDAP getAttributes(): Could not get attributes of first entry from DN \'' . $dn . '\'');
        }

        // Parsing each found attribute into our result set
        $result = array();  // Recycling $result... Possibly bad practice.
        for ($i = 0; $i < $attributes['count']; $i++) {

            // Ignore attributes that exceed the maximum allowed size
            $name = $attributes[$i];
            $attribute = $attributes[$name];

            // Deciding whether to base64 encode
            $values = array();
            for ($j = 0; $j < $attribute['count']; $j++) {
                $value = $attribute[$j];

                if (!empty($maxsize) && strlen($value) >= $maxsize) {
                    // Ignoring and warning
                    SimpleSAML_Logger::warning('Library - LDAP getAttributes(): Attribute \'' .
                        $name . '\' exceeded maximum allowed size by ' + ($maxsize - strlen($value)));
                    continue;
                }

                // Base64 encode binary attributes
                if (strtolower($name) === 'jpegphoto' || strtolower($name) === 'objectguid') {
                    $values[] = base64_encode($value);
                } else {
                    $values[] = $value;
                }

            }

            // Adding
            $result[$name] = $values;

        }

        // We're done
        SimpleSAML_Logger::debug('Library - LDAP getAttributes(): Found attributes \'(' . join(',', array_keys($result)) . ')\'');
        return $result;
    }


    /**
     * Enter description here...
     *
     * @param string $config
     * @param string $username
     * @param string $password
     * @return array|bool
     */
    // TODO: Documentation; only cleared up exception/log messages
    public function validate($config, $username, $password = null) {

        /* Escape any characters with a special meaning in LDAP. The following
         * characters have a special meaning (according to RFC 2253):
         * ',', '+', '"', '\', '<', '>', ';', '*'
         * These characters are escaped by prefixing them with '\'.
         */
        $username = addcslashes($username, ',+"\\<>;*');

        if (isset($config['priv_user_dn'])) {
            $this->bind($config['priv_user_dn'], $config['priv_user_pw']);
        }
        if (isset($config['dnpattern'])) {
            $dn = str_replace('%username%', $username, $config['dnpattern']);
        } else {
            $dn = $this->searchfordn($config['searchbase'], $config['searchattributes'], $username);
        }

        if ($password !== null) { // checking users credentials ... assuming below that she may read her own attributes ...
            // escape characters with a special meaning, also in the password
            $password = addcslashes($password, ',+"\\<>;*');
            if (!$this->bind($dn, $password)) {
                SimpleSAML_Logger::info('Library - LDAP validate(): Failed to authenticate \''. $username . '\' using DN \'' . $dn . '\'');
                return FALSE;
            }
        }

        /*
         * Retrieve attributes from LDAP
         */
        $attributes = $this->getAttributes($dn, $config['attributes']);
        return $attributes;

    }


    /**
     * Borrowed function from PEAR:LDAP.
     *
     * Escapes the given VALUES according to RFC 2254 so that they can be safely used in LDAP filters.
     *
     * Any control characters with an ACII code < 32 as well as the characters with special meaning in
     * LDAP filters "*", "(", ")", and "\" (the backslash) are converted into the representation of a
     * backslash followed by two hex digits representing the hexadecimal value of the character.
     *
     * @static
     * @param array $values Array of values to escape
     * @return array Array $values, but escaped
     */
    public static function escape_filter_value($values = array(), $singleValue = TRUE) {
        // Parameter validation
        if (!is_array($values)) {
            $values = array($values);
        }

        foreach ($values as $key => $val) {
            // Escaping of filter meta characters
            $val = str_replace('\\', '\5c', $val);
            $val = str_replace('*',  '\2a', $val);
            $val = str_replace('(',  '\28', $val);
            $val = str_replace(')',  '\29', $val);

            // ASCII < 32 escaping
            $val = self::asc2hex32($val);

            if (null === $val) {
                $val = '\0';  // apply escaped "null" if string is empty
            }

            $values[$key] = $val;
        }
        if ($singleValue) {
            return $values[0];
        }
        return $values;
    }


    /**
     * Borrowed function from PEAR:LDAP.
     *
     * Converts all ASCII chars < 32 to "\HEX"
     *
     * @param string $string String to convert
     *
     * @static
     * @return string
     */
    public static function asc2hex32($string) {
        for ($i = 0; $i < strlen($string); $i++) {
            $char = substr($string, $i, 1);
            if (ord($char) < 32) {
                $hex = dechex(ord($char));
                if (strlen($hex) == 1) {
                    $hex = '0'.$hex;
                }
                $string = str_replace($char, '\\'.$hex, $string);
            }
        }
        return $string;
    }

    /**
     * Convert SASL authz_id into a DN
     */
    private  function authzid_to_dn($searchBase, $searchAttributes, $authz_id) {
        if (preg_match("/^dn:/", $authz_id)) {
            return preg_replace("/^dn:/", "", $authz_id);
        }

        if (preg_match("/^u:/", $authz_id)) {
            return $this->searchfordn($searchBase, $searchAttributes,
                preg_replace("/^u:/", "", $authz_id));
        }
        return $authz_id;
    }

    /**
     * ldap_exop_whoami accessor, if available. Use requested authz_id
     * otherwise.
     *
     * ldap_exop_whoami is not yet included in PHP. For reference, the
     * feature request: http://bugs.php.net/bug.php?id=42060
     * And the patch against lastest PHP release:
     * http://cvsweb.netbsd.org/bsdweb.cgi/pkgsrc/databases/php-ldap/files/ldap-ctrl-exop.patch
     */
    public  function whoami($searchBase, $searchAttributes) {
        $authz_id = '';

        if (function_exists('ldap_exop_whoami')) {
            if (ldap_exop_whoami($this->ldap, $authz_id) !== true) {
                throw $this->getLDAPException('LDAP whoami exop failure');
            }
        } else {
            $authz_id = $this->authz_id;
        }

        $dn = $this->authzid_to_dn($searchBase, $searchAttributes, $authz_id);

        if (!isset($dn) || ($dn == '')) {
            throw $this->getLDAPException('Cannot figure userID');
        }

        return $dn;
    }

}
