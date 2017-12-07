<?php

/**
 * Validates the preconditions that have to be met prior to processing of the response.
 */
class SAML2_Response_Validation_PreconditionValidator extends SAML2_Response_Validation_Validator
{
    public function __construct(SAML2_Configuration_Destination $destination)
    {
        // move to DI
        $this->addConstraintValidator(new SAML2_Response_Validation_ConstraintValidator_IsSuccessful());
        $this->addConstraintValidator(
            new SAML2_Response_Validation_ConstraintValidator_DestinationMatches($destination)
        );
    }
}
