<?php

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) - due to specific exceptions
 */
class SAML2_Response_Processor
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var SAML2_Response_Validation_PreconditionValidator
     */
    private $preconditionValidator;

    /**
     * @var SAML2_Signature_Validator
     */
    private $signatureValidator;

    /**
     * @var SAML2_Assertion_Processor
     */
    private $assertionProcessor;

    /**
     * Indicates whether or not the response was signed. This is required in order to be able to check whether either
     * the reponse or one of its assertions was signed
     *
     * @var bool
     */
    private $responseIsSigned = FALSE;

    /**
     * @param \Psr\Log\LoggerInterface        $logger
     *
     */
    public function __construct(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;

        $this->signatureValidator = new SAML2_Signature_Validator($logger);
    }

    /**
     * @param SAML2_Configuration_ServiceProvider  $serviceProviderConfiguration
     * @param SAML2_Configuration_IdentityProvider $identityProviderConfiguration
     * @param SAML2_Configuration_Destination      $currentDestination
     * @param SAML2_Response                       $response
     *
     * @return SAML2_Assertion[] Collection (SAML2_Utilities_ArrayCollection) of SAML2_Assertion objects
     */
    public function process(
        SAML2_Configuration_ServiceProvider $serviceProviderConfiguration,
        SAML2_Configuration_IdentityProvider $identityProviderConfiguration,
        SAML2_Configuration_Destination $currentDestination,
        SAML2_Response $response
    ) {
        $this->preconditionValidator = new SAML2_Response_Validation_PreconditionValidator($currentDestination);
        $this->assertionProcessor = SAML2_Assertion_ProcessorBuilder::build(
            $this->logger,
            $this->signatureValidator,
            $currentDestination,
            $identityProviderConfiguration,
            $serviceProviderConfiguration,
            $response
        );

        $this->enforcePreconditions($response);
        $this->verifySignature($response, $identityProviderConfiguration);
        return $this->processAssertions($response);
    }

    /**
     * Checks the preconditions that must be valid in order for the response to be processed.
     *
     * @param SAML2_Response $response
     */
    private function enforcePreconditions(SAML2_Response $response)
    {
        $result = $this->preconditionValidator->validate($response);

        if (!$result->isValid()) {
            throw SAML2_Response_Exception_PreconditionNotMetException::createFromValidationResult($result);
        }
    }

    /**
     * @param SAML2_Response                       $response
     * @param SAML2_Configuration_IdentityProvider $identityProviderConfiguration
     */
    private function verifySignature(
        SAML2_Response $response,
        SAML2_Configuration_IdentityProvider $identityProviderConfiguration
    ) {
        if (!$response->isMessageConstructedWithSignature()) {
            $this->logger->info(sprintf(
                'SAMLResponse with id "%s" was not signed at root level, not attempting to verify the signature of the'
                . ' reponse itself',
                $response->getId()
            ));

            return;
        }

        $this->logger->info(sprintf(
            'Attempting to verify the signature of SAMLResponse with id "%s"',
            $response->getId()
        ));

        $this->responseIsSigned = TRUE;

        if (!$this->signatureValidator->hasValidSignature($response, $identityProviderConfiguration)) {
            throw new SAML2_Response_Exception_InvalidResponseException();
        }
    }

    /**
     * @param SAML2_Response $response
     *
     * @return SAML2_Assertion[]
     */
    private function processAssertions(SAML2_Response $response)
    {
        $assertions = $response->getAssertions();
        if (empty($assertions)) {
            throw new SAML2_Response_Exception_NoAssertionsFoundException('No assertions found in response from IdP.');
        }

        if (!$this->responseIsSigned) {
            foreach ($assertions as $assertion) {
                if (!$assertion->getWasSignedAtConstruction()) {
                    throw new SAML2_Response_Exception_UnsignedResponseException(
                        'Both the response and the assertion it containes are not signed.'
                    );
                }
            }
        }

        return $this->assertionProcessor->processAssertions($assertions);
    }
}
