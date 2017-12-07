<?php
/**
 * SAML 2.0 remote IdP metadata for SimpleSAMLphp.
 *
 * Remember to remove the IdPs you don't use from this file.
 *
 * See: https://simplesamlphp.org/docs/stable/simplesamlphp-reference-idp-remote 
 */

/*
 * Guest IdP. allows users to sign up and register. Great for testing!
 */
$metadata['https://openidp.feide.no'] = array(
	'name' => array(
		'en' => 'Feide OpenIdP - guest users',
		'no' => 'Feide Gjestebrukere',
	),
	'description'          => 'Here you can login with your account on Feide RnD OpenID. If you do not already have an account on this identity provider, you can create a new one by following the create new account link and follow the instructions.',

	'SingleSignOnService'  => 'https://openidp.feide.no/simplesaml/saml2/idp/SSOService.php',
	'SingleLogoutService'  => 'https://openidp.feide.no/simplesaml/saml2/idp/SingleLogoutService.php',
	'certFingerprint'      => 'c9ed4dfb07caf13fc21e0fec1572047eb8a7a4cb'
);

/* For windows azure */

$metadata['https://sts.windows.net/487ea2d6-7ea0-4a4b-8773-6f4833ed2108/'] = array (
  'entityid' => 'https://sts.windows.net/487ea2d6-7ea0-4a4b-8773-6f4833ed2108/',
  'contacts' => 
  array (
  ),
  'metadata-set' => 'saml20-idp-remote',
  'SingleSignOnService' => 
  array (
    0 => 
    array (
      'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
      'Location' => 'https://login.microsoftonline.com/487ea2d6-7ea0-4a4b-8773-6f4833ed2108/saml2',
    ),
    1 => 
    array (
      'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
      'Location' => 'https://login.microsoftonline.com/487ea2d6-7ea0-4a4b-8773-6f4833ed2108/saml2',
    ),
  ),
  'SingleLogoutService' => 
  array (
    0 => 
    array (
      'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
      'Location' => 'https://login.microsoftonline.com/487ea2d6-7ea0-4a4b-8773-6f4833ed2108/saml2',
    ),
  ),
  'ArtifactResolutionService' => 
  array (
  ),
  'NameIDFormats' => 
  array (
  ),
  'keys' => 
  array (
    0 => 
    array (
      'encryption' => false,
      'signing' => true,
      'type' => 'X509Certificate',
      'X509Certificate' => 'MIIC8DCCAdigAwIBAgIQTRc8nUqQPrBKEa6i8CgrrDANBgkqhkiG9w0BAQsFADA0MTIwMAYDVQQDEylNaWNyb3NvZnQgQXp1cmUgRmVkZXJhdGVkIFNTTyBDZXJ0aWZpY2F0ZTAeFw0xNzExMTMxNTE2MjZaFw0yMDExMTMxNTE2MjVaMDQxMjAwBgNVBAMTKU1pY3Jvc29mdCBBenVyZSBGZWRlcmF0ZWQgU1NPIENlcnRpZmljYXRlMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAyKoLa692Rz9aKb0yC+UU2uBR1/Ec9gdE/gyHeYJzLNth76pf1/LLmXATC43DUJl3/ZukqhCnMbhjzJ/aY0grAx5In8T155hQUPV7/xXFo6kuQyCNIgXjwTNYvX8cjRjidXv3dBsLWFT7+VgWuKKWjW2oq6El7etFrO5NXsvfHXLLePqJige06rVQHiixiBloAp4IyYUXE5D7HHgWmINDP15+leSzpCmkmW/JSKwSyDpbNnkKfNHi9ixlf8vjetYzm88zTHGsYaW+WyhJFufLQr58SCuz+ZrC9YMfUexgqWbBcNOGXo0uszxxrCY9fqAJa8qQLUJyqR1UsLjvkS5RDwIDAQABMA0GCSqGSIb3DQEBCwUAA4IBAQBLIWwFHthkIuJQ7GbeasD2xHmi6EwrcBhVFckC+38mYM0Xfod3tD48modqExPcqBzZ9ZvdUlY6VinYecj5Lj0O6Wv66U6bkYeGReIkbpM0vV+VSQSyXNpKSPVRSiseHCx7hNTgDjGvroheBk0xdUBDFgdH1TJVR+5Jmwkwi6k4NYKKxWk4eMrRQ+G6ymq8nyrrUTtgymqlurRb0cMlPs3pV/xZqKiqfWjrj+ZrsqCU2Ly+A+gqMfHLzQEq9TXnqmhifOcbr6f2lsLeHeKHWqs6B27CRKEfybKOQW2g90tFMHlPzHh2sfp/xI5xHUAect56d1BJsc9YEpDETDBO7Zmv',
    ),
  ),
);

