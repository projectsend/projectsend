<?php


/**
 * The main logger class for SimpleSAMLphp.
 *
 * @author Lasse Birnbaum Jensen, SDU.
 * @author Andreas Åkre Solberg, UNINETT AS. <andreas.solberg@uninett.no>
 * @author Jaime Pérez Crespo, UNINETT AS <jaime.perez@uninett.no>
 * @package SimpleSAMLphp
 * @version $ID$
 */
class SimpleSAML_Logger
{

    /**
     * @var SimpleSAML_Logger_LoggingHandler|false|null
     */
    private static $loggingHandler = null;

    /**
     * @var integer|null
     */
    private static $logLevel = null;

    /**
     * @var boolean
     */
    private static $captureLog = false;

    /**
     * @var array
     */
    private static $capturedLog = array();

    /**
     * Array with messages logged before the logging handler was initialized.
     *
     * @var array
     */
    private static $earlyLog = array();


    /**
     * This constant defines the string we set the track ID to while we are fetching the track ID from the session
     * class. This is used to prevent infinite recursion.
     */
    const NO_TRACKID = '_NOTRACKIDYET_';

    /**
     * This variable holds the track ID we have retrieved from the session class. It can also be NULL, in which case
     * we haven't fetched the track ID yet, or self::NO_TRACKID, which means that we are fetching the track ID now.
     */
    private static $trackid = self::NO_TRACKID;

    /**
     * This variable holds the format used to log any message. Its use varies depending on the log handler used (for
     * instance, you cannot control here how dates are displayed when using syslog or errorlog handlers), but in
     * general the options are:
     *
     * - %date{<format>}: the date and time, with its format specified inside the brackets. See the PHP documentation
     *   of the strftime() function for more information on the format. If the brackets are omitted, the standard
     *   format is applied. This can be useful if you just want to control the placement of the date, but don't care
     *   about the format.
     *
     * - %process: the name of the SimpleSAMLphp process. Remember you can configure this in the 'logging.processname'
     *   option.
     *
     * - %level: the log level (name or number depending on the handler used).
     *
     * - %stat: if the log entry is intended for statistical purposes, it will print the string 'STAT ' (bear in mind
     *   the trailing space).
     *
     * - %trackid: the track ID, an identifier that allows you to track a single session.
     *
     * - %srcip: the IP address of the client. If you are behind a proxy, make sure to modify the
     *   $_SERVER['REMOTE_ADDR'] variable on your code accordingly to the X-Forwarded-For header.
     *
     * - %msg: the message to be logged.
     *
     * @var string The format of the log line.
     */
    private static $format = '%date{%b %d %H:%M:%S} %process %level %stat[%trackid] %msg';

    /**
     * This variable tells if we have a shutdown function registered or not.
     *
     * @var bool
     */
    private static $shutdownRegistered = false;

    /**
     * This variable tells if we are shutting down.
     *
     * @var bool
     */
    private static $shuttingDown = false;

    const EMERG = 0;
    const ALERT = 1;
    const CRIT = 2;
    const ERR = 3;
    const WARNING = 4;
    const NOTICE = 5;
    const INFO = 6;
    const DEBUG = 7;


    /**
     * Log an emergency message.
     *
     * @var string $string The message to log.
     */
    public static function emergency($string)
    {
        self::log(self::EMERG, $string);
    }


    /**
     * Log a critical message.
     *
     * @var string $string The message to log.
     */
    public static function critical($string)
    {
        self::log(self::CRIT, $string);
    }


    /**
     * Log an alert.
     *
     * @var string $string The message to log.
     */
    public static function alert($string)
    {
        self::log(self::ALERT, $string);
    }


    /**
     * Log an error.
     *
     * @var string $string The message to log.
     */
    public static function error($string)
    {
        self::log(self::ERR, $string);
    }


    /**
     * Log a warning.
     *
     * @var string $string The message to log.
     */
    public static function warning($string)
    {
        self::log(self::WARNING, $string);
    }


    /**
     * We reserve the notice level for statistics, so do not use this level for other kind of log messages.
     *
     * @var string $string The message to log.
     */
    public static function notice($string)
    {
        self::log(self::NOTICE, $string);
    }


    /**
     * Info messages are a bit less verbose than debug messages. This is useful to trace a session.
     *
     * @var string $string The message to log.
     */
    public static function info($string)
    {
        self::log(self::INFO, $string);
    }


    /**
     * Debug messages are very verbose, and will contain more information than what is necessary for a production
     * system.
     *
     * @var string $string The message to log.
     */
    public static function debug($string)
    {
        self::log(self::DEBUG, $string);
    }


    /**
     * Statistics.
     *
     * @var string $string The message to log.
     */
    public static function stats($string)
    {
        self::log(self::NOTICE, $string, true);
    }


    /**
     * Set the logger to capture logs.
     *
     * @var boolean $val Whether to capture logs or not. Defaults to TRUE.
     */
    public static function setCaptureLog($val = true)
    {
        self::$captureLog = $val;
    }


    /**
     * Get the captured log.
     */
    public static function getCapturedLog()
    {
        return self::$capturedLog;
    }


    /**
     * Set the track identifier to use in all logs.
     *
     * @param $trackId string The track identifier to use during this session.
     */
    public static function setTrackId($trackId)
    {
        self::$trackid = $trackId;
    }


    /**
     * Flush any pending log messages to the logging handler.
     *
     * This method is intended to be registered as a shutdown handler, so that any pending messages that weren't sent
     * to the logging handler at that point, can still make it. It is therefore not intended to be called manually.
     *
     */
    public static function flush()
    {
        try {
            $s = \SimpleSAML_Session::getSessionFromRequest();
        } catch (\Exception $e) {
            // loading session failed. We don't care why, at this point we have a transient session, so we use that
            self::error('Cannot load or create session: '.$e->getMessage());
            $s = \SimpleSAML_Session::getSessionFromRequest();
        }
        self::$trackid = $s->getTrackID();

        self::$shuttingDown = true;
        foreach (self::$earlyLog as $msg) {
            self::log($msg['level'], $msg['string'], $msg['statsLog']);
        }
    }


    /**
     * Defer a message for later logging.
     *
     * @param int $level The log level corresponding to this message.
     * @param string $message The message itself to log.
     * @param boolean $stats Whether this is a stats message or a regular one.
     */
    private static function defer($level, $message, $stats)
    {
        // save the message for later
        self::$earlyLog[] = array('level' => $level, 'string' => $message, 'statsLog' => $stats);

        // register a shutdown handler if needed
        if (!self::$shutdownRegistered) {
            register_shutdown_function(array('SimpleSAML_Logger', 'flush'));
            self::$shutdownRegistered = true;
        }
    }

    private static function createLoggingHandler()
    {
        // set to FALSE to indicate that it is being initialized
        self::$loggingHandler = false;

        // get the configuration
        $config = SimpleSAML_Configuration::getInstance();
        assert($config instanceof SimpleSAML_Configuration);

        // get the metadata handler option from the configuration
        $handler = $config->getString('logging.handler', 'syslog');

        // setting minimum log_level
        self::$logLevel = $config->getInteger('logging.level', self::INFO);

        $handler = strtolower($handler);

        if ($handler === 'syslog') {
            $sh = new SimpleSAML_Logger_LoggingHandlerSyslog();
        } elseif ($handler === 'file') {
            $sh = new SimpleSAML_Logger_LoggingHandlerFile();
        } elseif ($handler === 'errorlog') {
            $sh = new SimpleSAML_Logger_LoggingHandlerErrorLog();
        } else {
            throw new Exception(
                'Invalid value for the [logging.handler] configuration option. Unknown handler: '.$handler
            );
        }

        self::$format = $config->getString('logging.format', self::$format);
        $sh->setLogFormat(self::$format);

        // set the session handler
        self::$loggingHandler = $sh;
    }


    private static function log($level, $string, $statsLog = false)
    {
        if (php_sapi_name() === 'cli' || defined('STDIN')) {
            // we are being executed from the CLI, nowhere to log
            return;
        }

        if (self::$loggingHandler === null) {
            // Initialize logging
            self::createLoggingHandler();

            if (!empty(self::$earlyLog)) {
                error_log('----------------------------------------------------------------------');
                // output messages which were logged before we properly initialized logging
                foreach (self::$earlyLog as $msg) {
                    self::log($msg['level'], $msg['string'], $msg['statsLog']);
                }
            }
        } elseif (self::$loggingHandler === false) {
            // some error occurred while initializing logging
            if (empty(self::$earlyLog)) {
                // this is the first message
                error_log('--- Log message(s) while initializing logging ------------------------');
            }
            error_log($string);

            self::defer($level, $string, $statsLog);
            return;
        }

        if (self::$captureLog) {
            $ts = microtime(true);
            $msecs = (int) (($ts - (int) $ts) * 1000);
            $ts = gmdate('H:i:s', $ts).sprintf('.%03d', $msecs).'Z';
            self::$capturedLog[] = $ts.' '.$string;
        }

        if (self::$logLevel >= $level || $statsLog) {
            if (is_array($string)) {
                $string = implode(",", $string);
            }

            $formats = array('%trackid', '%msg', '%srcip', '%stat');
            $replacements = array(self::$trackid, $string, $_SERVER['REMOTE_ADDR']);

            $stat = '';
            if ($statsLog) {
                $stat = 'STAT ';
            }
            array_push($replacements, $stat);

            if (self::$trackid === self::NO_TRACKID && !self::$shuttingDown) {
                // we have a log without track ID and we are not still shutting down, so defer logging
                self::defer($level, $string, $statsLog);
                return;
            } elseif (self::$trackid === self::NO_TRACKID) {
                // shutting down without a track ID, prettify it
                array_shift($replacements);
                array_unshift($replacements, 'N/A');
            }

            // we either have a track ID or we are shutting down, so just log the message
            $string = str_replace($formats, $replacements, self::$format);
            self::$loggingHandler->log($level, $string);
        }
    }
}
