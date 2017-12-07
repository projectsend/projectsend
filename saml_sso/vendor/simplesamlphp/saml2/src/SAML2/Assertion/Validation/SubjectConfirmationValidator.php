<?php

class SAML2_Assertion_Validation_SubjectConfirmationValidator
{
    /**
     * @var SAML2_Assertion_Validation_SubjectConfirmationConstraintValidator[]
     */
    protected $constraints;

    /**
     * @var SAML2_Configuration_IdentityProvider
     */
    protected $identityProvider;

    /**
     * @var SAML2_Configuration_ServiceProvider
     */
    protected $serviceProvider;

    public function __construct(
        SAML2_Configuration_IdentityProvider $identityProvider,
        SAML2_Configuration_ServiceProvider $serviceProvider
    ) {
        $this->identityProvider = $identityProvider;
        $this->serviceProvider = $serviceProvider;
    }

    public function addConstraintValidator(
        SAML2_Assertion_Validation_SubjectConfirmationConstraintValidator $constraint
    ) {
        if ($constraint instanceof SAML2_Configuration_IdentityProviderAware) {
            $constraint->setIdentityProvider($this->identityProvider);
        }

        if ($constraint instanceof SAML2_Configuration_ServiceProviderAware) {
            $constraint->setServiceProvider($this->serviceProvider);
        }

        $this->constraints[] = $constraint;
    }

    public function validate(SAML2_XML_saml_SubjectConfirmation $subjectConfirmation)
    {
        $result = new SAML2_Assertion_Validation_Result();
        foreach ($this->constraints as $validator) {
            $validator->validate($subjectConfirmation, $result);
        }

        return $result;
    }
}
