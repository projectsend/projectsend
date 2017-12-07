<?php

/**
 * Specific Certificate Key.
 */
class SAML2_Certificate_X509 extends SAML2_Certificate_Key
{
    /**
     * @var SAML2_Certificate_Fingerprint
     */
    private $fingerprint;

    public static function createFromCertificateData($certificateContents)
    {
        $data = array(
            'encryption'      => TRUE,
            'signing'         => TRUE,
            'type'            => 'X509Certificate',
            'X509Certificate' => $certificateContents
        );

        return new self($data);
    }

    /**
     * {@inheritdoc} Best place to ensure the logic is encapsulated in a single place
     */
    public function offsetSet($offset, $value)
    {
        if ($offset === 'X509Certificate') {
            $value = preg_replace('~\s+~', '', $value);
        }

        parent::offsetSet($offset, $value);
    }

    /**
     * Get the certificate representation
     *
     * @return string
     */
    public function getCertificate()
    {
        return "-----BEGIN CERTIFICATE-----\n"
                . chunk_split($this->keyData['X509Certificate'], 64)
                . "-----END CERTIFICATE-----\n";
    }

    /**
     * @return SAML2_Certificate_Fingerprint
     */
    public function getFingerprint()
    {
        if (isset($this->fingerprint)) {
            return $this->fingerprint;
        }

        $fingerprint = strtolower(sha1(base64_decode($this->keyData['X509Certificate'])));

        return $this->fingerprint = new SAML2_Certificate_Fingerprint($fingerprint);
    }
}
