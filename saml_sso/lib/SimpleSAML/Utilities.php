<?php


/**
 * Misc static functions that is used several places.in example parsing and id generation.
 *
 * @author Andreas Åkre Solberg, UNINETT AS. <andreas.solberg@uninett.no>
 * @package SimpleSAMLphp
 *
 * @deprecated This entire class will be removed in SimpleSAMLphp 2.0.
 */
class SimpleSAML_Utilities
{

    /**
     * List of log levels.
     *
     * This list is used to restore the log levels after some log levels are disabled.
     *
     * @var array
     */
    private static $logLevelStack = array();


    /**
     * The current mask of disabled log levels.
     *
     * Note: This mask is not directly related to the PHP error reporting level.
     *
     * @var int
     */
    public static $logMask = 0;


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\HTTP::getSelfHost() instead.
     */
    public static function getSelfHost()
    {
        return \SimpleSAML\Utils\HTTP::getSelfHost();
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\HTTP::getSelfURLHost() instead.
     */
    public static function selfURLhost()
    {
        return \SimpleSAML\Utils\HTTP::getSelfURLHost();
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\HTTP::isHTTPS() instead.
     */
    public static function isHTTPS()
    {
        return \SimpleSAML\Utils\HTTP::isHTTPS();
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\HTTP::getSelfURLNoQuery()
     *     instead.
     */
    public static function selfURLNoQuery()
    {
        return \SimpleSAML\Utils\HTTP::getSelfURLNoQuery();
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\HTTP::getSelfHostWithPath()
     *     instead.
     */
    public static function getSelfHostWithPath()
    {
        return \SimpleSAML\Utils\HTTP::getSelfHostWithPath();
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\HTTP::getFirstPathElement()
     *     instead.
     */
    public static function getFirstPathElement($trailingslash = true)
    {
        return \SimpleSAML\Utils\HTTP::getFirstPathElement($trailingslash);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\HTTP::getSelfURL() instead.
     */
    public static function selfURL()
    {
        return \SimpleSAML\Utils\HTTP::getSelfURL();
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\HTTP::getBaseURL() instead.
     */
    public static function getBaseURL()
    {
        return \SimpleSAML\Utils\HTTP::getBaseURL();
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\HTTP::addURLParameters() instead.
     */
    public static function addURLparameter($url, $parameters)
    {
        return \SimpleSAML\Utils\HTTP::addURLParameters($url, $parameters);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use \SimpleSAML\Utils\HTTP::checkURLAllowed() instead.
     */
    public static function checkURLAllowed($url, array $trustedSites = null)
    {
        return \SimpleSAML\Utils\HTTP::checkURLAllowed($url, $trustedSites);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML_Auth_State::parseStateID() instead.
     */
    public static function parseStateID($stateId)
    {
        return SimpleSAML_Auth_State::parseStateID($stateId);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0.
     */
    public static function checkDateConditions($start = null, $end = null)
    {
        $currentTime = time();

        if (!empty($start)) {
            $startTime = SAML2_Utils::xsDateTimeToTimestamp($start);
            // Allow for a 10 minute difference in Time
            if (($startTime < 0) || (($startTime - 600) > $currentTime)) {
                return false;
            }
        }
        if (!empty($end)) {
            $endTime = SAML2_Utils::xsDateTimeToTimestamp($end);
            if (($endTime < 0) || ($endTime <= $currentTime)) {
                return false;
            }
        }
        return true;
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\Random::generateID() instead.
     */
    public static function generateID()
    {
        return SimpleSAML\Utils\Random::generateID();
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use \SimpleSAML\Utils\Time::generateTimestamp()
     *     instead.
     */
    public static function generateTimestamp($instant = null)
    {
        return SimpleSAML\Utils\Time::generateTimestamp($instant);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use \SimpleSAML\Utils\Time::parseDuration() instead.
     */
    public static function parseDuration($duration, $timestamp = null)
    {
        return SimpleSAML\Utils\Time::parseDuration($duration, $timestamp);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please raise a SimpleSAML_Error_Error exception instead.
     */
    public static function fatalError($trackId = 'na', $errorCode = null, Exception $e = null)
    {
        throw new SimpleSAML_Error_Error($errorCode, $e);
    }


    /**
     * @deprecated This method will be removed in version 2.0. Use SimpleSAML\Utils\Net::ipCIDRcheck() instead.
     */
    public static function ipCIDRcheck($cidr, $ip = null)
    {
        return SimpleSAML\Utils\Net::ipCIDRcheck($cidr, $ip);
    }


    private static function _doRedirect($url, $parameters = array())
    {
        assert('is_string($url)');
        assert('!empty($url)');
        assert('is_array($parameters)');

        if (!empty($parameters)) {
            $url = self::addURLparameter($url, $parameters);
        }

        /* Set the HTTP result code. This is either 303 See Other or
         * 302 Found. HTTP 303 See Other is sent if the HTTP version
         * is HTTP/1.1 and the request type was a POST request.
         */
        if ($_SERVER['SERVER_PROTOCOL'] === 'HTTP/1.1' &&
            $_SERVER['REQUEST_METHOD'] === 'POST'
        ) {
            $code = 303;
        } else {
            $code = 302;
        }

        if (strlen($url) > 2048) {
            SimpleSAML_Logger::warning('Redirecting to a URL longer than 2048 bytes.');
        }

        // Set the location header
        header('Location: '.$url, true, $code);

        // Disable caching of this response
        header('Pragma: no-cache');
        header('Cache-Control: no-cache, must-revalidate');

        // Show a minimal web page with a clickable link to the URL
        echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"'.
            ' "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">'."\n";
        echo '<html xmlns="http://www.w3.org/1999/xhtml">';
        echo '<head>
					<meta http-equiv="content-type" content="text/html; charset=utf-8">
					<title>Redirect</title>
				</head>';
        echo '<body>';
        echo '<h1>Redirect</h1>';
        echo '<p>';
        echo 'You were redirected to: ';
        echo '<a id="redirlink" href="'.
            htmlspecialchars($url).'">'.htmlspecialchars($url).'</a>';
        echo '<script type="text/javascript">document.getElementById("redirlink").focus();</script>';
        echo '</p>';
        echo '</body>';
        echo '</html>';

        // End script execution
        exit;
    }


    /**
     * @deprecated 1.12.0 This method will be removed from the API. Instead, use the redirectTrustedURL() or
     * redirectUntrustedURL() functions accordingly.
     */
    public static function redirect($url, $parameters = array(), $allowed_redirect_hosts = null)
    {
        assert('is_string($url)');
        assert('strlen($url) > 0');
        assert('is_array($parameters)');

        if ($allowed_redirect_hosts !== null) {
            $url = self::checkURLAllowed($url, $allowed_redirect_hosts);
        } else {
            $url = self::normalizeURL($url);
        }
        self::_doRedirect($url, $parameters);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\HTTP::redirectTrustedURL()
     *     instead.
     */
    public static function redirectTrustedURL($url, $parameters = array())
    {
        \SimpleSAML\Utils\HTTP::redirectTrustedURL($url, $parameters);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\HTTP::redirectUntrustedURL()
     *     instead.
     */
    public static function redirectUntrustedURL($url, $parameters = array())
    {
        \SimpleSAML\Utils\HTTP::redirectUntrustedURL($url, $parameters);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\Arrays::transpose() instead.
     */
    public static function transposeArray($in)
    {
        return SimpleSAML\Utils\Arrays::transpose($in);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\XML::isDOMElementOfType()
     *     instead.
     */
    public static function isDOMElementOfType(DOMNode $element, $name, $nsURI)
    {
        return SimpleSAML\Utils\XML::isDOMElementOfType($element, $name, $nsURI);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\XML::getDOMChildren() instead.
     */
    public static function getDOMChildren(DOMElement $element, $localName, $namespaceURI)
    {
        return SimpleSAML\Utils\XML::getDOMChildren($element, $localName, $namespaceURI);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\XML::getDOMText() instead.
     */
    public static function getDOMText($element)
    {
        return SimpleSAML\Utils\XML::getDOMText($element);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\HTTP::getAcceptLanguage()
     *     instead.
     */
    public static function getAcceptLanguage()
    {
        return \SimpleSAML\Utils\HTTP::getAcceptLanguage();
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\XML::isValid() instead.
     */
    public static function validateXML($xml, $schema)
    {
        $result = \SimpleSAML\Utils\XML::isValid($xml, $schema);
        return ($result === true) ? '' : $result;
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\XML::checkSAMLMessage() instead.
     */
    public static function validateXMLDocument($message, $type)
    {
        \SimpleSAML\Utils\XML::checkSAMLMessage($message, $type);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use openssl_random_pseudo_bytes() instead.
     */
    public static function generateRandomBytes($length)
    {
        assert('is_int($length)');

        return openssl_random_pseudo_bytes($length);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use bin2hex() instead.
     */
    public static function stringToHex($bytes)
    {
        $ret = '';
        for ($i = 0; $i < strlen($bytes); $i++) {
            $ret .= sprintf('%02x', ord($bytes[$i]));
        }
        return $ret;
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\System::resolvePath() instead.
     */
    public static function resolvePath($path, $base = null)
    {
        return \SimpleSAML\Utils\System::resolvePath($path, $base);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\HTTP::resolveURL() instead.
     */
    public static function resolveURL($url, $base = null)
    {
        return \SimpleSAML\Utils\HTTP::resolveURL($url, $base);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\HTTP::normalizeURL() instead.
     */
    public static function normalizeURL($url)
    {
        return \SimpleSAML\Utils\HTTP::normalizeURL($url);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\HTTP::parseQueryString() instead.
     */
    public static function parseQueryString($query_string)
    {
        return \SimpleSAML\Utils\HTTP::parseQueryString($query_string);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use
     * SimpleSAML\Utils\Attributes::normalizeAttributesArray() instead.
     */
    public static function parseAttributes($attributes)
    {
        return SimpleSAML\Utils\Attributes::normalizeAttributesArray($attributes);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\Config::getSecretSalt() instead.
     */
    public static function getSecretSalt()
    {
        return SimpleSAML\Utils\Config::getSecretSalt();
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please call error_get_last() directly.
     */
    public static function getLastError()
    {

        if (!function_exists('error_get_last')) {
            return '[Cannot get error message]';
        }

        $error = error_get_last();
        if ($error === null) {
            return '[No error message found]';
        }

        return $error['message'];
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\Config::getCertPath() instead.
     */
    public static function resolveCert($path)
    {
        return \SimpleSAML\Utils\Config::getCertPath($path);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\Crypto::loadPublicKey() instead.
     */
    public static function loadPublicKey(SimpleSAML_Configuration $metadata, $required = false, $prefix = '')
    {
        return SimpleSAML\Utils\Crypto::loadPublicKey($metadata, $required, $prefix);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\Crypto::loadPrivateKey() instead.
     */
    public static function loadPrivateKey(SimpleSAML_Configuration $metadata, $required = false, $prefix = '')
    {
        return SimpleSAML\Utils\Crypto::loadPrivateKey($metadata, $required, $prefix);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\XML::formatDOMElement() instead.
     */
    public static function formatDOMElement(DOMElement $root, $indentBase = '')
    {
        SimpleSAML\Utils\XML::formatDOMElement($root, $indentBase);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\XML::formatXMLString() instead.
     */
    public static function formatXMLString($xml, $indentBase = '')
    {
        return SimpleSAML\Utils\XML::formatXMLString($xml, $indentBase);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\Arrays::arrayize() instead.
     */
    public static function arrayize($data, $index = 0)
    {
        return SimpleSAML\Utils\Arrays::arrayize($data, $index);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\Auth::isAdmin() instead.
     */
    public static function isAdmin()
    {
        return SimpleSAML\Utils\Auth::isAdmin();
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\Auth::getAdminLoginURL instead();
     */
    public static function getAdminLoginURL($returnTo = null)
    {
        return SimpleSAML\Utils\Auth::getAdminLoginURL($returnTo);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\Auth::requireAdmin() instead.
     */
    public static function requireAdmin()
    {
        \SimpleSAML\Utils\Auth::requireAdmin();
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\HTTP::submitPOSTData() instead.
     */
    public static function postRedirect($destination, $post)
    {
        \SimpleSAML\Utils\HTTP::submitPOSTData($destination, $post);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. PLease use SimpleSAML\Utils\HTTP::getPOSTRedirectURL()
     *     instead.
     */
    public static function createPostRedirectLink($destination, $post)
    {
        return \SimpleSAML\Utils\HTTP::getPOSTRedirectURL($destination, $post);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\HTTP::getPOSTRedirectURL()
     *     instead.
     */
    public static function createHttpPostRedirectLink($destination, $post)
    {
        assert('is_string($destination)');
        assert('is_array($post)');

        $postId = SimpleSAML\Utils\Random::generateID();
        $postData = array(
            'post' => $post,
            'url'  => $destination,
        );

        $session = SimpleSAML_Session::getSessionFromRequest();
        $session->setData('core_postdatalink', $postId, $postData);

        $redirInfo = base64_encode(SimpleSAML\Utils\Crypto::aesEncrypt($session->getSessionId().':'.$postId));

        $url = SimpleSAML_Module::getModuleURL('core/postredirect.php', array('RedirInfo' => $redirInfo));
        $url = preg_replace("#^https:#", "http:", $url);

        return $url;
    }


    /**
     * @deprecated This method will be removed in SSP 2.0.
     */
    public static function validateCA($certificate, $caFile)
    {
        SimpleSAML_XML_Validator::validateCertificate($certificate, $caFile);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\Time::initTimezone() instead.
     */
    public static function initTimezone()
    {
        \SimpleSAML\Utils\Time::initTimezone();
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\System::writeFile() instead.
     */
    public static function writeFile($filename, $data, $mode = 0600)
    {
        \SimpleSAML\Utils\System::writeFile($filename, $data, $mode);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\System::getTempDir instead.
     */
    public static function getTempDir()
    {
        return SimpleSAML\Utils\System::getTempDir();
    }


    /**
     * @deprecated This method will be removed in SSP 2.0.
     */
    public static function maskErrors($mask)
    {
        assert('is_int($mask)');

        $currentEnabled = error_reporting();
        self::$logLevelStack[] = array($currentEnabled, self::$logMask);

        $currentEnabled &= ~$mask;
        error_reporting($currentEnabled);
        self::$logMask |= $mask;
    }


    /**
     * @deprecated This method will be removed in SSP 2.0.
     */
    public static function popErrorMask()
    {
        $lastMask = array_pop(self::$logLevelStack);
        error_reporting($lastMask[0]);
        self::$logMask = $lastMask[1];
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use
     *     SimpleSAML\Utils\Config\Metadata::getDefaultEndpoint() instead.
     */
    public static function getDefaultEndpoint(array $endpoints, array $bindings = null)
    {
        return \SimpleSAML\Utils\Config\Metadata::getDefaultEndpoint($endpoints, $bindings);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\HTTP::checkSessionCookie()
     *     instead.
     */
    public static function checkCookie($retryURL = null)
    {
        \SimpleSAML\Utils\HTTP::checkSessionCookie($retryURL);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\XML::debugSAMLMessage() instead.
     */
    public static function debugMessage($message, $type)
    {
        \SimpleSAML\Utils\XML::debugSAMLMessage($message, $type);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\HTTP::fetch() instead.
     */
    public static function fetch($path, $context = array(), $getHeaders = false)
    {
        return \SimpleSAML\Utils\HTTP::fetch($path, $context, $getHeaders);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\Crypto::aesEncrypt() instead.
     */
    public static function aesEncrypt($clear)
    {
        return SimpleSAML\Utils\Crypto::aesEncrypt($clear);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\Crypto::aesDecrypt() instead.
     */
    public static function aesDecrypt($encData)
    {
        return SimpleSAML\Utils\Crypto::aesDecrypt($encData);
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\System::getOS() instead.
     */
    public static function isWindowsOS()
    {
        return SimpleSAML\Utils\System::getOS() === SimpleSAML\Utils\System::WINDOWS;
    }


    /**
     * @deprecated This method will be removed in SSP 2.0. Please use SimpleSAML\Utils\HTTP::setCookie() instead.
     */
    public static function setCookie($name, $value, array $params = null, $throw = true)
    {
        \SimpleSAML\Utils\HTTP::setCookie($name, $value, $params, $throw);
    }

}
