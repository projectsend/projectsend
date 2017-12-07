<?php

class SAML2_Assertion_Validation_ConstraintValidator_NotOnOrAfter implements
    SAML2_Assertion_Validation_AssertionConstraintValidator
{
    public function validate(SAML2_Assertion $assertion, SAML2_Assertion_Validation_Result $result)
    {
        $notValidOnOrAfterTimestamp = $assertion->getNotOnOrAfter();
        if ($notValidOnOrAfterTimestamp && $notValidOnOrAfterTimestamp <= SAML2_Utilities_Temporal::getTime() - 60) {
            $result->addError(
                'Received an assertion that has expired. Check clock synchronization on IdP and SP.'
            );
        }
    }
}
