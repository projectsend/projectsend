<?php
/**
 * The interface that must be implemented by any log handler.
 *
 * @author Jaime Perez Crespo, UNINETT AS.
 * @package SimpleSAMLphp
 * @version $ID$
 */

interface SimpleSAML_Logger_LoggingHandler
{
    /**
     * Log a message to its destination.
     *
     * @param int $level The log level.
     * @param string $string The formatted message to log.
     */
    public function log($level, $string);


    /**
     * Set the format desired for the logs.
     *
     * @param string $format The format used for logs.
     */
    public function setLogFormat($format);
}
