<?php

class SAML2_Assertion_Validation_ConstraintValidator_SubjectConfirmationRecipientMatches implements
    SAML2_Assertion_Validation_SubjectConfirmationConstraintValidator
{
    /**
     * @var SAML2_Configuration_Destination
     */
    private $destination;

    public function __construct(SAML2_Configuration_Destination $destination)
    {
        $this->destination = $destination;
    }

    public function validate(
        SAML2_XML_saml_SubjectConfirmation $subjectConfirmation,
        SAML2_Assertion_Validation_Result $result
    ) {
        $recipient = $subjectConfirmation->SubjectConfirmationData->Recipient;
        if ($recipient && !$this->destination->equals(new SAML2_Configuration_Destination($recipient))) {
            $result->addError(sprintf(
                'Recipient in SubjectConfirmationData ("%s") does not match the current destination ("%s")',
                $recipient,
                $this->destination
            ));
        }
    }
}
