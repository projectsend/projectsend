<?php

/**
 * Validates the signature based on the fingerprint of the certificate
 */
class SAML2_Signature_FingerprintValidator extends SAML2_Signature_AbstractChainedValidator
{
    /**
     * @var array
     */
    private $certificates;

    /**
     * @var SAML2_Certificate_FingerprintLoader
     */
    private $fingerprintLoader;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        SAML2_Certificate_FingerprintLoader $fingerprintLoader
    ) {
        $this->fingerprintLoader = $fingerprintLoader;

        parent::__construct($logger);
    }

    public function canValidate(
        SAML2_SignedElement $signedElement,
        SAML2_Configuration_CertificateProvider $configuration
    ) {
        if ($configuration->getCertificateFingerprints() === NULL) {
            $this->logger->debug(
                'Configuration does not have "certFingerprint" value, cannot validate signature with fingerprint'
            );
            return FALSE;
        }

        // use internal cache to prevent doing certificate extraction twice.
        $this->certificates = $signedElement->getCertificates();
        if (empty($this->certificates)) {
            $this->logger->debug(
                'Signed element does not have certificates, cannot validate signature with fingerprint'
            );
            return FALSE;
        }

        return TRUE;
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
        $this->certificates = array_map(function ($certificate) {
            return SAML2_Certificate_X509::createFromCertificateData($certificate);
        }, $this->certificates);

        $fingerprintCollection = $this->fingerprintLoader->loadFromConfiguration($configuration);

        $pemCandidates = array();
        foreach ($this->certificates as $certificate) {
            /** @var SAML2_Certificate_X509 $certificate */
            $certificateFingerprint = $certificate->getFingerprint();
            if ($fingerprintCollection->contains($certificateFingerprint)) {
                $pemCandidates[] = $certificate;
            }
        }

        if (empty($pemCandidates)) {
            $this->logger->debug(
                'Unable to match a certificate of the SignedElement matching a configured fingerprint'
            );

            return FALSE;
        }

        return $this->validateElementWithKeys($signedElement, $pemCandidates);
    }
}
