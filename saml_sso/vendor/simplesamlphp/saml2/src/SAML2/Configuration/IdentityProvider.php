<?php

/**
 * Basic configuration wrapper
 */
class SAML2_Configuration_IdentityProvider extends SAML2_Configuration_ArrayAdapter implements
    SAML2_Configuration_CertificateProvider,
    SAML2_Configuration_DecryptionProvider,
    SAML2_Configuration_EntityIdProvider
{
    public function getKeys()
    {
        return $this->get('keys');
    }

    public function getCertificateData()
    {
        return $this->get('certificateData');
    }

    public function getCertificateFile()
    {
        return $this->get('certificateFile');
    }

    public function getCertificateFingerprints()
    {
        return $this->get('certificateFingerprints');
    }

    public function isAssertionEncryptionRequired()
    {
        return $this->get('assertionEncryptionEnabled');
    }

    public function getSharedKey()
    {
        return $this->get('sharedKey');
    }

    public function hasBase64EncodedAttributes()
    {
        return $this->get('base64EncodedAttributes');
    }

    public function getPrivateKey($name, $required = FALSE)
    {
        $privateKeys = $this->get('privateKeys');
        $key = array_filter($privateKeys, function (SAML2_Configuration_PrivateKey $key) use ($name) {
            return $key->getName() === $name;
        });

        $keyCount = count($key);
        if ($keyCount !== 1 && $required) {
            throw new \RuntimeException(sprintf(
                'Attempted to get privateKey by name "%s", found "%d" keys, where only one was expected. Please '
                . 'verify that your configuration is correct',
                $name,
                $keyCount
            ));
        }

        if (!$keyCount) {
            return NULL;
        }

        return array_pop($key);
    }

    public function getBlacklistedAlgorithms()
    {
        return $this->get('blacklistedEncryptionAlgorithms');
    }

    /**
     * @return null|string
     */
    public function getEntityId()
    {
        return $this->get('entityId');
    }
}
