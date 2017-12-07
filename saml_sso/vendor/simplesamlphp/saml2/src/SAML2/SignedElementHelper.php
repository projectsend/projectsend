<?php

/**
 * Helper class for processing signed elements.
 *
 * Can either be inherited from, or can be used by proxy.
 *
 * @package SimpleSAMLphp
 */
class SAML2_SignedElementHelper implements SAML2_SignedElement
{
    /**
     * The private key we should use to sign the message.
     *
     * The private key can be NULL, in which case the message is sent unsigned.
     *
     * @var XMLSecurityKey|NULL
     */
    private $signatureKey;

    /**
     * List of certificates that should be included in the message.
     *
     * @var array
     */
    private $certificates;

    /**
     * Available methods for validating this message.
     *
     * @var array
     */
    private $validators;

    /**
     * Initialize the helper class.
     *
     * @param DOMElement|NULL $xml The XML element which may be signed.
     */
    protected function __construct(DOMElement $xml = NULL)
    {
        $this->certificates = array();
        $this->validators = array();

        if ($xml === NULL) {
            return;
        }

        /* Validate the signature element of the message. */
        try {
            $sig = SAML2_Utils::validateElement($xml);

            if ($sig !== FALSE) {
                $this->certificates = $sig['Certificates'];
                $this->validators[] = array(
                    'Function' => array('SAML2_Utils', 'validateSignature'),
                    'Data' => $sig,
                );
            }

        } catch (Exception $e) {
            /* Ignore signature validation errors. */
        }
    }

    /**
     * Add a method for validating this element.
     *
     * This function is used for custom validation extensions
     *
     * @param callback $function The function which should be called.
     * @param mixed    $data     The data that should be included as the first parameter to the function.
     */
    public function addValidator($function, $data)
    {
        assert('is_callable($function)');

        $this->validators[] = array(
            'Function' => $function,
            'Data' => $data,
        );
    }

    /**
     * Validate this element against a public key.
     *
     * TRUE is returned on success, FALSE is returned if we don't have any
     * signature we can validate. An exception is thrown if the signature
     * validation fails.
     *
     * @param  XMLSecurityKey $key The key we should check against.
     * @return boolean        TRUE on success, FALSE when we don't have a signature.
     * @throws Exception
     */
    public function validate(XMLSecurityKey $key)
    {
        if (count($this->validators) === 0) {
            return FALSE;
        }

        $exceptions = array();

        foreach ($this->validators as $validator) {
            $function = $validator['Function'];
            $data = $validator['Data'];

            try {
                call_user_func($function, $data, $key);
                /* We were able to validate the message with this validator. */

                return TRUE;
            } catch (Exception $e) {
                $exceptions[] = $e;
            }
        }

        /* No validators were able to validate the message. */
        throw $exceptions[0];
    }

    /**
     * Retrieve the private key we should use to sign the message.
     *
     * @return XMLSecurityKey|NULL The key, or NULL if no key is specified.
     */
    public function getSignatureKey()
    {
        return $this->signatureKey;
    }

    /**
     * Set the private key we should use to sign the message.
     *
     * If the key is NULL, the message will be sent unsigned.
     *
     * @param XMLSecurityKey|NULL $signatureKey
     */
    public function setSignatureKey(XMLsecurityKey $signatureKey = NULL)
    {
        $this->signatureKey = $signatureKey;
    }

    /**
     * Set the certificates that should be included in the message.
     *
     * The certificates should be strings with the PEM encoded data.
     *
     * @param array $certificates An array of certificates.
     */
    public function setCertificates(array $certificates)
    {
        $this->certificates = $certificates;
    }

    /**
     * Retrieve the certificates that are included in the message.
     *
     * @return array An array of certificates.
     */
    public function getCertificates()
    {
        return $this->certificates;
    }

    /**
     * Retrieve certificates that sign this element.
     *
     * @return array Array with certificates.
     */
    public function getValidatingCertificates()
    {
        $ret = array();
        foreach ($this->certificates as $cert) {

            /* We have found a matching fingerprint. */
            $pemCert = "-----BEGIN CERTIFICATE-----\n" .
                chunk_split($cert, 64) .
                "-----END CERTIFICATE-----\n";

            /* Extract the public key from the certificate for validation. */
            $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type'=>'public'));
            $key->loadKey($pemCert);

            try {
                /* Check the signature. */
                if ($this->validate($key)) {
                    $ret[] = $cert;
                }
            } catch (Exception $e) {
                /* This certificate does not sign this element. */
            }
        }

        return $ret;
    }

    /**
     * Sign the given XML element.
     *
     * @param DOMElement      $root         The element we should sign.
     * @param DOMElement|NULL $insertBefore The element we should insert the signature node before.
     * @return DOMElement|NULL
     */
    protected function signElement(DOMElement $root, DOMElement $insertBefore = NULL)
    {
        if ($this->signatureKey === NULL) {
            /* We cannot sign this element. */

            return NULL;
        }

        SAML2_Utils::insertSignature($this->signatureKey, $this->certificates, $root, $insertBefore);

        return $root;
    }

}
