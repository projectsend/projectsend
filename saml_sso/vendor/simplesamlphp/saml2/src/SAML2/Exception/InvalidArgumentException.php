<?php

class SAML2_Exception_InvalidArgumentException extends InvalidArgumentException implements SAML2_Exception_Throwable
{
    /**
     * @param string $expected description of expected type
     * @param mixed  $parameter the parameter that is not of the expected type.
     *
     * @return SAML2_Exception_InvalidArgumentException
     */
    public static function invalidType($expected, $parameter)
    {
        $message = sprintf(
            'Invalid Argument type: "%s" expected, "%s" given',
            $expected,
            is_object($parameter) ? get_class($parameter) : gettype($parameter)
        );

        return new self($message);
    }
}
