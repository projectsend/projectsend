<?php

interface SAML2_Signature_ValidatorInterface
{
    /**
     * Validate the signature of the signed Element based on the configuration
     *
     * @param SAML2_SignedElement             $signedElement
     * @param SAML2_Configuration_CertificateProvider $configuration
     *
     * @return bool
     */
    public function hasValidSignature(
        SAML2_SignedElement $signedElement,
        SAML2_Configuration_CertificateProvider $configuration
    );
}
