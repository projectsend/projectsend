<?php

class SAML2_Signature_PublicKeyValidator extends SAML2_Signature_AbstractChainedValidator
{
    /**
     * @var SAML2_Certificate_KeyCollection
     */
    private $configuredKeys;

    /**
     * @var SAML2_Certificate_KeyLoader
     */
    private $keyLoader;

    public function __construct(\Psr\Log\LoggerInterface $logger, SAML2_Certificate_KeyLoader $keyLoader)
    {
        $this->keyLoader = $keyLoader;

        parent::__construct($logger);
    }

    /**
     * @param SAML2_SignedElement             $signedElement
     * @param SAML2_Configuration_CertificateProvider $configuration
     *
     * @return bool
     */
    public function canValidate(
        SAML2_SignedElement $signedElement,
        SAML2_Configuration_CertificateProvider $configuration
    ) {
        $this->configuredKeys = $this->keyLoader->extractPublicKeys($configuration);

        return !!count($this->configuredKeys);
    }

    /**
     * @param SAML2_SignedElement             $signedElement
     * @param SAML2_Configuration_CertificateProvider $configuration
     *
     * @return bool
     */
    public function hasValidSignature(
        SAML2_SignedElement $signedElement,
        SAML2_Configuration_CertificateProvider $configuration
    ) {
        $logger = $this->logger;
        $pemCandidates = $this->configuredKeys->filter(function (SAML2_Certificate_Key $key) use ($logger) {
            if (!$key instanceof SAML2_Certificate_X509) {
                $logger->debug(sprintf('Skipping unknown key type: "%s"', $key['type']));
                return FALSE;
            }
            return TRUE;
        });

        if (!count($pemCandidates)) {
            $this->logger->debug('No configured X509 certificate found to verify the signature with');

            return FALSE;
        }

        return $this->validateElementWithKeys($signedElement, $pemCandidates);
    }
}
