<?php

final class SAML2_Exception_UnparseableXmlException extends SAML2_Exception_RuntimeException
{
    private static $levelMap = array(
        LIBXML_ERR_WARNING => 'WARNING',
        LIBXML_ERR_ERROR   => 'ERROR',
        LIBXML_ERR_FATAL   => 'FATAL'
    );

    public function __construct(LibXMLError $error)
    {
        $message = sprintf(
            'Unable to parse XML - "%s[%d]": "%s" in "%s" at line %d on column %d"',
            static::$levelMap[$error->level],
            $error->code,
            $error->message,
            $error->file ?: '(string)',
            $error->line,
            $error->column
        );

        parent::__construct($message);
    }
}
