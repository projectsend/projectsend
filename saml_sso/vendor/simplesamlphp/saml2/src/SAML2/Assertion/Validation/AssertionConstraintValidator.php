<?php

interface SAML2_Assertion_Validation_AssertionConstraintValidator
{
    public function validate(SAML2_Assertion $assertion, SAML2_Assertion_Validation_Result $result);
}
