<?php

interface SAML2_Assertion_Transformer_Transformer
{
    /**
     * @param SAML2_Assertion $assertion
     *
     * @return SAML2_Assertion
     */
    public function transform(SAML2_Assertion $assertion);
}
