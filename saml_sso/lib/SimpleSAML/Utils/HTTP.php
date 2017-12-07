<?php
namespace SimpleSAML\Utils;


/**
 * HTTP-related utility methods.
 *
 * @package SimpleSAMLphp
 */
class HTTP
{

    /**
     * Obtain a URL where we can redirect to securely post a form with the given data to a specific destination.
     *
     * @param string $destination The destination URL.
     * @param array  $data An associative array containing the data to be posted to $destination.
     *
     * @return string  A URL which allows to securely post a form to $destination.
     *
     * @author Jaime Perez, UNINETT AS <jaime.perez@uninett.no>
     */
    private static function getSecurePOSTRedirectURL($destination, $data)
    {
        $session = \SimpleSAML_Session::getSessionFromRequest();
        $id = self::savePOSTData($session, $destination, $data);

        // encrypt the session ID and the random ID
        $info = base64_encode(Crypto::aesEncrypt($session->getSessionId().':'.$id));

        $url = \SimpleSAML_Module::getModuleURL('core/postredirect.php', array('RedirInfo' => $info));
        return preg_replace('#^https:#', 'http:', $url);
    }


    /**
     * Retrieve Host value from $_SERVER environment variables.
     *
     * @return string The current host name, including the port if needed. It will use localhost when unable to
     *     determine the current host.
     *
     * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
     */
    private static function getServerHost()
    {
        if (array_key_exists('HTTP_HOST', $_SERVER)) {
            $current = $_SERVER['HTTP_HOST'];
        } elseif (array_key_exists('SERVER_NAME', $_SERVER)) {
            $current = $_SERVER['SERVER_NAME'];
        } else {
            // almost certainly not what you want, but...
            $current = 'localhost';
        }

        if (strstr($current, ":")) {
            $decomposed = explode(":", $current);
            $port = array_pop($decomposed);
            if (!is_numeric($port)) {
                array_push($decomposed, $port);
            }
            $current = implode($decomposed, ":");
        }
        return $current;
    }


    /**
     * Retrieve HTTPS status from $_SERVER environment variables.
     *
     * @return boolean True if the request was performed through HTTPS, false otherwise.
     *
     * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
     */
    private static function getServerHTTPS()
    {
        if (!array_key_exists('HTTPS', $_SERVER)) {
            // not an https-request
            return false;
        }

        if ($_SERVER['HTTPS'] === 'off') {
            // IIS with HTTPS off
            return false;
        }

        // otherwise, HTTPS will be a non-empty string
        return $_SERVER['HTTPS'] !== '';
    }


    /**
     * Retrieve the port number from $_SERVER environment variables.
     *
     * @return string The port number prepended by a colon, if it is different than the default port for the protocol
     *     (80 for HTTP, 443 for HTTPS), or an empty string otherwise.
     *
     * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
     */
    private static function getServerPort()
    {
        $port = (isset($_SERVER['SERVER_PORT'])) ? $_SERVER['SERVER_PORT'] : '80';
        if (self::getServerHTTPS()) {
            if ($port !== '443') {
                return ':'.$port;
            }
        } else {
            if ($port !== '80') {
                return ':'.$port;
            }
        }
        return '';
    }


    /**
     * This function redirects the user to the specified address.
     *
     * This function will use the "HTTP 303 See Other" redirection if the current request used the POST method and the
     * HTTP version is 1.1. Otherwise, a "HTTP 302 Found" redirection will be used.
     *
     * The function will also generate a simple web page with a clickable link to the target page.
     *
     * @param string   $url The URL we should redirect to. This URL may include query parameters. If this URL is a
     *     relative URL (starting with '/'), then it will be turned into an absolute URL by prefixing it with the
     *     absolute URL to the root of the website.
     * @param string[] $parameters An array with extra query string parameters which should be appended to the URL. The
     *     name of the parameter is the array index. The value of the parameter is the value stored in the index. Both
     *     the name and the value will be urlencoded. If the value is NULL, then the parameter will be encoded as just
     *     the name, without a value.
     *
     * @return void This function never returns.
     * @throws \InvalidArgumentException If $url is not a string or is empty, or $parameters is not an array.
     *
     * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
     * @author Mads Freek Petersen
     * @author Jaime Perez, UNINETT AS <jaime.perez@uninett.no>
     */
    private static function redirect($url, $parameters = array())
    {
        if (!is_string($url) || empty($url) || !is_array($parameters)) {
            throw new \InvalidArgumentException('Invalid input parameters.');
        }
        if (!empty($parameters)) {
            $url = self::addURLParameters($url, $parameters);
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
            \SimpleSAML_Logger::warning('Redirecting to a URL longer than 2048 bytes.');
        }

        // set the location header
        header('Location: '.$url, true, $code);

        // disable caching of this response
        header('Pragma: no-cache');
        header('Cache-Control: no-cache, must-revalidate');

        // show a minimal web page with a clickable link to the URL
        echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"';
        echo ' "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">'."\n";
        echo '<html xmlns="http://www.w3.org/1999/xhtml">'."\n";
        echo "  <head>\n";
        echo '    <meta http-equiv="content-type" content="text/html; charset=utf-8">'."\n";
        echo "    <title>Redirect</title>\n";
        echo "  </head>\n";
        echo "  <body>\n";
        echo "    <h1>Redirect</h1>\n";
        echo '      <p>You were redirected to: <a id="redirlink" href="'.htmlspecialchars($url).'">';
        echo htmlspecialchars($url)."</a>\n";
        echo '        <script type="text/javascript">document.getElementById("redirlink").focus();</script>'."\n";
        echo "      </p>\n";
        echo "  </body>\n";
        echo '</html>';

        // end script execution
        exit;
    }


    /**
     * Save the given HTTP POST data and the destination where it should be posted to a given session.
     *
     * @param \SimpleSAML_Session $session The session where to temporarily store the data.
     * @param string              $destination The destination URL where the form should be posted.
     * @param array               $data An associative array with the data to be posted to $destination.
     *
     * @return string A random identifier that can be used to retrieve the data from the current session.
     *
     * @author Andjelko Horvat
     * @author Jaime Perez, UNINETT AS <jaime.perez@uninett.no>
     */
    private static function savePOSTData(\SimpleSAML_Session $session, $destination, $data)
    {
        // generate a random ID to avoid replay attacks
        $id = Random::generateID();
        $postData = array(
            'post' => $data,
            'url'  => $destination,
        );

        // save the post data to the session, tied to the random ID
        $session->setData('core_postdatalink', $id, $postData);

        return $id;
    }


    /**
     * Add one or more query parameters to the given URL.
     *
     * @param string $url The URL the query parameters should be added to.
     * @param array  $parameters The query parameters which should be added to the url. This should be an associative
     *     array.
     *
     * @return string The URL with the new query parameters.
     * @throws \InvalidArgumentException If $url is not a string or $parameters is not an array.
     *
     * @author Andreas Solberg, UNINETT AS <andreas.solberg@uninett.no>
     * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
     */
    public static function addURLParameters($url, $parameters)
    {
        if (!is_string($url) || !is_array($parameters)) {
            throw new \InvalidArgumentException('Invalid input parameters.');
        }

        $queryStart = strpos($url, '?');
        if ($queryStart === false) {
            $oldQuery = array();
            $url .= '?';
        } else {
            $oldQuery = substr($url, $queryStart + 1);
            if ($oldQuery === false) {
                $oldQuery = array();
            } else {
                $oldQuery = self::parseQueryString($oldQuery);
            }
            $url = substr($url, 0, $queryStart + 1);
        }

        $query = array_merge($oldQuery, $parameters);
        $url .= http_build_query($query, '', '&');

        return $url;
    }


    /**
     * Check for session cookie, and show missing-cookie page if it is missing.
     *
     * @param string|NULL $retryURL The URL the user should access to retry the operation. Defaults to null.
     *
     * @return void If there is a session cookie, nothing will be returned. Otherwise, the user will be redirected to a
     *     page telling about the missing cookie.
     * @throws \InvalidArgumentException If $retryURL is neither a string nor null.
     *
     * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
     */
    public static function checkSessionCookie($retryURL = null)
    {
        if (!is_string($retryURL) && !is_null($retryURL)) {
            throw new \InvalidArgumentException('Invalid input parameters.');
        }

        $session = \SimpleSAML_Session::getSessionFromRequest();
        if ($session->hasSessionCookie()) {
            return;
        }

        // we didn't have a session cookie. Redirect to the no-cookie page

        $url = \SimpleSAML_Module::getModuleURL('core/no_cookie.php');
        if ($retryURL !== null) {
            $url = self::addURLParameters($url, array('retryURL' => $retryURL));
        }
        self::redirectTrustedURL($url);
    }


    /**
     * Check if a URL is valid and is in our list of allowed URLs.
     *
     * @param string $url The URL to check.
     * @param array  $trustedSites An optional white list of domains. If none specified, the 'trusted.url.domains'
     * configuration directive will be used.
     *
     * @return string The normalized URL itself if it is allowed. An empty string if the $url parameter is empty as
     * defined by the empty() function.
     * @throws \InvalidArgumentException If the URL is malformed.
     * @throws \SimpleSAML_Error_Exception If the URL is not allowed by configuration.
     *
     * @author Jaime Perez, UNINETT AS <jaime.perez@uninett.no>
     */
    public static function checkURLAllowed($url, array $trustedSites = null)
    {
        if (empty($url)) {
            return '';
        }
        $url = self::normalizeURL($url);

        // get the white list of domains
        if ($trustedSites === null) {
            $trustedSites = \SimpleSAML_Configuration::getInstance()->getValue('trusted.url.domains', array());
        }

        // validates the URL's host is among those allowed
        if (is_array($trustedSites)) {
            assert(is_array($trustedSites));
            preg_match('@^http(s?)://([^/:]+)((?::\d+)?)@i', $url, $matches);
            $hostname = $matches[2];

            // allow URLs with standard ports specified (non-standard ports must then be allowed explicitly)
            if (!empty($matches[3]) &&
                (($matches[1] === '' && $matches[3] !== ':80') || ($matches[1]) === 's' && $matches[3] !== ':443')
            ) {
                $hostname = $hostname.$matches[3];
            }

            // add self host to the white list
            $self_host = self::getSelfHost();
            $trustedSites[] = $self_host;

            // throw exception due to redirection to untrusted site
            if (!in_array($hostname, $trustedSites)) {
                throw new \SimpleSAML_Error_Exception('URL not allowed: '.$url);
            }
        }
        return $url;
    }


    /**
     * Helper function to retrieve a file or URL with proxy support.
     *
     * An exception will be thrown if we are unable to retrieve the data.
     *
     * @param string  $url The path or URL we should fetch.
     * @param array   $context Extra context options. This parameter is optional.
     * @param boolean $getHeaders Whether to also return response headers. Optional.
     *
     * @return mixed array if $getHeaders is set, string otherwise
     * @throws \InvalidArgumentException If the input parameters are invalid.
     * @throws \SimpleSAML_Error_Exception If the file or URL cannot be retrieved.
     *
     * @author Andjelko Horvat
     * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
     * @author Marco Ferrante, University of Genova <marco@csita.unige.it>
     */
    public static function fetch($url, $context = array(), $getHeaders = false)
    {
        if (!is_string($url)) {
            throw new \InvalidArgumentException('Invalid input parameters.');
        }

        $config = \SimpleSAML_Configuration::getInstance();

        $proxy = $config->getString('proxy', null);
        if ($proxy !== null) {
            if (!isset($context['http']['proxy'])) {
                $context['http']['proxy'] = $proxy;
            }
            if (!isset($context['http']['request_fulluri'])) {
                $context['http']['request_fulluri'] = true;
            }
            /*
             * If the remote endpoint over HTTPS uses the SNI extension (Server Name Indication RFC 4366), the proxy
             * could introduce a mismatch between the names in the Host: HTTP header and the SNI_server_name in TLS
             * negotiation (thanks to Cristiano Valli @ GARR-IDEM to have pointed this problem).
             * See: https://bugs.php.net/bug.php?id=63519
             * These controls will force the same value for both fields.
             * Marco Ferrante (marco@csita.unige.it), Nov 2012
             */
            if (preg_match('#^https#i', $url)
                && defined('OPENSSL_TLSEXT_SERVER_NAME')
                && OPENSSL_TLSEXT_SERVER_NAME
            ) {
                // extract the hostname
                $hostname = parse_url($url, PHP_URL_HOST);
                if (!empty($hostname)) {
                    $context['ssl'] = array(
                        'SNI_server_name' => $hostname,
                        'SNI_enabled'     => true,
                    );
                } else {
                    \SimpleSAML_Logger::warning('Invalid URL format or local URL used through a proxy');
                }
            }
        }

        $context = stream_context_create($context);
        $data = file_get_contents($url, false, $context);
        if ($data === false) {
            $error = error_get_last();
            throw new \SimpleSAML_Error_Exception('Error fetching '.var_export($url, true).':'.$error['message']);
        }

        // data and headers
        if ($getHeaders) {
            if (isset($http_response_header)) {
                $headers = array();
                foreach ($http_response_header as $h) {
                    if (preg_match('@^HTTP/1\.[01]\s+\d{3}\s+@', $h)) {
                        $headers = array(); // reset
                        $headers[0] = $h;
                        continue;
                    }
                    $bits = explode(':', $h, 2);
                    if (count($bits) === 2) {
                        $headers[strtolower($bits[0])] = trim($bits[1]);
                    }
                }
            } else {
                // no HTTP headers, probably a different protocol, e.g. file
                $headers = null;
            }
            return array($data, $headers);
        }

        return $data;
    }


    /**
     * This function parses the Accept-Language HTTP header and returns an associative array with each language and the
     * score for that language. If a language includes a region, then the result will include both the language with
     * the region and the language without the region.
     *
     * The returned array will be in the same order as the input.
     *
     * @return array An associative array with each language and the score for that language.
     *
     * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
     */
    public static function getAcceptLanguage()
    {
        if (!array_key_exists('HTTP_ACCEPT_LANGUAGE', $_SERVER)) {
            // no Accept-Language header, return an empty set
            return array();
        }

        $languages = explode(',', strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']));

        $ret = array();

        foreach ($languages as $l) {
            $opts = explode(';', $l);

            $l = trim(array_shift($opts)); // the language is the first element

            $q = 1.0;

            // iterate over all options, and check for the quality option
            foreach ($opts as $o) {
                $o = explode('=', $o);
                if (count($o) < 2) {
                    // skip option with no value
                    continue;
                }

                $name = trim($o[0]);
                $value = trim($o[1]);

                if ($name === 'q') {
                    $q = (float) $value;
                }
            }

            // remove the old key to ensure that the element is added to the end
            unset($ret[$l]);

            // set the quality in the result
            $ret[$l] = $q;

            if (strpos($l, '-')) {
                // the language includes a region part

                // extract the language without the region
                $l = explode('-', $l);
                $l = $l[0];

                // add this language to the result (unless it is defined already)
                if (!array_key_exists($l, $ret)) {
                    $ret[$l] = $q;
                }
            }
        }
        return $ret;
    }


    /**
     * Retrieve the base URL of the SimpleSAMLphp installation. The URL will always end with a '/'. For example:
     *      https://idp.example.org/simplesaml/
     *
     * @return string The absolute base URL for the SimpleSAMLphp installation.
     * @throws \SimpleSAML_Error_Exception If 'baseurlpath' has an invalid format.
     *
     * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
     */
    public static function getBaseURL()
    {
        $globalConfig = \SimpleSAML_Configuration::getInstance();
        $baseURL = $globalConfig->getString('baseurlpath', 'simplesaml/');

        if (preg_match('#^https?://.*/$#D', $baseURL, $matches)) {
            // full URL in baseurlpath, override local server values
            return $baseURL;
        } elseif (
            (preg_match('#^/?([^/]?.*/)$#D', $baseURL, $matches)) ||
            (preg_match('#^\*(.*)/$#D', $baseURL, $matches)) ||
            ($baseURL === '')
        ) {
            // get server values
            $protocol = 'http';
            $protocol .= (self::getServerHTTPS()) ? 's' : '';
            $protocol .= '://';

            $hostname = self::getServerHost();
            $port = self::getServerPort();
            $path = '/'.$globalConfig->getBaseURL();

            return $protocol.$hostname.$port.$path;
        } else {
            throw new \SimpleSAML_Error_Exception('Invalid value for \'baseurlpath\' in '.
                'config.php. Valid format is in the form: '.
                '[(http|https)://(hostname|fqdn)[:port]]/[path/to/simplesaml/]. '.
                'It must end with a \'/\'.');
        }
    }


    /**
     * Retrieve the first element of the URL path.
     *
     * @param boolean $trailingslash Whether to add a trailing slash to the element or not. Defaults to true.
     *
     * @return string The first element of the URL path, with an optional, trailing slash.
     *
     * @author Andreas Solberg, UNINETT AS <andreas.solberg@uninett.no>
     */
    public static function getFirstPathElement($trailingslash = true)
    {
        if (preg_match('|^/(.*?)/|', $_SERVER['SCRIPT_NAME'], $matches)) {
            return ($trailingslash ? '/' : '').$matches[1];
        }
        return '';
    }


    /**
     * Create a link which will POST data.
     *
     * @param string $destination The destination URL.
     * @param array  $data The name-value pairs which will be posted to the destination.
     *
     * @return string  A URL which can be accessed to post the data.
     * @throws \InvalidArgumentException If $destination is not a string or $data is not an array.
     *
     * @author Andjelko Horvat
     * @author Jaime Perez, UNINETT AS <jaime.perez@uninett.no>
     */
    public static function getPOSTRedirectURL($destination, $data)
    {
        if (!is_string($destination) || !is_array($data)) {
            throw new \InvalidArgumentException('Invalid input parameters.');
        }

        $config = \SimpleSAML_Configuration::getInstance();
        $allowed = $config->getBoolean('enable.http_post', false);

        if ($allowed && preg_match("#^http:#", $destination) && self::isHTTPS()) {
            // we need to post the data to HTTP
            $url = self::getSecurePOSTRedirectURL($destination, $data);
        } else { // post the data directly
            $session = \SimpleSAML_Session::getSessionFromRequest();
            $id = self::savePOSTData($session, $destination, $data);
            $url = \SimpleSAML_Module::getModuleURL('core/postredirect.php', array('RedirId' => $id));
        }

        return $url;
    }


    /**
     * Retrieve our own host.
     *
     * @return string The current host (with non-default ports included).
     *
     * @author Andreas Solberg, UNINETT AS <andreas.solberg@uninett.no>
     * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
     */
    public static function getSelfHost()
    {
        $url = self::getBaseURL();

        $start = strpos($url, '://') + 3;
        $length = strcspn($url, '/:', $start);

        return substr($url, $start, $length);
    }


    /**
     * Retrieve our own host together with the URL path. Please note this function will return the base URL for the
     * current SP, as defined in the global configuration.
     *
     * @return string The current host (with non-default ports included) plus the URL path.
     *
     * @author Andreas Solberg, UNINETT AS <andreas.solberg@uninett.no>
     * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
     */
    public static function getSelfHostWithPath()
    {
        $baseurl = explode("/", self::getBaseURL());
        $elements = array_slice($baseurl, 3 - count($baseurl), count($baseurl) - 4);
        $path = implode("/", $elements);
        return self::getSelfHost()."/".$path;
    }


    /**
     * Retrieve the current URL using the base URL in the configuration, if possible.
     *
     * This method will try to see if the current script is part of SimpleSAMLphp. In that case, it will use the
     * 'baseurlpath' configuration option to rebuild the current URL based on that. If the current script is NOT
     * part of SimpleSAMLphp, it will just return the current URL.
     *
     * Note that this method does NOT make use of the HTTP X-Forwarded-* set of headers.
     *
     * @return string The current URL, including query parameters.
     *
     * @author Andreas Solberg, UNINETT AS <andreas.solberg@uninett.no>
     * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
     * @author Jaime Perez, UNINETT AS <jaime.perez@uninett.no>
     */
    public static function getSelfURL()
    {
        $cfg = \SimpleSAML_Configuration::getInstance();
        $baseDir = $cfg->getBaseDir();
        $cur_path = realpath($_SERVER['SCRIPT_FILENAME']);
        // find the path to the current script relative to the www/ directory of SimpleSAMLphp
        $rel_path = str_replace($baseDir.'www'.DIRECTORY_SEPARATOR, '', $cur_path);
        // convert that relative path to an HTTP query
        $url_path = str_replace(DIRECTORY_SEPARATOR, '/', $rel_path);
        // find where the relative path starts in the current request URI
        $uri_pos = (!empty($url_path)) ? strpos($_SERVER['REQUEST_URI'], $url_path) : false;

        if ($cur_path == $rel_path || $uri_pos === false) {
            /*
             * We were accessed from an external script. This can happen in the following cases:
             *
             * - $_SERVER['SCRIPT_FILENAME'] points to a script that doesn't exist. E.g. functional testing. In this
             *   case, realpath() returns false and str_replace an empty string, so we compare them loosely.
             *
             * - The URI requested does not belong to a script in the www/ directory of SimpleSAMLphp. In that case,
             *   removing SimpleSAMLphp's base dir from the current path yields the same path, so $cur_path and
             *   $rel_path are equal.
             *
             * - The request URI does not match the current script. Even if the current script is located in the www/
             *   directory of SimpleSAMLphp, the URI does not contain its relative path, and $uri_pos is false.
             *
             * It doesn't matter which one of those cases we have. We just know we can't apply our base URL to the
             * current URI, so we need to build it back from the PHP environment.
             */
            $protocol = 'http';
            $protocol .= (self::getServerHTTPS()) ? 's' : '';
            $protocol .= '://';

            $hostname = self::getServerHost();
            $port = self::getServerPort();
            return $protocol.$hostname.$port.$_SERVER['REQUEST_URI'];
        }

        return self::getBaseURL().$rel_path.substr($_SERVER['REQUEST_URI'], $uri_pos + strlen($url_path));
    }


    /**
     * Retrieve the current URL using the base URL in the configuration, containing the protocol, the host and
     * optionally, the port number.
     *
     * @return string The current URL without path or query parameters.
     *
     * @author Andreas Solberg, UNINETT AS <andreas.solberg@uninett.no>
     * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
     */
    public static function getSelfURLHost()
    {
        $url = self::getSelfURL();
        $start = strpos($url, '://') + 3;
        $length = strcspn($url, '/', $start) + $start;
        return substr($url, 0, $length);
    }


    /**
     * Retrieve the current URL using the base URL in the configuration, without the query parameters.
     *
     * @return string The current URL, not including query parameters.
     *
     * @author Andreas Solberg, UNINETT AS <andreas.solberg@uninett.no>
     * @author Jaime Perez, UNINETT AS <jaime.perez@uninett.no>
     */
    public static function getSelfURLNoQuery()
    {
        $url = self::getSelfURL();
        $pos = strpos($url, '?');
        if (!$pos) {
            return $url;
        }
        return substr($url, 0, $pos);
    }


    /**
     * This function checks if we are using HTTPS as protocol.
     *
     * @return boolean True if the HTTPS is used, false otherwise.
     *
     * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
     * @author Jaime Perez, UNINETT AS <jaime.perez@uninett.no>
     */
    public static function isHTTPS()
    {
        return strpos(self::getSelfURL(), 'https://') === 0;
    }


    /**
     * Normalizes a URL to an absolute URL and validate it. In addition to resolving the URL, this function makes sure
     * that it is a link to an http or https site.
     *
     * @param string $url The relative URL.
     *
     * @return string An absolute URL for the given relative URL.
     * @throws \InvalidArgumentException If $url is not a string or a valid URL.
     *
     * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
     * @author Jaime Perez, UNINETT AS <jaime.perez@uninett.no>
     */
    public static function normalizeURL($url)
    {
        if (!is_string($url)) {
            throw new \InvalidArgumentException('Invalid input parameters.');
        }

        $url = self::resolveURL($url, self::getSelfURL());

        // verify that the URL is to a http or https site
        if (!preg_match('@^https?://@i', $url)) {
            throw new \InvalidArgumentException('Invalid URL: '.$url);
        }

        return $url;
    }


    /**
     * Parse a query string into an array.
     *
     * This function parses a query string into an array, similar to the way the builtin 'parse_str' works, except it
     * doesn't handle arrays, and it doesn't do "magic quotes".
     *
     * Query parameters without values will be set to an empty string.
     *
     * @param string $query_string The query string which should be parsed.
     *
     * @return array The query string as an associative array.
     * @throws \InvalidArgumentException If $query_string is not a string.
     *
     * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
     */
    public static function parseQueryString($query_string)
    {
        if (!is_string($query_string)) {
            throw new \InvalidArgumentException('Invalid input parameters.');
        }

        $res = array();
        if (empty($query_string)) {
            return $res;
        }

        foreach (explode('&', $query_string) as $param) {
            $param = explode('=', $param);
            $name = urldecode($param[0]);
            if (count($param) === 1) {
                $value = '';
            } else {
                $value = urldecode($param[1]);
            }
            $res[$name] = $value;
        }
        return $res;
    }


    /**
     * This function redirects to the specified URL without performing any security checks. Please, do NOT use this
     * function with user supplied URLs.
     *
     * This function will use the "HTTP 303 See Other" redirection if the current request used the POST method and the
     * HTTP version is 1.1. Otherwise, a "HTTP 302 Found" redirection will be used.
     *
     * The function will also generate a simple web page with a clickable  link to the target URL.
     *
     * @param string   $url The URL we should redirect to. This URL may include query parameters. If this URL is a
     * relative URL (starting with '/'), then it will be turned into an absolute URL by prefixing it with the absolute
     * URL to the root of the website.
     * @param string[] $parameters An array with extra query string parameters which should be appended to the URL. The
     * name of the parameter is the array index. The value of the parameter is the value stored in the index. Both the
     * name and the value will be urlencoded. If the value is NULL, then the parameter will be encoded as just the
     * name, without a value.
     *
     * @return void This function never returns.
     * @throws \InvalidArgumentException If $url is not a string or $parameters is not an array.
     *
     * @author Jaime Perez, UNINETT AS <jaime.perez@uninett.no>
     */
    public static function redirectTrustedURL($url, $parameters = array())
    {
        if (!is_string($url) || !is_array($parameters)) {
            throw new \InvalidArgumentException('Invalid input parameters.');
        }

        $url = self::normalizeURL($url);
        self::redirect($url, $parameters);
    }


    /**
     * This function redirects to the specified URL after performing the appropriate security checks on it.
     * Particularly, it will make sure that the provided URL is allowed by the 'redirect.trustedsites' directive in the
     * configuration.
     *
     * If the aforementioned option is not set or the URL does correspond to a trusted site, it performs a redirection
     * to it. If the site is not trusted, an exception will be thrown.
     *
     * @param string   $url The URL we should redirect to. This URL may include query parameters. If this URL is a
     * relative URL (starting with '/'), then it will be turned into an absolute URL by prefixing it with the absolute
     * URL to the root of the website.
     * @param string[] $parameters An array with extra query string parameters which should be appended to the URL. The
     * name of the parameter is the array index. The value of the parameter is the value stored in the index. Both the
     * name and the value will be urlencoded. If the value is NULL, then the parameter will be encoded as just the
     * name, without a value.
     *
     * @return void This function never returns.
     * @throws \InvalidArgumentException If $url is not a string or $parameters is not an array.
     *
     * @author Jaime Perez, UNINETT AS <jaime.perez@uninett.no>
     */
    public static function redirectUntrustedURL($url, $parameters = array())
    {
        if (!is_string($url) || !is_array($parameters)) {
            throw new \InvalidArgumentException('Invalid input parameters.');
        }

        $url = self::checkURLAllowed($url);
        self::redirect($url, $parameters);
    }


    /**
     * Resolve a (possibly relative) URL relative to a given base URL.
     *
     * This function supports these forms of relative URLs:
     * - ^\w+: Absolute URL. E.g. "http://www.example.com:port/path?query#fragment".
     * - ^// Same protocol. E.g. "//www.example.com:port/path?query#fragment"
     * - ^/ Same protocol and host. E.g. "/path?query#fragment".
     * - ^? Same protocol, host and path, replace query string & fragment. E.g. "?query#fragment".
     * - ^# Same protocol, host, path and query, replace fragment. E.g. "#fragment".
     * - The rest: Relative to the base path.
     *
     * @param string $url The relative URL.
     * @param string $base The base URL. Defaults to the base URL of this installation of SimpleSAMLphp.
     *
     * @return string An absolute URL for the given relative URL.
     * @throws \InvalidArgumentException If the base URL cannot be parsed into a valid URL, or the given parameters
     *     are not strings.
     *
     * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
     * @author Jaime Perez, UNINETT AS <jaime.perez@uninett.no>
     */
    public static function resolveURL($url, $base = null)
    {
        if ($base === null) {
            $base = self::getBaseURL();
        }

        if (!is_string($url) || !is_string($base)) {
            throw new \InvalidArgumentException('Invalid input parameters.');
        }

        if (!preg_match('/^((((\w+:)\/\/[^\/]+)(\/[^?#]*))(?:\?[^#]*)?)(?:#.*)?/', $base, $baseParsed)) {
            throw new \InvalidArgumentException('Unable to parse base url: '.$base);
        }

        $baseDir = dirname($baseParsed[5].'filename');
        $baseScheme = $baseParsed[4];
        $baseHost = $baseParsed[3];
        $basePath = $baseParsed[2];
        $baseQuery = $baseParsed[1];

        if (preg_match('$^\w+:$', $url)) {
            return $url;
        }

        if (substr($url, 0, 2) === '//') {
            return $baseScheme.$url;
        }

        $firstChar = substr($url, 0, 1);
        if ($firstChar === '/') {
            return $baseHost.$url;
        }
        if ($firstChar === '?') {
            return $basePath.$url;
        }
        if ($firstChar === '#') {
            return $baseQuery.$url;
        }

        // we have a relative path. Remove query string/fragment and save it as $tail
        $queryPos = strpos($url, '?');
        $fragmentPos = strpos($url, '#');
        if ($queryPos !== false || $fragmentPos !== false) {
            if ($queryPos === false) {
                $tailPos = $fragmentPos;
            } elseif ($fragmentPos === false) {
                $tailPos = $queryPos;
            } elseif ($queryPos < $fragmentPos) {
                $tailPos = $queryPos;
            } else {
                $tailPos = $fragmentPos;
            }

            $tail = substr($url, $tailPos);
            $dir = substr($url, 0, $tailPos);
        } else {
            $dir = $url;
            $tail = '';
        }

        $dir = System::resolvePath($dir, $baseDir);

        return $baseHost.$dir.$tail;
    }


    /**
     * Set a cookie.
     *
     * @param string      $name The name of the cookie.
     * @param string|NULL $value The value of the cookie. Set to NULL to delete the cookie.
     * @param array|NULL  $params Cookie parameters.
     * @param bool        $throw Whether to throw exception if setcookie() fails.
     *
     * @throws \InvalidArgumentException If any parameter has an incorrect type.
     * @throws \SimpleSAML\Error\CannotSetCookie If the headers were already sent and the cookie cannot be set.
     *
     * @author Andjelko Horvat
     * @author Jaime Perez, UNINETT AS <jaime.perez@uninett.no>
     */
    public static function setCookie($name, $value, $params = null, $throw = true)
    {
        if (!(is_string($name) && // $name must be a string
            (is_string($value) || is_null($value)) && // $value can be a string or null
            (is_array($params) || is_null($params)) && // $params can be an array or null
            is_bool($throw)) // $throw must be boolean
        ) {
            throw new \InvalidArgumentException('Invalid input parameters.');
        }

        $default_params = array(
            'lifetime' => 0,
            'expire'   => null,
            'path'     => '/',
            'domain'   => null,
            'secure'   => false,
            'httponly' => true,
            'raw'      => false,
        );

        if ($params !== null) {
            $params = array_merge($default_params, $params);
        } else {
            $params = $default_params;
        }

        // Do not set secure cookie if not on HTTPS
        if ($params['secure'] && !self::isHTTPS()) {
            if ($throw) {
                throw new \SimpleSAML\Error\CannotSetCookie(
                    'Setting secure cookie on plain HTTP is not allowed.',
                    \SimpleSAML\Error\CannotSetCookie::SECURE_COOKIE
                );
            }
            Logger::warning('Error setting cookie: setting secure cookie on plain HTTP is not allowed.');
            return;
        }

        if ($value === null) {
            $expire = time() - 365 * 24 * 60 * 60;
        } elseif (isset($params['expire'])) {
            $expire = $params['expire'];
        } elseif ($params['lifetime'] === 0) {
            $expire = 0;
        } else {
            $expire = time() + $params['lifetime'];
        }

        if ($params['raw']) {
            $success = @setrawcookie(
                $name,
                $value,
                $expire,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        } else {
            $success = @setcookie(
                $name,
                $value,
                $expire,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        if (!$success) {
            if ($throw) {
                throw new \SimpleSAML\Error\CannotSetCookie(
                    'Headers already sent.',
                    \SimpleSAML\Error\CannotSetCookie::HEADERS_SENT
                );
            }
            Logger::warning('Error setting cookie: headers already sent.');
        }
    }


    /**
     * Submit a POST form to a specific destination.
     *
     * This function never returns.
     *
     * @param string $destination The destination URL.
     * @param array  $data An associative array with the data to be posted to $destination.
     *
     * @throws \InvalidArgumentException If $destination is not a string or $data is not an array.
     *
     * @author Olav Morken, UNINETT AS <olav.morken@uninett.no>
     * @author Andjelko Horvat
     * @author Jaime Perez, UNINETT AS <jaime.perez@uninett.no>
     */
    public static function submitPOSTData($destination, $data)
    {
        if (!is_string($destination) || !is_array($data)) {
            throw new \InvalidArgumentException('Invalid input parameters.');
        }

        $config = \SimpleSAML_Configuration::getInstance();
        $allowed = $config->getBoolean('enable.http_post', false);

        if ($allowed && preg_match("#^http:#", $destination) && self::isHTTPS()) {
            // we need to post the data to HTTP
            self::redirect(self::getSecurePOSTRedirectURL($destination, $data));
        }

        $p = new \SimpleSAML_XHTML_Template($config, 'post.php');
        $p->data['destination'] = $destination;
        $p->data['post'] = $data;
        $p->show();
        exit(0);
    }
}
