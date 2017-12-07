<?php

class SAML2_Assertion_Validation_ConstraintValidator_SpIsValidAudience implements
    SAML2_Assertion_Validation_AssertionConstraintValidator,
    SAML2_Configuration_ServiceProviderAware
{
    /**
     * @var SAML2_Configuration_ServiceProvider
     */
    private $serviceProvider;

    public function setServiceProvider(SAML2_Configuration_ServiceProvider $serviceProvider)
    {
        $this->serviceProvider = $serviceProvider;
    }

    public function validate(SAML2_Assertion $assertion, SAML2_Assertion_Validation_Result $result)
    {
        $intendedAudiences = $assertion->getValidAudiences();
        if ($intendedAudiences === NULL) {
            return;
        }

        $entityId = $this->serviceProvider->getEntityId();
        if (!in_array($entityId, $intendedAudiences)) {
            $result->addError(sprintf(
                'The configured Service Provider [%s] is not a valid audience for the assertion. Audiences: [%s]',
                $entityId,
                implode('], [', $intendedAudiences)
            ));
        }
    }
}
