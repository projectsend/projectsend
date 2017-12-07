<?php

/**
 * Simple Builder that allows to build a new Assertion Processor.
 *
 * This is an excellent candidate for refactoring towards dependency injection
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SAML2_Assertion_ProcessorBuilder
{
    public static function build(
        Psr\Log\LoggerInterface $logger,
        SAML2_Signature_Validator $signatureValidator,
        SAML2_Configuration_Destination $currentDestination,
        SAML2_Configuration_IdentityProvider $identityProvider,
        SAML2_Configuration_ServiceProvider $serviceProvider,
        SAML2_Response $response
    ) {
        $keyloader = new SAML2_Certificate_PrivateKeyLoader();
        $decrypter = new SAML2_Assertion_Decrypter($logger, $identityProvider, $serviceProvider, $keyloader);
        $assertionValidator = self::createAssertionValidator($identityProvider, $serviceProvider);
        $subjectConfirmationValidator = self::createSubjectConfirmationValidator(
            $identityProvider,
            $serviceProvider,
            $currentDestination,
            $response
        );

        $transformerChain = self::createAssertionTransformerChain(
            $logger,
            $keyloader,
            $identityProvider,
            $serviceProvider
        );

        return new SAML2_Assertion_Processor(
            $decrypter,
            $signatureValidator,
            $assertionValidator,
            $subjectConfirmationValidator,
            $transformerChain,
            $identityProvider,
            $logger
        );
    }

    private static function createAssertionValidator(
        SAML2_Configuration_IdentityProvider $identityProvider,
        SAML2_Configuration_ServiceProvider $serviceProvider
    ) {
        $validator = new SAML2_Assertion_Validation_AssertionValidator($identityProvider, $serviceProvider);
        $validator->addConstraintValidator(new SAML2_Assertion_Validation_ConstraintValidator_NotBefore());
        $validator->addConstraintValidator(new SAML2_Assertion_Validation_ConstraintValidator_NotOnOrAfter());
        $validator->addConstraintValidator(new SAML2_Assertion_Validation_ConstraintValidator_SessionNotOnOrAfter());
        $validator->addConstraintValidator(new SAML2_Assertion_Validation_ConstraintValidator_SpIsValidAudience());

        return $validator;
    }

    private static function createSubjectConfirmationValidator(
        SAML2_Configuration_IdentityProvider $identityProvider,
        SAML2_Configuration_ServiceProvider $serviceProvider,
        SAML2_Configuration_Destination $currentDestination,
        SAML2_Response $response
    ) {
        $validator = new SAML2_Assertion_Validation_SubjectConfirmationValidator($identityProvider, $serviceProvider);
        $validator->addConstraintValidator(
            new SAML2_Assertion_Validation_ConstraintValidator_SubjectConfirmationMethod()
        );
        $validator->addConstraintValidator(
            new SAML2_Assertion_Validation_ConstraintValidator_SubjectConfirmationNotBefore()
        );
        $validator->addConstraintValidator(
            new SAML2_Assertion_Validation_ConstraintValidator_SubjectConfirmationNotOnOrAfter()
        );
        $validator->addConstraintValidator(
            new SAML2_Assertion_Validation_ConstraintValidator_SubjectConfirmationRecipientMatches(
                $currentDestination
            )
        );
        $validator->addConstraintValidator(
            new SAML2_Assertion_Validation_ConstraintValidator_SubjectConfirmationResponseToMatches(
                $response
            )
        );

        return $validator;
    }

    private static function createAssertionTransformerChain(
        \Psr\Log\LoggerInterface $logger,
        SAML2_Certificate_PrivateKeyLoader $keyloader,
        SAML2_Configuration_IdentityProvider $identityProvider,
        SAML2_Configuration_ServiceProvider $serviceProvider
    ) {
        $chain = new SAML2_Assertion_Transformer_TransformerChain($identityProvider, $serviceProvider);
        $chain->addTransformerStep(new SAML2_Assertion_Transformer_DecodeBase64Transformer());
        $chain->addTransformerStep(
            new SAML2_Assertion_Transformer_NameIdDecryptionTransformer($logger, $keyloader)
        );

        return $chain;
    }

}
