<?php

/**
 * Named exception. Indicates that although required, no keys could be loaded from the configuration
 */
class SAML2_Certificate_Exception_NoKeysFoundException extends DomainException implements SAML2_Exception_Throwable
{
}
