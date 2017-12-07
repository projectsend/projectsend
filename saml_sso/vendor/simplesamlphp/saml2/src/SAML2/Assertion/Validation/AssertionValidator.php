<?php

class SAML2_Assertion_Validation_AssertionValidator
{
    /**
     * @var SAML2_Assertion_Validation_AssertionConstraintValidator[]
     */
    protected $constraints;

    /**
     * @var SAML2_Configuration_IdentityProvider
     */
    private $identityProvider;

    /**
     * @var SAML2_Configuration_ServiceProvider
     */
    private $serviceProvider;

    /**
     * @param SAML2_Configuration_IdentityProvider $identityProvider
     * @param SAML2_Configuration_ServiceProvider  $serviceProvider
     */
    public function __construct(
        SAML2_Configuration_IdentityProvider $identityProvider,
        SAML2_Configuration_ServiceProvider $serviceProvider
    ) {
        $this->identityProvider = $identityProvider;
        $this->serviceProvider = $serviceProvider;
    }

    public function addConstraintValidator(SAML2_Assertion_Validation_AssertionConstraintValidator $constraint)
    {
        if ($constraint instanceof SAML2_Configuration_IdentityProviderAware) {
            $constraint->setIdentityProvider($this->identityProvider);
        }

        if ($constraint instanceof SAML2_Configuration_ServiceProviderAware) {
            $constraint->setServiceProvider($this->serviceProvider);
        }

        $this->constraints[] = $constraint;
    }

    public function validate(SAML2_Assertion $assertion)
    {
        $result = new SAML2_Assertion_Validation_Result();
        foreach ($this->constraints as $validator) {
            $validator->validate($assertion, $result);
        }

        return $result;
    }
}
