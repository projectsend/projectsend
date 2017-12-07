<?php

class SAML2_Assertion_Validation_ConstraintValidator_SubjectConfirmationResponseToMatches implements
    SAML2_Assertion_Validation_SubjectConfirmationConstraintValidator
{
    private $response;

    public function __construct(SAML2_Response $response)
    {
        $this->response = $response;
    }

    public function validate(
        SAML2_XML_saml_SubjectConfirmation $subjectConfirmation,
        SAML2_Assertion_Validation_Result $result
    ) {
        $inResponseTo = $subjectConfirmation->SubjectConfirmationData->InResponseTo;
        if ($inResponseTo && $this->getInResponseTo() && $this->getInResponseTo() !== $inResponseTo) {
            $result->addError(sprintf(
                'InResponseTo in SubjectConfirmationData ("%s") does not match the Response InResponseTo ("%s")',
                $inResponseTo,
                $this->getInResponseTo()
            ));
        }
    }

    private function getInResponseTo()
    {
        $inResponseTo = $this->response->getInResponseTo();
        if ($inResponseTo === NULL) {
            return FALSE;
        }

        return $inResponseTo;
    }
}
