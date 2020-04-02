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
      'X509Certificate' => 'MIIC8DCCAdigAwIBAgIQENgDY/grM5NOr76uDvKDUzANBgkqhkiG9w0BAQsFADA0MTIwMAYDVQQDEylNaWNyb3NvZnQgQXp1cmUgRmVkZXJhdGVkIFNTTyBDZXJ0aWZpY2F0ZTAeFw0yMDAzMTgxNDAwMTlaFw0yMzAzMTgxNDAwMTlaMDQxMjAwBgNVBAMTKU1pY3Jvc29mdCBBenVyZSBGZWRlcmF0ZWQgU1NPIENlcnRpZmljYXRlMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA13PGuE5Xu+YAwtaEO31CiamUUcWvV/sgTCtniwWLukbbxs8n0tFns7ihT84V/9r3XhJTbn05YRn/Wafcwakr8hSnuhM/HzZ63LYcHQkAuwErojfLM1YGXVO486xmIJ8e7XyvcMiKhUin+WZbFtRWuga8MF4YMQtgbUOqfwT2kz3WEI8a/i9Yfy90s/yaQZbUw9h1fnZPsMzbUVxCiMpb3gq2Kcl5RPe/dTT9ZaWCXJQeAzJbhJxOTI0AVY36nn5d5mj7oPfrYNQjD+nusHxaH7pAkpuWgo0QVGuhWRpUugOm2/EwBvJvV3XMTMorxKhdM/M7Fu3sWKElMraKnGRJmQIDAQABMA0GCSqGSIb3DQEBCwUAA4IBAQADGbyLV7NQpLJmKtPnqq0KLgivsMyJEDAakpFon5vRq39GWv0B/AOAGHjSVa3/Lz6cdVDSGWeTSnn/dgIU2TJUHqWxRKYpRSNaGcLsCqk9Df0ieiGhF7I81k7mNOoCL9ut0SUqsghO2Rzyz2fTuemRZOzY1G96AIa+H2x21Gu0Iog4OTu64BxWfM5h4IVCv/i13cFMoSziBGdlpafEXYw6lpzCFBRWtoS0ofOxbS9O9gT6mOlVj4xt29Nmrw+rSvjdaas1Doh6dZ5EJ7r3InxLR0eTUIoMEf7uSHmQkDGLPgc1IWWTGdarAkhThxJWwhANL7t4gvRP21JEK6JqtGUK',
    ),
  ),
);

