<?php

/**
 * Interface for triggering setter injection
 */
interface SAML2_Configuration_IdentityProviderAware
{
    public function setIdentityProvider(SAML2_Configuration_IdentityProvider $identityProvider);
}
