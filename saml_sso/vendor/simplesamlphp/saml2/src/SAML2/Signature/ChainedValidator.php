<?php

/**
 * Interface SAML2_Validator_Responsible
 *
 * should be renamed.
 */
interface SAML2_Signature_ChainedValidator extends SAML2_Signature_ValidatorInterface
{
    /**
     * Test whether or not this link in the chain can validate the signedElement signature.
     *
     * @param SAML2_SignedElement             $signedElement
     * @param SAML2_Configuration_CertificateProvider $configuration
     *
     * @return bool
     */
    public function canValidate(
        SAML2_SignedElement $signedElement,
        SAML2_Configuration_CertificateProvider $configuration
    );
}
