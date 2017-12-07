<?php
/**
 * Exception to indicate that we cannot set a cookie.
 *
 * @author Jaime Pérez Crespo <jaime.perez@uninett.no>
 * @package SimpleSAMLphp
 */

namespace SimpleSAML\Error;


class CannotSetCookie extends \SimpleSAML_Error_Exception
{

    /**
     * The exception was thrown for unknown reasons.
     *
     * @var int
     */
    const UNKNOWN = 0;

    /**
     * The exception was due to the HTTP headers being already sent, and therefore we cannot send additional headers to
     * set the cookie.
     *
     * @var int
     */
    const HEADERS_SENT = 1;

    /**
     * The exception was due to trying to set a secure cookie over an insecure channel.
     *
     * @var int
     */
    const SECURE_COOKIE = 2;
}
