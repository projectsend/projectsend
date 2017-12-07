<?php

/**
 * This file implements an script which can be used to authenticate users with Auth MemCookie.
 * See: http://authmemcookie.sourceforge.net/
 *
 * The configuration for this script is stored in config/authmemcookie.php.
 *
 * The file extra/auth_memcookie.conf contains an example of how Auth Memcookie can be configured
 * to use SimpleSAMLphp.
 */

require_once('_include.php');

try {
    // load SimpleSAMLphp configuration
    $globalConfig = SimpleSAML_Configuration::getInstance();

    // check if this module is enabled
    if (!$globalConfig->getBoolean('enable.authmemcookie', false)) {
        throw new SimpleSAML_Error_Error('NOACCESS');
    }

    // load Auth MemCookie configuration
    $amc = SimpleSAML_AuthMemCookie::getInstance();

    $sourceId = $amc->getAuthSource();
    $s = new SimpleSAML_Auth_Simple($sourceId);

    // check if the user is authorized. We attempt to authenticate the user if not
    $s->requireAuth();

    // generate session id and save it in a cookie
    $sessionID = SimpleSAML\Utils\Random::generateID();
    $cookieName = $amc->getCookieName();
    \SimpleSAML\Utils\HTTP::setCookie($cookieName, $sessionID);

    // generate the authentication information
    $attributes = $s->getAttributes();

    $authData = array();

    // username
    $usernameAttr = $amc->getUsernameAttr();
    if (!array_key_exists($usernameAttr, $attributes)) {
        throw new Exception(
            "The user doesn't have an attribute named '".$usernameAttr.
            "'. This attribute is expected to contain the username."
        );
    }
    $authData['UserName'] = $attributes[$usernameAttr];

    // groups
    $groupsAttr = $amc->getGroupsAttr();
    if ($groupsAttr !== null) {
        if (!array_key_exists($groupsAttr, $attributes)) {
            throw new Exception(
                "The user doesn't have an attribute named '".$groupsAttr.
                "'. This attribute is expected to contain the groups the user is a member of."
            );
        }
        $authData['Groups'] = $attributes[$groupsAttr];
    } else {
        $authData['Groups'] = array();
    }

    $authData['RemoteIP'] = $_SERVER['REMOTE_ADDR'];

    foreach ($attributes as $n => $v) {
        $authData['ATTR_'.$n] = $v;
    }

    // store the authentication data in the memcache server
    $data = '';
    foreach ($authData as $name => $values) {
        if (is_array($values)) {
            foreach ($values as $i => $value) {
                if (!is_a($value, 'DOMNodeList')) {
                    continue;
                }
                /* @var \DOMNodeList $value */
                if ($value->length === 0) {
                    continue;
                }
                $values[$i] = new SAML2_XML_saml_AttributeValue($value->item(0)->parentNode);
            }
            $values = implode(':', $values);
        }
        $data .= $name.'='.$values."\r\n";
    }

    $memcache = $amc->getMemcache();
    $expirationTime = $s->getAuthData('Expire');
    $memcache->set($sessionID, $data, 0, $expirationTime);

    // register logout handler
    $session = SimpleSAML_Session::getSessionFromRequest();
    $session->registerLogoutHandler($sourceId, 'SimpleSAML_AuthMemCookie', 'logoutHandler');

    // redirect the user back to this page to signal that the login is completed
    \SimpleSAML\Utils\HTTP::redirectTrustedURL(\SimpleSAML\Utils\HTTP::getSelfURL());
} catch (Exception $e) {
    throw new SimpleSAML_Error_Error('CONFIG', $e);
}
