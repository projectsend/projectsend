<?php

class SAML2_Assertion_Validation_ConstraintValidator_SubjectConfirmationMethod implements
    SAML2_Assertion_Validation_SubjectConfirmationConstraintValidator
{
    public function validate(
        SAML2_XML_saml_SubjectConfirmation $subjectConfirmation,
        SAML2_Assertion_Validation_Result $result
    ) {
        if ($subjectConfirmation->Method !== SAML2_Const::CM_BEARER) {
            $result->addError(sprintf(
                'Invalid Method on SubjectConfirmation, current;y only Bearer (%s) is supported',
                SAML2_Const::CM_BEARER
            ));
        }
    }
}
