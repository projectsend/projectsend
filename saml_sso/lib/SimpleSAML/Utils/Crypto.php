<?php
namespace SimpleSAML\Utils;


/**
 * A class for cryptography-related functions
 *
 * @package SimpleSAMLphp
 */
class Crypto
{

    /**
     * Decrypt data using AES-256-CBC and the key provided as a parameter.
     *
     * @param string $ciphertext The HMAC of the encrypted data, the IV used and the encrypted data, concatenated.
     * @param string $secret The secret to use to decrypt the data.
     *
     * @return string The decrypted data.
     * @htorws \InvalidArgumentException If $ciphertext is not a string.
     * @throws \SimpleSAML_Error_Exception If the openssl module is not loaded.
     *
     * @see \SimpleSAML\Utils\Crypto::aesDecrypt()
     */
    private static function _aesDecrypt($ciphertext, $secret)
    {
        if (!is_string($ciphertext) || mb_strlen($ciphertext, '8bit') < 48) {
            throw new \InvalidArgumentException(
                'Input parameter "$ciphertext" must be a string with more than 48 characters.'
            );
        }
        if (!function_exists("openssl_decrypt")) {
            throw new \SimpleSAML_Error_Exception("The openssl PHP module is not loaded.");
        }

        // derive encryption and authentication keys from the secret
        $key  = openssl_digest($secret, 'sha512');

        $hmac = mb_substr($ciphertext, 0, 32, '8bit');
        $iv   = mb_substr($ciphertext, 32, 16, '8bit');
        $msg  = mb_substr($ciphertext, 48, mb_strlen($ciphertext, '8bit') - 48, '8bit');

        // authenticate the ciphertext
        if (self::secureCompare(hash_hmac('sha256', $iv.$msg, substr($key, 64, 64), true), $hmac)) {
            $plaintext = openssl_decrypt(
                $msg,
                'AES-256-CBC',
                substr($key, 0, 64),
                defined('OPENSSL_RAW_DATA') ? OPENSSL_RAW_DATA : true,
                $iv
            );

            if ($plaintext != false) {
                return $plaintext;
            }
        }

        throw new \SimpleSAML_Error_Exception("Failed to decrypt ciphertext.");
    }


    /**
     * Decrypt data using AES-256-CBC and the system-wide secret salt as key.
     *
     * @param string $ciphertext The HMAC of the encrypted data, the IV used and the encrypted data, concatenated.
     *
     * @return string The decrypted data.
     * @htorws \InvalidArgumentException If $ciphertext is not a string.
     * @throws \SimpleSAML_Error_Exception If the openssl module is not loaded.
     *
     * @author Andreas Solberg, UNINETT AS <andreas.solberg@uninett.no>
     * @author Jaime Perez, UNINETT AS <jaime.perez@uninett.no>
     */
    public static function aesDecrypt($ciphertext)
    {
        return self::_aesDecrypt($ciphertext, Config::getSecretSalt());
    }


    /**
     * Encrypt data using AES-256-CBC and the key provided as a parameter.
     *
     * @param string $data The data to encrypt.
     * @param string $secret The secret to use to encrypt the data.
     *
     * @return string An HMAC of the encrypted data, the IV and the encrypted data, concatenated.
     * @throws \InvalidArgumentException If $data is not a string.
     * @throws \SimpleSAML_Error_Exception If the openssl module is not loaded.
     *
     * @see \SimpleSAML\Utils\Crypto::aesEncrypt()
     */
    private static function _aesEncrypt($data, $secret)
    {
        if (!is_string($data)) {
            throw new \InvalidArgumentException('Input parameter "$data" must be a string.');
        }

        if (!function_exists("openssl_encrypt")) {
            throw new \SimpleSAML_Error_Exception('The openssl PHP module is not loaded.');
        }

        // derive encryption and authentication keys from the secret
        $key = openssl_digest($secret, 'sha512');

        // generate a random IV
        $iv = openssl_random_pseudo_bytes(16);

        // encrypt the message
        $ciphertext = $iv.openssl_encrypt(
            $data,
            'AES-256-CBC',
            substr($key, 0, 64),
            defined('OPENSSL_RAW_DATA') ? OPENSSL_RAW_DATA : true,
            $iv
        );

        if ($ciphertext === false) {
            throw new \SimpleSAML_Error_Exception("Failed to encrypt plaintext.");
        }

        // return the ciphertext with proper authentication
        return hash_hmac('sha256', $ciphertext, substr($key, 64, 64), true).$ciphertext;
    }


    /**
     * Encrypt data using AES-256-CBC and the system-wide secret salt as key.
     *
     * @param string $data The data to encrypt.
     *
     * @return string An HMAC of the encrypted data, the IV and the encrypted data, concatenated.
     * @throws \InvalidArgumentException If $data is not a string.
     * @throws \SimpleSAML_Error_Exception If the openssl module is not loaded.
     *
     * @author Andreas Solberg, UNINETT AS <andreas.solberg@uninett.no>
     * @author Jaime Perez, UNINETT AS <jaime.perez@uninett.no>
     */
    public static function aesEncrypt($data)
    {
        return self::_aesEncrypt($data, Config::getSecretSalt());
    }


    /**
     * Load a private key from metadata.
     *
     * This function loads a private key from a metadata array. It looks for the following elements:
     * - 'privatekey': Name of a private key file in the cert-directory.
     * - 'privatekey_pass': Password for the private key.
     *
     * It returns and array with the following elements:
     * - 'PEM': Data for the private key, in PEM-format.
     * - 'password': Password for the private key.
     *
     * @param \SimpleSAML_Configuration $metadata The metadata array the private key should be loaded from.
     * @param bool                      $required Whether the private key is required. If this is true, a
     * missing key will cause an exception. Defaults to false.
     * @param string                    $prefix The prefix which should be used when reading from the metadata
     * array. Defaults to ''.
     *
     * @return array|NULL Extracted private key, or NULL if no private key is present.
     * @throws \InvalidArgumentException If $required is not boolean or $prefix is not a string.
     * @throws \SimpleSAML_Error_Exception If no private key is found in the metadata, or it was not possible to load
     *     it.
     *
     * @author Andreas Solberg, UNINETT AS <andreas.solberg@uninett.no>
     * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
     */
    public static function loadPrivateKey(\SimpleSAML_Configuration $metadata, $required = false, $prefix = '')
    {
        if (!is_bool($required) || !is_string($prefix)) {
            throw new \InvalidArgumentException('Invalid input parameters.');
        }

        $file = $metadata->getString($prefix.'privatekey', null);
        if ($file === null) {
            // no private key found
            if ($required) {
                throw new \SimpleSAML_Error_Exception('No private key found in metadata.');
            } else {
                return null;
            }
        }

        $file = Config::getCertPath($file);
        $data = @file_get_contents($file);
        if ($data === false) {
            throw new \SimpleSAML_Error_Exception('Unable to load private key from file "'.$file.'"');
        }

        $ret = array(
            'PEM' => $data,
        );

        if ($metadata->hasValue($prefix.'privatekey_pass')) {
            $ret['password'] = $metadata->getString($prefix.'privatekey_pass');
        }

        return $ret;
    }


    /**
     * Get public key or certificate from metadata.
     *
     * This function implements a function to retrieve the public key or certificate from a metadata array.
     *
     * It will search for the following elements in the metadata:
     * - 'certData': The certificate as a base64-encoded string.
     * - 'certificate': A file with a certificate or public key in PEM-format.
     * - 'certFingerprint': The fingerprint of the certificate. Can be a single fingerprint, or an array of multiple
     * valid fingerprints.
     *
     * This function will return an array with these elements:
     * - 'PEM': The public key/certificate in PEM-encoding.
     * - 'certData': The certificate data, base64 encoded, on a single line. (Only present if this is a certificate.)
     * - 'certFingerprint': Array of valid certificate fingerprints. (Only present if this is a certificate.)
     *
     * @param \SimpleSAML_Configuration $metadata The metadata.
     * @param bool                      $required Whether the private key is required. If this is TRUE, a missing key
     *     will cause an exception. Default is FALSE.
     * @param string                    $prefix The prefix which should be used when reading from the metadata array.
     *     Defaults to ''.
     *
     * @return array|NULL Public key or certificate data, or NULL if no public key or certificate was found.
     * @throws \InvalidArgumentException If $metadata is not an instance of \SimpleSAML_Configuration, $required is not
     *     boolean or $prefix is not a string.
     * @throws \SimpleSAML_Error_Exception If no private key is found in the metadata, or it was not possible to load
     *     it.
     *
     * @author Andreas Solberg, UNINETT AS <andreas.solberg@uninett.no>
     * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
     * @author Lasse Birnbaum Jensen
     */
    public static function loadPublicKey(\SimpleSAML_Configuration $metadata, $required = false, $prefix = '')
    {
        if (!is_bool($required) || !is_string($prefix)) {
            throw new \InvalidArgumentException('Invalid input parameters.');
        }

        $keys = $metadata->getPublicKeys(null, false, $prefix);
        if ($keys !== null) {
            foreach ($keys as $key) {
                if ($key['type'] !== 'X509Certificate') {
                    continue;
                }
                if ($key['signing'] !== true) {
                    continue;
                }
                $certData = $key['X509Certificate'];
                $pem = "-----BEGIN CERTIFICATE-----\n".
                    chunk_split($certData, 64).
                    "-----END CERTIFICATE-----\n";
                $certFingerprint = strtolower(sha1(base64_decode($certData)));

                return array(
                    'certData'        => $certData,
                    'PEM'             => $pem,
                    'certFingerprint' => array($certFingerprint),
                );
            }
            // no valid key found
        } elseif ($metadata->hasValue($prefix.'certFingerprint')) {
            // we only have a fingerprint available
            $fps = $metadata->getArrayizeString($prefix.'certFingerprint');

            // normalize fingerprint(s) - lowercase and no colons
            foreach ($fps as &$fp) {
                assert('is_string($fp)');
                $fp = strtolower(str_replace(':', '', $fp));
            }

            // We can't build a full certificate from a fingerprint, and may as well return an array with only the
            //fingerprint(s) immediately.
            return array('certFingerprint' => $fps);
        }

        // no public key/certificate available
        if ($required) {
            throw new \SimpleSAML_Error_Exception('No public key / certificate found in metadata.');
        } else {
            return null;
        }
    }


    /**
     * This function hashes a password with a given algorithm.
     *
     * @param string $password The password to hash.
     * @param string $algorithm The hashing algorithm, uppercase, optionally prepended with 'S' (salted). See
     *     hash_algos() for a complete list of hashing algorithms.
     * @param string $salt An optional salt to use.
     *
     * @return string The hashed password.
     * @throws \InvalidArgumentException If the input parameters are not strings.
     * @throws \SimpleSAML_Error_Exception If the algorithm specified is not supported.
     *
     * @see hash_algos()
     *
     * @author Dyonisius Visser, TERENA <visser@terena.org>
     * @author Jaime Perez, UNINETT AS <jaime.perez@uninett.no>
     */
    public static function pwHash($password, $algorithm, $salt = null)
    {
        if (!is_string($algorithm) || !is_string($password)) {
            throw new \InvalidArgumentException('Invalid input parameters.');
        }

        // hash w/o salt
        if (in_array(strtolower($algorithm), hash_algos())) {
            $alg_str = '{'.str_replace('SHA1', 'SHA', $algorithm).'}'; // LDAP compatibility
            $hash = hash(strtolower($algorithm), $password, true);
            return $alg_str.base64_encode($hash);
        }

        // hash w/ salt
        if ($salt === null) { // no salt provided, generate one
            // default 8 byte salt, but 4 byte for LDAP SHA1 hashes
            $bytes = ($algorithm == 'SSHA1') ? 4 : 8;
            $salt = openssl_random_pseudo_bytes($bytes);
        }

        if ($algorithm[0] == 'S' && in_array(substr(strtolower($algorithm), 1), hash_algos())) {
            $alg = substr(strtolower($algorithm), 1); // 'sha256' etc
            $alg_str = '{'.str_replace('SSHA1', 'SSHA', $algorithm).'}'; // LDAP compatibility
            $hash = hash($alg, $password.$salt, true);
            return $alg_str.base64_encode($hash.$salt);
        }

        throw new \SimpleSAML_Error_Exception('Hashing algorithm \''.strtolower($algorithm).'\' is not supported');
    }


    /**
     * Compare two strings securely.
     *
     * This method checks if two strings are equal in constant time, avoiding timing attacks. Use it every time we need
     * to compare a string with a secret that shouldn't be leaked, i.e. when verifying passwords, one-time codes, etc.
     *
     * @param string $known A known string.
     * @param string $user A user-provided string to compare with the known string.
     *
     * @return bool True if both strings are equal, false otherwise.
     */
    public static function secureCompare($known, $user)
    {
        if (function_exists('hash_equals')) {
            // use hash_equals() if available (PHP >= 5.6)
            return hash_equals($known, $user);
        }

        // compare manually in constant time
        $len = mb_strlen($known, '8bit'); // see mbstring.func_overload
        if ($len !== mb_strlen($user, '8bit')) {
            return false; // length differs
        }
        $diff = 0;
        for ($i = 0; $i < $len; $i++) {
            $diff |= ord($known[$i]) ^ ord($user[$i]);
        }
        // if all the bytes in $a and $b are identical, $diff should be equal to 0
        return $diff === 0;
    }


    /**
     * This function checks if a password is valid
     *
     * @param string $hash The password as it appears in password file, optionally prepended with algorithm.
     * @param string $password The password to check in clear.
     *
     * @return boolean True if the hash corresponds with the given password, false otherwise.
     * @throws \InvalidArgumentException If the input parameters are not strings.
     * @throws \SimpleSAML_Error_Exception If the algorithm specified is not supported.
     *
     * @author Dyonisius Visser, TERENA <visser@terena.org>
     */
    public static function pwValid($hash, $password)
    {
        if (!is_string($hash) || !is_string($password)) {
            throw new \InvalidArgumentException('Invalid input parameters.');
        }

        // match algorithm string (e.g. '{SSHA256}', '{MD5}')
        if (preg_match('/^{(.*?)}(.*)$/', $hash, $matches)) {

            // LDAP compatibility
            $alg = preg_replace('/^(S?SHA)$/', '${1}1', $matches[1]);

            // hash w/o salt
            if (in_array(strtolower($alg), hash_algos())) {
                return self::secureCompare($hash, self::pwHash($password, $alg));
            }

            // hash w/ salt
            if ($alg[0] === 'S' && in_array(substr(strtolower($alg), 1), hash_algos())) {
                $php_alg = substr(strtolower($alg), 1);

                // get hash length of this algorithm to learn how long the salt is
                $hash_length = strlen(hash($php_alg, '', true));
                $salt = substr(base64_decode($matches[2]), $hash_length);
                return self::secureCompare($hash, self::pwHash($password, $alg, $salt));
            }
        } else {
            return $hash === $password;
        }

        throw new \SimpleSAML_Error_Exception('Hashing algorithm \''.strtolower($alg).'\' is not supported');
    }
}
