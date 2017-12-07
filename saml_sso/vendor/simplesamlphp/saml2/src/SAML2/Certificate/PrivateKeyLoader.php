<?php

class SAML2_Certificate_PrivateKeyLoader
{
    /**
     * Loads a private key based on the configuration given.
     *
     * @param SAML2_Configuration_PrivateKey $key
     *
     * @return SAML2_Certificate_PrivateKey
     */
    public function loadPrivateKey(SAML2_Configuration_PrivateKey $key)
    {
        $privateKey = SAML2_Utilities_File::getFileContents($key->getFilePath());

        return SAML2_Certificate_PrivateKey::create($privateKey, $key->getPassPhrase());
    }

    /**
     * @param SAML2_Configuration_DecryptionProvider $identityProvider
     * @param SAML2_Configuration_DecryptionProvider $serviceProvider
     *
     * @return SAML2_Utilities_ArrayCollection
     * @throws Exception
     */
    public function loadDecryptionKeys(
        SAML2_Configuration_DecryptionProvider $identityProvider,
        SAML2_Configuration_DecryptionProvider $serviceProvider
    ) {
        $decryptionKeys = new SAML2_Utilities_ArrayCollection();

        $senderSharedKey = $identityProvider->getSharedKey();
        if ($senderSharedKey) {
            $key = new XMLSecurityKey(XMLSecurityKey::AES128_CBC);
            $key->loadKey($senderSharedKey);
            $decryptionKeys->add($key);

            return $decryptionKeys;
        }

        $newPrivateKey = $serviceProvider->getPrivateKey(SAML2_Configuration_PrivateKey::NAME_NEW);
        if ($newPrivateKey instanceof SAML2_Configuration_PrivateKey) {
            $loadedKey = $this->loadPrivateKey($newPrivateKey);
            $decryptionKeys->add($this->convertPrivateKeyToRsaKey($loadedKey));
        }

        $privateKey = $serviceProvider->getPrivateKey(SAML2_Configuration_PrivateKey::NAME_DEFAULT, TRUE);
        $loadedKey  = $this->loadPrivateKey($privateKey);
        $decryptionKeys->add($this->convertPrivateKeyToRsaKey($loadedKey));

        return $decryptionKeys;
    }

    /**
     * @param SAML2_Certificate_PrivateKey $privateKey
     *
     * @return XMLSecurityKey
     * @throws Exception
     */
    private function convertPrivateKeyToRsaKey(SAML2_Certificate_PrivateKey $privateKey)
    {
        $key        = new XMLSecurityKey(XMLSecurityKey::RSA_1_5, array('type' => 'private'));
        $passphrase = $privateKey->getPassphrase();
        if ($passphrase) {
            $key->passphrase = $passphrase;
        }

        $key->loadKey($privateKey->getKeyAsString());

        return $key;
    }
}
