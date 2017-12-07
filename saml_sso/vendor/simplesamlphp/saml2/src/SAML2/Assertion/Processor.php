<?php

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) - due to all the named exceptions
 */
class SAML2_Assertion_Processor
{
    /**
     * @var SAML2_Assertion_Decrypter
     */
    private $decrypter;

    /**
     * @var SAML2_Assertion_Validation_AssertionValidator
     */
    private $assertionValidator;

    /**
     * @var SAML2_Assertion_Validation_SubjectConfirmationValidator
     */
    private $subjectConfirmationValidator;

    /**
     * @var SAML2_Assertion_Transformer_Transformer
     */
    private $transformer;

    /**
     * @var SAML2_Signature_Validator
     */
    private $signatureValidator;

    /**
     * @var SAML2_Configuration_IdentityProvider
     */
    private $identityProviderConfiguration;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    public function __construct(
        SAML2_Assertion_Decrypter $decrypter,
        SAML2_Signature_Validator $signatureValidator,
        SAML2_Assertion_Validation_AssertionValidator $assertionValidator,
        SAML2_Assertion_Validation_SubjectConfirmationValidator $subjectConfirmationValidator,
        SAML2_Assertion_Transformer_Transformer $transformer,
        SAML2_Configuration_IdentityProvider $identityProviderConfiguration,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->assertionValidator            = $assertionValidator;
        $this->signatureValidator            = $signatureValidator;
        $this->decrypter                     = $decrypter;
        $this->subjectConfirmationValidator  = $subjectConfirmationValidator;
        $this->transformer                   = $transformer;
        $this->identityProviderConfiguration = $identityProviderConfiguration;
        $this->logger                        = $logger;
    }

    /**
     * @param SAML2_Utilities_ArrayCollection $assertions
     *
     * @return SAML2_Assertion[] Collection (SAML2_Utilities_ArrayCollection) of processed assertions
     */
    public function processAssertions($assertions)
    {
        $processed = new SAML2_Utilities_ArrayCollection();
        foreach ($assertions as $assertion) {
            $processed->add($this->process($assertion));
        }

        return $processed;
    }

    /**
     * @param SAML2_Assertion|SAML2_EncryptedAssertion $assertion
     *
     * @return SAML2_Assertion
     */
    public function process($assertion)
    {
        $assertion = $this->decryptAssertion($assertion);

        if (!$assertion->getWasSignedAtConstruction()) {
            $this->logger->info(sprintf(
                'Assertion with id "%s" was not signed at construction, not verifying the signature',
                $assertion->getId()
            ));
        } else {
            $this->logger->info(sprintf('Verifying signature of Assertion with id "%s"', $assertion->getId()));

            if (!$this->signatureValidator->hasValidSignature($assertion, $this->identityProviderConfiguration)) {
                throw new SAML2_Response_Exception_InvalidSignatureException();
            }
        }

        $this->validateAssertion($assertion);

        $assertion = $this->transformAssertion($assertion);

        return $assertion;
    }

    /**
     * @param SAML2_Assertion|SAML2_EncryptedAssertion $assertion
     *
     * @return SAML2_Assertion
     */
    private function decryptAssertion($assertion)
    {
        if ($this->decrypter->isEncryptionRequired() && $assertion instanceof SAML2_Assertion) {
            throw new SAML2_Response_Exception_UnencryptedAssertionFoundException();
        }

        if ($assertion instanceof SAML2_Assertion) {
            return $assertion;
        }

        return $this->decrypter->decrypt($assertion);
    }

    /**
     * @param SAML2_Assertion $assertion
     */
    public function validateAssertion(SAML2_Assertion $assertion)
    {
        $assertionValidationResult = $this->assertionValidator->validate($assertion);
        if (!$assertionValidationResult->isValid()) {
            throw new SAML2_Assertion_Exception_InvalidAssertionException(sprintf(
                'Invalid Assertion in SAML Response, erorrs: "%s"',
                implode('", "', $assertionValidationResult->getErrors())
            ));
        }

        foreach ($assertion->getSubjectConfirmation() as $subjectConfirmation) {
            $subjectConfirmationValidationResult = $this->subjectConfirmationValidator->validate(
                $subjectConfirmation
            );
            if (!$subjectConfirmationValidationResult->isValid()) {
                throw new SAML2_Assertion_Exception_InvalidSubjectConfirmationException(sprintf(
                    'Invalid SubjectConfirmation in Assertion, errors: "%s"',
                    implode('", "', $subjectConfirmationValidationResult->getErrors())
                ));
            }
        }
    }

    /**
     * @param SAML2_Assertion $assertion
     *
     * @return SAML2_Assertion
     */
    private function transformAssertion(SAML2_Assertion $assertion)
    {
        return $this->transformer->transform($assertion);
    }
}
