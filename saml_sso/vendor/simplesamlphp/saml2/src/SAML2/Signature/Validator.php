<?php

/**
 * Signature Validator.
 */
class SAML2_Signature_Validator
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    public function __construct(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function hasValidSignature(
        SAML2_SignedElement $signedElement,
        SAML2_Configuration_CertificateProvider $configuration
    ) {
        // should be DI
        $validator = new SAML2_Signature_ValidatorChain(
            $this->logger,
            array(
                new SAML2_Signature_PublicKeyValidator($this->logger, new SAML2_Certificate_KeyLoader()),
                new SAML2_Signature_FingerprintValidator($this->logger, new SAML2_Certificate_FingerprintLoader())
            )
        );

        return $validator->hasValidSignature($signedElement, $configuration);
    }
}
