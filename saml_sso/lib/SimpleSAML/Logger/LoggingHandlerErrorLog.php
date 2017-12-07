<?php

/**
 * A class for logging to the default php error log.
 *
 * @author Lasse Birnbaum Jensen, SDU.
 * @author Andreas Åkre Solberg, UNINETT AS. <andreas.solberg@uninett.no>
 * @author Olav Morken, UNINETT AS.
 * @package SimpleSAMLphp
 * @version $ID$
 */
class SimpleSAML_Logger_LoggingHandlerErrorLog implements SimpleSAML_Logger_LoggingHandler
{

    /**
     * This array contains the mappings from syslog loglevel to names.
     */
    private static $levelNames = array(
        SimpleSAML_Logger::EMERG   => 'EMERG',
        SimpleSAML_Logger::ALERT   => 'ALERT',
        SimpleSAML_Logger::CRIT    => 'CRIT',
        SimpleSAML_Logger::ERR     => 'ERR',
        SimpleSAML_Logger::WARNING => 'WARNING',
        SimpleSAML_Logger::NOTICE  => 'NOTICE',
        SimpleSAML_Logger::INFO    => 'INFO',
        SimpleSAML_Logger::DEBUG   => 'DEBUG',
    );
    private $format;


    /**
     * Set the format desired for the logs.
     *
     * @param string $format The format used for logs.
     */
    public function setLogFormat($format)
    {
        $this->format = $format;
    }


    /**
     * Log a message to syslog.
     *
     * @param int $level The log level.
     * @param string $string The formatted message to log.
     */
    public function log($level, $string)
    {
        $config = SimpleSAML_Configuration::getInstance();
        assert($config instanceof SimpleSAML_Configuration);
        $processname = $config->getString('logging.processname', 'SimpleSAMLphp');

        if (array_key_exists($level, self::$levelNames)) {
            $levelName = self::$levelNames[$level];
        } else {
            $levelName = sprintf('UNKNOWN%d', $level);
        }

        $formats = array('%process', '%level');
        $replacements = array($processname, $levelName);
        $string = str_replace($formats, $replacements, $string);
        $string = preg_replace('/%\w+(\{[^\}]+\})?/', '', $string);
        $string = trim($string);

        error_log($string);
    }
}
