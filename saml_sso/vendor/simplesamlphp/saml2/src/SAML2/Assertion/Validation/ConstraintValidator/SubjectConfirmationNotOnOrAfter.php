<?php

class SAML2_Assertion_Validation_ConstraintValidator_SubjectConfirmationNotOnOrAfter implements
    SAML2_Assertion_Validation_SubjectConfirmationConstraintValidator
{
    public function validate(
        SAML2_XML_saml_SubjectConfirmation $subjectConfirmation,
        SAML2_Assertion_Validation_Result $result
    ) {
        $notOnOrAfter = $subjectConfirmation->SubjectConfirmationData->NotOnOrAfter;
        if ($notOnOrAfter && $notOnOrAfter <= SAML2_Utilities_Temporal::getTime() - 60) {
            $result->addError('NotOnOrAfter in SubjectConfirmationData is in the past');
        }
    }
}
