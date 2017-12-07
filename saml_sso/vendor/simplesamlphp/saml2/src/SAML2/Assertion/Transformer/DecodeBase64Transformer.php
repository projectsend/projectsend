<?php

class SAML2_Assertion_Transformer_DecodeBase64Transformer implements
    SAML2_Assertion_Transformer_Transformer,
    SAML2_Configuration_IdentityProviderAware
{
    /**
     * @var SAML2_Configuration_IdentityProvider
     */
    private $identityProvider;

    public function setIdentityProvider(SAML2_Configuration_IdentityProvider $identityProvider)
    {
        $this->identityProvider = $identityProvider;
    }

    public function transform(SAML2_Assertion $assertion)
    {
        if (!$this->identityProvider->hasBase64EncodedAttributes()) {
            return $assertion;
        }

        $attributes = $assertion->getAttributes();
        $keys = array_keys($attributes);
        $decoded = array_map(array($this, 'decodeValue'), $attributes);

        $attributes = array_combine($keys, $decoded);

        $assertion->setAttributes($attributes);
    }

    /**
     * @param $value
     *
     * @return array
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function decodeValue($value)
    {
        $elements = explode('_', $value);
        return array_map('base64_decode', $elements);
    }
}
