<?php

/**
 * Simple representation of the fingerprint of a certificate
 */
class SAML2_Certificate_Fingerprint
{
    /**
     * @var string
     */
    private $contents;

    /**
     * @param string $fingerPrint
     */
    public function __construct($fingerPrint)
    {
        if (!is_string($fingerPrint)) {
            throw SAML2_Exception_InvalidArgumentException::invalidType('string', $fingerPrint);
        }

        $this->contents = $fingerPrint;
    }

    /**
     * Get the raw, unmodified fingerprint value.
     *
     * @return string
     */
    public function getRaw()
    {
        return $this->contents;
    }

    /**
     * @return string
     */
    public function getNormalized()
    {
        return strtolower(str_replace(':', '', $this->contents));
    }

    /**
     * @param SAML2_Certificate_Fingerprint $fingerprint
     *
     * @return bool
     */
    public function equals(SAML2_Certificate_Fingerprint $fingerprint)
    {
        return $this->getNormalized() === $fingerprint->getNormalized();
    }
}
