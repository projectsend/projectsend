<?php

class SAML2_Certificate_FingerprintLoader
{
    /**
     * Static method mainly for BC, should be replaced with DI.
     *
     * @param SAML2_Configuration_CertificateProvider $configuration
     *
     * @return SAML2_Certificate_FingerprintCollection
     */
    public static function loadFromConfiguration(SAML2_Configuration_CertificateProvider $configuration)
    {
        $loader = new self();

        return $loader->loadFingerprints($configuration);
    }

    /**
     * Loads the fingerprints from a configurationValue
     *
     * @param SAML2_Configuration_CertificateProvider $configuration
     *
     * @return SAML2_Certificate_FingerprintCollection
     */
    public function loadFingerprints(SAML2_Configuration_CertificateProvider $configuration)
    {
        $fingerprints = $configuration->getCertificateFingerprints();
        if (!is_array($fingerprints) && !$fingerprints instanceof \Traversable) {
            throw SAML2_Exception_InvalidArgumentException::invalidType(
                'array or instanceof \Traversable',
                $fingerprints
            );
        }

        $collection = new SAML2_Certificate_FingerprintCollection();
        foreach ($fingerprints as $fingerprint) {
            if (!is_string($fingerprint) && !(is_object($fingerprint) && method_exists($fingerprint, '__toString'))) {
                throw SAML2_Exception_InvalidArgumentException::invalidType(
                    'fingerprint as string or object that can be casted to string',
                    $fingerprint
                );
            }

            $collection->add(new SAML2_Certificate_Fingerprint((string) $fingerprint));
        }

        return $collection;
    }
}
