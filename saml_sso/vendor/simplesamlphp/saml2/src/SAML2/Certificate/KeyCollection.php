<?php

/**
 * Simple collection object for transporting keys
 */
class SAML2_Certificate_KeyCollection extends SAML2_Utilities_ArrayCollection
{
    /**
     * Add a key to the collection
     *
     * @param SAML2_Certificate_Key $key
     */
    public function add($key)
    {
        if (!$key instanceof SAML2_Certificate_Key) {
            throw SAML2_Exception_InvalidArgumentException::invalidType(
                'SAML2_Certificate_Key',
                $key
            );
        }

        parent::add($key);
    }
}
