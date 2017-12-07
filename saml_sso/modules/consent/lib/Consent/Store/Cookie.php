<?php
/**
 * Cookie storage for consent
 *
 * This class implements a consent store which stores the consent information
 * in cookies on the users computer.
 *
 * Example - Consent module with cookie store:
 * 
 * <code>
 * 'authproc' => array(
 *   array(
 *     'consent:Consent',
 *     'store' => 'consent:Cookie',
 *     ),
 *   ),
 * </code>
 *
 * @author  Olav Morken <olav.morken@uninett.no>
 * @package SimpleSAMLphp
 */
class sspmod_consent_Consent_Store_Cookie extends sspmod_consent_Store
{
    /**
     * Check for consent.
     *
     * This function checks whether a given user has authorized the release of
     * the attributes identified by $attributeSet from $source to $destination.
     *
     * @param string $userId        The hash identifying the user at an IdP.
     * @param string $destinationId A string which identifies the destination.
     * @param string $attributeSet  A hash which identifies the attributes.
     *
     * @return bool True if the user has given consent earlier, false if not
     *              (or on error).
     */
    public function hasConsent($userId, $destinationId, $attributeSet)
    {
        assert('is_string($userId)');
        assert('is_string($destinationId)');
        assert('is_string($attributeSet)');

        $cookieName = self::_getCookieName($userId, $destinationId);

        $data = $userId . ':' . $attributeSet . ':' . $destinationId;

        SimpleSAML_Logger::debug('Consent cookie - Get [' . $data . ']');

        if (!array_key_exists($cookieName, $_COOKIE)) {
            SimpleSAML_Logger::debug(
                'Consent cookie - no cookie with name \'' .
                $cookieName . '\'.'
            );
            return false;
        }
        if (!is_string($_COOKIE[$cookieName])) {
            SimpleSAML_Logger::warning(
                'Value of consent cookie wasn\'t a string. Was: ' .
                var_export($_COOKIE[$cookieName], true)
            );
            return false;
        }

        $data = self::_sign($data);

        if ($_COOKIE[$cookieName] !== $data) {
            SimpleSAML_Logger::info(
                'Attribute set changed from the last time consent was given.'
            );
            return false;
        }

        SimpleSAML_Logger::debug(
            'Consent cookie - found cookie with correct name and value.'
        );

        return true;
    }

    /**
     * Save consent.
     *
     * Called when the user asks for the consent to be saved. If consent information
     * for the given user and destination already exists, it should be overwritten.
     *
     * @param string $userId        The hash identifying the user at an IdP.
     * @param string $destinationId A string which identifies the destination.
     * @param string $attributeSet  A hash which identifies the attributes.
     *
     * @return void
     */
    public function saveConsent($userId, $destinationId, $attributeSet)
    {
        assert('is_string($userId)');
        assert('is_string($destinationId)');
        assert('is_string($attributeSet)');

        $name = self::_getCookieName($userId, $destinationId);
        $value = $userId . ':' . $attributeSet . ':' . $destinationId;

        SimpleSAML_Logger::debug('Consent cookie - Set [' . $value . ']');

        $value = self::_sign($value);
        $this->_setConsentCookie($name, $value);
    }

    /**
     * Delete consent.
     *
     * Called when a user revokes consent for a given destination.
     *
     * @param string $userId        The hash identifying the user at an IdP.
     * @param string $destinationId A string which identifies the destination.
     *
     * @return void
     */
    public function deleteConsent($userId, $destinationId)
    {
        assert('is_string($userId)');
        assert('is_string($destinationId)');

        $name = self::_getCookieName($userId, $destinationId);
        $this->_setConsentCookie($name, null);
    }

    /**
     * Delete consent.
     *
     * @param string $userId The hash identifying the user at an IdP.
     *
     * @return void
     */
    public function deleteAllConsents($userId)
    {
        assert('is_string($userId)');

        throw new Exception(
            'The cookie consent handler does not support delete of all consents...'
        );
    }

    /**
     * Retrieve consents.
     *
     * This function should return a list of consents the user has saved.
     *
     * @param string $userId The hash identifying the user at an IdP.
     * 
     * @return array Array of all destination ids the user has given consent for.
     */
    public function getConsents($userId)
    {
        assert('is_string($userId)');

        $ret = array();

        $cookieNameStart = 'sspmod_consent:';
        $cookieNameStartLen = strlen($cookieNameStart);
        foreach ($_COOKIE as $name => $value) {
            if (substr($name, 0, $cookieNameStartLen) !== $cookieNameStart) {
                continue;
            }

            $value = self::_verify($value);
            if ($value === false) {
                continue;
            }

            $tmp = explode(':', $value, 3);
            if (count($tmp) !== 3) {
                SimpleSAML_Logger::warning(
                    'Consent cookie with invalid value: ' . $value
                );
                continue;
            }

            if ($userId !== $tmp[0]) {
                // Wrong user
                continue;
            }

            $destination = $tmp[2];
            $ret[] = $destination;
        }

        return $ret;
    }

    /**
     * Calculate a signature of some data.
     *
     * This function calculates a signature of the data.
     *
     * @param string $data The data which should be signed.
     * 
     * @return string The signed data.
     */
    private static function _sign($data)
    {
        assert('is_string($data)');

        $secretSalt = SimpleSAML\Utils\Config::getSecretSalt();

        return sha1($secretSalt . $data . $secretSalt) . ':' . $data;
    }

    /**
     * Verify signed data.
     *
     * This function verifies signed data.
     *
     * @param string $signedData The data which is signed.
     * 
     * @return string|false The data, or false if the signature is invalid.
     */
    private static function _verify($signedData)
    {
        assert('is_string($signedData)');

        $data = explode(':', $signedData, 2);
        if (count($data) !== 2) {
            SimpleSAML_Logger::warning('Consent cookie: Missing signature.');
            return false;
        }
        $data = $data[1];

        $newSignedData = self::_sign($data);
        if ($newSignedData !== $signedData) {
            SimpleSAML_Logger::warning('Consent cookie: Invalid signature.');
            return false;
        }

        return $data;
    }

    /**
     * Get cookie name.
     *
     * This function gets the cookie name for the given user & destination.
     *
     * @param string $userId        The hash identifying the user at an IdP.
     * @param string $destinationId A string which identifies the destination.
     *
     * @return string The cookie name
     */
    private static function _getCookieName($userId, $destinationId)
    {
        assert('is_string($userId)');
        assert('is_string($destinationId)');

        return 'sspmod_consent:' . sha1($userId . ':' . $destinationId);
    }

    /**
     * Helper function for setting a cookie.
     *
     * @param string      $name  Name of the cookie.
     * @param string|null $value Value of the cookie. Set this to null to
     *                           delete the cookie.
     *
     * @return void
     */
    private function _setConsentCookie($name, $value)
    {
        assert('is_string($name)');
        assert('is_string($value) || is_null($value)');

        $globalConfig = SimpleSAML_Configuration::getInstance();
        $params = array(
            'lifetime' => (90*24*60*60),
            'path' => ('/' . $globalConfig->getBaseURL()),
            'httponly' => FALSE,
        );

        if (\SimpleSAML\Utils\HTTP::isHTTPS()) {
            // Enable secure cookie for https-requests
            $params['secure'] = true;
        } else {
            $params['secure'] = false;
        }

        \SimpleSAML\Utils\HTTP::setCookie($name, $value, $params, FALSE);
    }

}
