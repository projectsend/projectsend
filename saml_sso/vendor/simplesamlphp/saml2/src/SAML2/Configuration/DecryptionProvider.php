<?php

interface SAML2_Configuration_DecryptionProvider
{
    /**
     * @return null|bool
     */
    public function isAssertionEncryptionRequired();

    /**
     * @return null|string
     */
    public function getSharedKey();

    /**
     * @param string  $name     the name of the private key
     * @param boolean $required whether or not the private key must exist
     *
     * @return mixed
     */
    public function getPrivateKey($name, $required = FALSE);

    /**
     * @return array
     */
    public function getBlacklistedAlgorithms();
}
