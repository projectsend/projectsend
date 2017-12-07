<?php

class SAML2_Compat_Ssp_Logger implements Psr\Log\LoggerInterface
{
    /**
     * System is unusable.
     *
     * @param string $message
     * @param array $context
     * @return NULL
     */
    public function emergency($message, array $context = array())
    {
        SimpleSAML_Logger::emergency($message . var_export($context, TRUE));
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array $context
     * @return NULL
     */
    public function alert($message, array $context = array())
    {
        SimpleSAML_Logger::alert($message . var_export($context, TRUE));
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array $context
     * @return NULL
     */
    public function critical($message, array $context = array())
    {
        SimpleSAML_Logger::critical($message . var_export($context, TRUE));
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array $context
     * @return NULL
     */
    public function error($message, array $context = array())
    {
        SimpleSAML_Logger::error($message . var_export($context, TRUE));
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array $context
     * @return NULL
     */
    public function warning($message, array $context = array())
    {
        SimpleSAML_Logger::warning($message . var_export($context, TRUE));
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array $context
     * @return NULL
     */
    public function notice($message, array $context = array())
    {
        SimpleSAML_Logger::notice($message . var_export($context, TRUE));
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array $context
     * @return NULL
     */
    public function info($message, array $context = array())
    {
        SimpleSAML_Logger::info($message . var_export($context, TRUE));
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array $context
     * @return NULL
     */
    public function debug($message, array $context = array())
    {
        SimpleSAML_Logger::debug($message . var_export($context, TRUE));
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     * @return NULL
     */
    public function log($level, $message, array $context = array())
    {
        switch ($level) {
            case SimpleSAML_Logger::ALERT:
                SimpleSAML_Logger::alert($message);
                break;
            case SimpleSAML_Logger::CRIT:
                SimpleSAML_Logger::critical($message);
                break;
            case SimpleSAML_Logger::DEBUG:
                SimpleSAML_Logger::debug($message);
                break;
            case SimpleSAML_Logger::EMERG:
                SimpleSAML_Logger::emergency($message);
                break;
            case SimpleSAML_Logger::ERR:
                SimpleSAML_Logger::error($message);
                break;
            case SimpleSAML_Logger::INFO:
                SimpleSAML_Logger::info($message);
                break;
            case SimpleSAML_Logger::NOTICE:
                SimpleSAML_Logger::notice($message);
                break;
            case SimpleSAML_Logger::WARNING:
                SimpleSAML_Logger::warning($message);
        }
    }
}
