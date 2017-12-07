<?php

/**
 * Interface to a SAML 2 element which may be signed.
 *
 * @package SimpleSAMLphp
 */
interface SAML2_SignedElement
{
    /**
     * Validate this element against a public key.
     *
     * If no signature is present, FALSE is returned. If a signature is present,
     * but cannot be verified, an exception will be thrown.
     *
     * @param  XMLSecurityKey $key The key we should check against.
     * @return boolean        TRUE if successful, FALSE if we don't have a signature that can be verified.
     */
    public function validate(XMLSecurityKey $key);

    /**
     * Set the certificates that should be included in the element.
     *
     * The certificates should be strings with the PEM encoded data.
     *
     * @param array $certificates An array of certificates.
     */
    public function setCertificates(array $certificates);

    /**
     * Retrieve the certificates that are included in the element (if any).
     *
     * @return array An array of certificates.
     */
    public function getCertificates();

    /**
     * Retrieve the private key we should use to sign the element.
     *
     * @return XMLSecurityKey|NULL The key, or NULL if no key is specified.
     */
    public function getSignatureKey();

    /**
     * Set the private key we should use to sign the element.
     *
     * If the key is NULL, the message will be sent unsigned.
     *
     * @param XMLSecurityKey|NULL $signatureKey
     */
    public function setSignatureKey(XMLsecurityKey $signatureKey = NULL);
}
