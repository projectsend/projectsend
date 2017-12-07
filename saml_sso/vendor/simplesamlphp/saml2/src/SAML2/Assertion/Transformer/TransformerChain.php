<?php

class SAML2_Assertion_Transformer_TransformerChain implements SAML2_Assertion_Transformer_Transformer
{
    /**
     * @var SAML2_Assertion_Transformer_Transformer[]
     */
    private $transformers = array();

    /**
     * @var SAML2_Configuration_IdentityProvider
     */
    private $identityProvider;

    /**
     * @var SAML2_Configuration_ServiceProvider
     */
    private $serviceProvider;

    public function __construct(
        SAML2_Configuration_IdentityProvider $identityProvider,
        SAML2_Configuration_ServiceProvider $serviceProvider
    ) {
        $this->identityProvider = $identityProvider;
        $this->serviceProvider  = $serviceProvider;
    }

    public function addTransformerStep(SAML2_Assertion_Transformer_Transformer $transformer)
    {
        if ($transformer instanceof SAML2_Configuration_IdentityProviderAware) {
            $transformer->setIdentityProvider($this->identityProvider);
        }

        if ($transformer instanceof SAML2_Configuration_ServiceProviderAware) {
            $transformer->setServiceProvider($this->serviceProvider);
        }

        $this->transformers[] = $transformer;
    }

    /**
     * @param SAML2_Assertion $assertion
     *
     * @return SAML2_Assertion
     */
    public function transform(SAML2_Assertion $assertion)
    {
        foreach ($this->transformers as $transformer) {
            $assertion = $transformer->transform($assertion);
        }

        return $assertion;
    }
}
