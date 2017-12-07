<?php

/**
 * MockChainedValidator, to be able to test the validatorchain without having to use
 * actual validators
 */
class SAML2_Signature_MockChainedValidator extends SAML2_Signature_AbstractChainedValidator
{
    /**
     * @var boolean
     */
    private $canValidate;

    /**
     * @var boolean
     */
    private $isValid;

    /**
     * Constructor that allows to control the behavior of the Validator
     *
     * @param bool $canValidate the return value of the canValidate call
     * @param bool $isValid     the return value of the isValid hasValidSignature call
     */
    public function __construct($canValidate, $isValid)
    {
        $this->canValidate = $canValidate;
        $this->isValid = $isValid;

        parent::__construct(new \Psr\Log\NullLogger());
    }

    public function canValidate(
        SAML2_SignedElement $signedElement,
        SAML2_Configuration_CertificateProvider $configuration
    ) {
        return $this->canValidate;
    }

    public function hasValidSignature(
        SAML2_SignedElement $signedElement,
        SAML2_Configuration_CertificateProvider $configuration
    ) {
        return $this->isValid;
    }

}
