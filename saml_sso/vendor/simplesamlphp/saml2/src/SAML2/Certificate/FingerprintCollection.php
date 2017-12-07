<?php

/**
 * Simple collection object for transporting keys
 */
class SAML2_Certificate_FingerprintCollection extends SAML2_Utilities_ArrayCollection
{
    /**
     * Add a key to the collection
     *
     * @param SAML2_Certificate_Fingerprint $fingerprint
     */
    public function add($fingerprint)
    {
        if (!$fingerprint instanceof SAML2_Certificate_Fingerprint) {
            throw SAML2_Exception_InvalidArgumentException::invalidType(
                'SAML2_Certificate_Fingerprint ',
                $fingerprint
            );
        }

        parent::add($fingerprint);
    }

    /**
     * @param SAML2_Certificate_Fingerprint $otherFingerprint
     *
     * @return bool
     */
    public function contains(SAML2_Certificate_Fingerprint $otherFingerprint)
    {
        foreach ($this->elements as $fingerprint) {
            /** @var SAML2_Certificate_Fingerprint $fingerprint */
            if ($fingerprint->equals($otherFingerprint)) {
                return TRUE;
            }
        }

        return FALSE;
    }
}
