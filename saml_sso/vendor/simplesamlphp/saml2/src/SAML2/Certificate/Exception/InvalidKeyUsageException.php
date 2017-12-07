<?php

/**
 * Named exception for when a non-existent key-usage is given
 */
class SAML2_Certificate_Exception_InvalidKeyUsageException extends InvalidArgumentException implements
    SAML2_Exception_Throwable
{
    /**
     * @param string $usage
     */
    public function __construct($usage)
    {
        $message = sprintf(
            'Invalid key usage given: "%s", usages "%s" allowed',
            is_string($usage) ? $usage : gettype($usage),
            implode('", "', SAML2_Certificate_Key::getValidKeyUsages())
        );

        parent::__construct($message);
    }
}
