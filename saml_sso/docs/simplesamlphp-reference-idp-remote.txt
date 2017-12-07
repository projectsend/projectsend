IdP remote metadata reference
=============================

<!-- {{TOC}} -->

This is a reference for metadata options available for `metadata/saml20-idp-remote.php` and `metadata/shib13-idp-remote.php`. Both files have the following format:

    <?php
    /* The index of the array is the entity ID of this IdP. */
    $metadata['entity-id-1'] = array(
        /* Configuration options for the first IdP. */
    );
    $metadata['entity-id-2'] = array(
        /* Configuration options for the second IdP. */
    );
    /* ... */


Common options
--------------

The following options are common between both the SAML 2.0 protocol and Shibboleth 1.3 protocol:

`authproc`
:   Used to manipulate attributes, and limit access for each IdP. See the [authentication processing filter manual](simplesamlphp-authproc).

`base64attributes`
:   Whether attributes received from this IdP should be base64 decoded. The default is `FALSE`.

`certData`
:   The base64 encoded certificate for this IdP. This is an alternative to storing the certificate in a file on disk and specifying the filename in the `certificate`-option.

`certFingerprint`
:   If you only need to validate signatures received from this IdP, you can specify the certificate fingerprint instead of storing the full certificate. To obtain this, you can enter a bogus value, and attempt to log in. You will then receive an error message with the correct fingerprint.

:   It is also possible to add an array of valid fingerprints, where any fingerprints in that array is accepted as valid. This can be used to update the certificate of the IdP without having to update every SP at that exact time. Instead, one can update the SPs with the new fingerprint, and only update the certificate after every SP is updated.

`certificate`
:   The file with the certificate for this IdP. The path is relative to the `cert`-directory.

`description`
:   A description of this IdP. Will be used by various modules when they need to show a description of the IdP to the user.

:   This option can be translated into multiple languages in the same way as the `name`-option.

`icon`
:   A logo which will be shown next to this IdP in the discovery service.

`OrganizationName`
:   The name of the organization responsible for this SPP.
    This name does not need to be suitable for display to end users.

:   This option can be translated into multiple languages by specifying the value as an array of language-code to translated name:

        'OrganizationName' => array(
            'en' => 'Example organization',
            'no' => 'Eksempel organisation',
        ),

:   *Note*: If you specify this option, you must also specify the `OrganizationURL` option.

`OrganizationDisplayName`
:   The name of the organization responsible for this IdP.
    This name must be suitable for display to end users.
    If this option isn't specified, `OrganizationName` will be used instead.

:   This option can be translated into multiple languages by specifying the value as an array of language-code to translated name.

:   *Note*: If you specify this option, you must also specify the `OrganizationName` option.

`OrganizationURL`
:   A URL the end user can access for more information about the organization.

:   This option can be translated into multiple languages by specifying the value as an array of language-code to translated URL.

:   *Note*: If you specify this option, you must also specify the `OrganizationName` option.

`name`
:   The name of this IdP. Will be used by various modules when they need to show a name of the SP to the user.

:   If this option is unset, the organization name will be used instead (if it is available).

:   This option can be translated into multiple languages by specifying the value as an array of language-code to translated name:

        'name' => array(
            'en' => 'A service',
            'no' => 'En tjeneste',
        ),

`SingleSignOnService`
:   Endpoint URL for sign on. You should obtain this from the IdP. For SAML 2.0, SimpleSAMLphp will use the HTTP-Redirect binding when contacting this endpoint.

:   The value of this option is specified in one of several [endpoint formats](./simplesamlphp-metadata-endpoints).


SAML 2.0 options
----------------

The following SAML 2.0 options are available:

`encryption.blacklisted-algorithms`
:   Blacklisted encryption algorithms. This is an array containing the algorithm identifiers.

:   Note that this option also exists in the SP configuration. This
    entry in the IdP-remote metadata overrides the option in the
    [SP configuration](./saml:sp).

:   The RSA encryption algorithm with PKCS#1 v1.5 padding is blacklisted by default for security reasons. Any assertions
    encrypted with this algorithm will therefore fail to decrypt. You can override this limitation by defining an empty
    array in this option (or blacklisting any other algorithms not including that one). However, it is strongly
    discouraged to do so. For your own safety, please include the string 'http://www.w3.org/2001/04/xmlenc#rsa-1_5' if
    you make use of this option.

`hide.from.discovery`
:   Whether to hide hide this IdP from the local discovery or not. Set to true to hide it. Defaults to false.

`nameid.encryption`
:   Whether NameIDs sent to this IdP should be encrypted. The default
    value is `FALSE`.

:   Note that this option also exists in the SP configuration. This
    entry in the IdP-remote metadata overrides the option in the
    [SP configuration](./saml:sp).

`sign.authnrequest`
:   Whether to sign authentication requests sent to this IdP.

:   Note that this option also exists in the SP configuration.
    This value in the IdP remote metadata overrides the value in the SP configuration.

`sign.logout`
:   Whether to sign logout messages sent to this IdP.

:   Note that this option also exists in the SP configuration.
    This value in the IdP remote metadata overrides the value in the SP configuration.

`SingleLogoutService`
:   Endpoint URL for logout requests and responses. You should obtain this from the IdP. Users who log out from your service is redirected to this URL with the LogoutRequest using HTTP-REDIRECT.

:   The value of this option is specified in one of several [endpoint formats](./simplesamlphp-metadata-endpoints).

`SingleLogoutServiceResponse`
:   Endpoint URL for logout responses. Overrides the `SingleLogoutService`-option for responses.

`signature.algorithm`
:   The algorithm to use when signing any message sent to this specific identity provider. Defaults to RSA-SHA1.
:   Note that this option also exists in the SP configuration.
    This value in the IdP remote metadata overrides the value in the SP configuration.
:   Possible values:

    * `http://www.w3.org/2000/09/xmldsig#rsa-sha1`
       *Note*: the use of SHA1 is **deprecated** and will be disallowed in the future.
    * `http://www.w3.org/2001/04/xmldsig-more#rsa-sha256`
    * `http://www.w3.org/2001/04/xmldsig-more#rsa-sha384`
    * `http://www.w3.org/2001/04/xmldsig-more#rsa-sha512`

`SPNameQualifier`
:   This corresponds to the SPNameQualifier in the SAML 2.0 specification. It allows to give subjects a SP specific namespace. This option is rarely used, so if you don't need it, leave it out. When left out, SimpleSAMLphp assumes the entityID of your SP as the SPNameQualifier.

`validate.logout`
:   Whether we require signatures on logout messages sent from this IdP.

:   Note that this option also exists in the SP configuration.
    This value in the IdP remote metadata overrides the value in the SP configuration.


### Decrypting assertions

It is possible to decrypt the assertions received from an IdP. Currently the only algorithm supported is `AES128_CBC` or `RIJNDAEL_128`.

There are two modes of encryption supported by SimpleSAMLphp. One is symmetric encryption, in which case both the SP and the IdP needs to share a key. The other mode is the use of public key encryption. In that mode, the public key of the SP is extracted from the certificate of the SP.

`assertion.encryption`
:   Whether assertions received from this IdP must be encrypted. The default value is `FALSE`.
    If this option is set to `TRUE`, assertions from the IdP must be encrypted.
    Unencrypted assertions will be rejected.

:   Note that this option overrides the option with the same name in the SP configuration.

`sharedkey`
:   Symmetric key which should be used for decryption. This should be a 128-bit key. If this option is not specified, public key encryption will be used instead.


### Fields for signing and validating messages

SimpleSAMLphp only signs authentication responses by default. Signing of authentication request, logout requests and logout responses can be enabled by setting the `redirect.sign` option. Validation of received messages can be enabled by the `redirect.validate` option.

`redirect.sign`
:   Whether authentication request, logout requests and logout responses sent to this IdP should be signed. The default is `FALSE`.

`redirect.validate`
:   Whether logout requests and logout responses received from this IdP should be validated. The default is `FALSE`.

**Example: Configuration for validating messages**

    'redirect.validate' => TRUE,
    'certificate' => 'example.org.crt',


Shibboleth 1.3 options
----------------------

`caFile`
:   Alternative to specifying a certificate. Allows you to specify a file with root certificates, and responses from the service be validated against these certificates. Note that SimpleSAMLphp doesn't support chains with any itermediate certificates between the root and the certificate used to sign the response. Support for PKIX in SimpleSAMLphp is experimental, and we encourage users to not rely on PKIX for validation of signatures; for background information review [the SAML 2.0 Metadata Interoperability Profile](http://docs.oasis-open.org/security/saml/Post2.0/sstc-metadata-iop-cd-01.pdf).

`saml1.useartifact`
:   Request that the IdP returns the result to the artifact binding.
    The default is to use the POST binding, set this option to TRUE to use the artifact binding instead.

:   This option can be set for all IdPs connected to a SP by setting it in the entry for the SP in `config/authsources.php`.

:   *Note*: This option only works with the `saml:SP` authentication source.


Calculating the fingerprint of a certificate
--------------------------------------------

If you have obtained a certificate file, and want to calculate the fingerprint of the file, you can use the `openssl` command:

    $ openssl x509 -noout -fingerprint -in "example.org.crt"
    SHA1 Fingerprint=AF:E7:1C:28:EF:74:0B:C8:74:25:BE:13:A2:26:3D:37:97:1D:A1:F9

In this case, the certFingerprint option should be set to `AF:E7:1C:28:EF:74:0B:C8:74:25:BE:13:A2:26:3D:37:97:1D:A1:F9`.
