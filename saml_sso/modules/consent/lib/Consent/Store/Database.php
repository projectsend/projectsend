<?php
/**
 * Store consent in database.
 *
 * This class implements a consent store which stores the consent information
 * in a database. It is tested, and should work against MySQL, PostgreSQL and
 * SQLite.
 *
 * It has the following options:
 * - dsn: The DSN which should be used to connect to the database server. See 
 *        PHP Manual for supported drivers and DSN formats.
 * - username: The username used for database connection.
 * - password: The password used for database connection.
 * - table: The name of the table used. Optional, defaults to 'consent'.
 *
 * @author  Olav Morken <olav.morken@uninett.no>
 * @package SimpleSAMLphp
 */
class sspmod_consent_Consent_Store_Database extends sspmod_consent_Store
{
    /**
     * DSN for the database.
     */
    private $_dsn;

    /**
     * The DATETIME SQL function to use
     */
    private $_dateTime;

    /**
     * Username for the database.
     */
    private $_username;

    /**
     * Password for the database;
     */
    private $_password;

    /**
     * Table with consent.
     */
    private $_table;

    /**
     * The timeout of the database connection.
     *
     * @var int|NULL
     */
    private $_timeout = NULL;

    /**
     * Database handle.
     *
     * This variable can't be serialized.
     */
    private $_db;

    /**
     * Parse configuration.
     *
     * This constructor parses the configuration.
     *
     * @param array $config Configuration for database consent store.
     */
    public function __construct($config)
    {
        parent::__construct($config);


        if (!array_key_exists('dsn', $config)) {
            throw new Exception('consent:Database - Missing required option \'dsn\'.');
        }
        if (!is_string($config['dsn'])) {
            throw new Exception('consent:Database - \'dsn\' is supposed to be a string.');
        }

        $this->_dsn = $config['dsn'];
        $this->_dateTime = (0 === strpos($this->_dsn, 'sqlite:')) ? 'DATETIME("NOW")' : 'NOW()';

        if (array_key_exists('username', $config)) {
            if(!is_string($config['username'])) {
                throw new Exception('consent:Database - \'username\' is supposed to be a string.');
            }
            $this->_username = $config['username'];
        } else {
            $this->_username = NULL;
        }

        if (array_key_exists('password', $config)) {
            if(!is_string($config['password'])) {
                throw new Exception('consent:Database - \'password\' is supposed to be a string.');
            }
            $this->_password = $config['password'];
        } else {
            $this->_password = NULL;
        }

        if (array_key_exists('table', $config)) {
            if (!is_string($config['table'])) {
                throw new Exception(
                    'consent:Database - \'table\' is supposed to be a string.'
                );
            }
            $this->_table = $config['table'];
        } else {
            $this->_table = 'consent';
        }

        if (isset($config['timeout'])) {
            if (!is_int($config['timeout'])) {
                throw new Exception(
                    'consent:Database - \'timeout\' is supposed to be an integer.'
                );
            }
            $this->_timeout = $config['timeout'];
        }
    }

    /**
     * Called before serialization.
     *
     * @return array The variables which should be serialized.
     */
    public function __sleep()
    {
        return array(
            '_dsn',
            '_dateTime',
            '_username',
            '_password',
            '_table',
            '_timeout',
        );
    }

    /**
     * Check for consent.
     *
     * This function checks whether a given user has authorized the release of
     * the attributes identified by $attributeSet from $source to $destination.
     *
     * @param string $userId        The hash identifying the user at an IdP.
     * @param string $destinationId A string which identifies the destination.
     * @param string $attributeSet  A hash which identifies the attributes.
     *
     * @return bool True if the user has given consent earlier, false if not
     *              (or on error).
     */
    public function hasConsent($userId, $destinationId, $attributeSet)
    {
        assert('is_string($userId)');
        assert('is_string($destinationId)');
        assert('is_string($attributeSet)');

        $st = $this->_execute(
            'UPDATE ' . $this->_table . ' ' .
            'SET usage_date = ' . $this->_dateTime . ' ' .
            'WHERE hashed_user_id = ? AND service_id = ? AND attribute = ?',
            array($userId, $destinationId, $attributeSet)
        );

        if ($st === false) {
            return false;
        }

        $rowCount = $st->rowCount();
        if ($rowCount === 0) {
            SimpleSAML_Logger::debug('consent:Database - No consent found.');
            return false;
        } else {
            SimpleSAML_Logger::debug('consent:Database - Consent found.');
            return true;
        }

    }

    /**
     * Save consent.
     *
     * Called when the user asks for the consent to be saved. If consent information
     * for the given user and destination already exists, it should be overwritten.
     *
     * @param string $userId        The hash identifying the user at an IdP.
     * @param string $destinationId A string which identifies the destination.
     * @param string $attributeSet  A hash which identifies the attributes.
     *
     * @return void|true True if consent is deleted 
     */
    public function saveConsent($userId, $destinationId, $attributeSet)
    {
        assert('is_string($userId)');
        assert('is_string($destinationId)');
        assert('is_string($attributeSet)');

        // Check for old consent (with different attribute set)
        $st = $this->_execute(
            'UPDATE ' . $this->_table . ' ' .
            'SET consent_date = ' . $this->_dateTime . ', usage_date = ' . $this->_dateTime . ', attribute = ? ' .
            'WHERE hashed_user_id = ? AND service_id = ?',
            array($attributeSet, $userId, $destinationId)
        );

        if ($st === false) {
            return;
        }

        if ($st->rowCount() > 0) {
            // Consent has already been stored in the database
            SimpleSAML_Logger::debug('consent:Database - Updated old consent.');
            return;
        }

        // Add new consent
        $st = $this->_execute(
            'INSERT INTO ' . $this->_table . ' (' .
            'consent_date, usage_date, hashed_user_id, service_id, attribute' .
            ') ' .
            'VALUES (' . $this->_dateTime . ', ' . $this->_dateTime . ', ?, ?, ?)',
            array($userId, $destinationId, $attributeSet)
        );

        if ($st !== false) {
            SimpleSAML_Logger::debug('consent:Database - Saved new consent.');
        }
        return true;
    }

    /**
     * Delete consent.
     *
     * Called when a user revokes consent for a given destination.
     *
     * @param string $userId        The hash identifying the user at an IdP.
     * @param string $destinationId A string which identifies the destination.
     *
     * @return int Number of consents deleted
     */
    public function deleteConsent($userId, $destinationId)
    {
        assert('is_string($userId)');
        assert('is_string($destinationId)');

        $st = $this->_execute(
            'DELETE FROM ' . $this->_table . ' ' .
            'WHERE hashed_user_id = ? AND service_id = ?;',
            array($userId, $destinationId)
        );

        if ($st === false) {
            return;
        }

        if ($st->rowCount() > 0) {
            SimpleSAML_Logger::debug('consent:Database - Deleted consent.');
            return $st->rowCount();
        } else {
            SimpleSAML_Logger::warning(
                'consent:Database - Attempted to delete nonexistent consent'
            );
        }
    }

    /**
     * Delete all consents.
     * 
     * @param string $userId The hash identifying the user at an IdP.
     *
     * @return int Number of consents deleted
     */
    public function deleteAllConsents($userId)
    {
        assert('is_string($userId)');

        $st = $this->_execute(
            'DELETE FROM ' . $this->_table . ' WHERE hashed_user_id = ?',
            array($userId)
        );

        if ($st === false) {
            return;
        }

        if ($st->rowCount() > 0) {
            SimpleSAML_Logger::debug(
                'consent:Database - Deleted (' . $st->rowCount() . ') consent(s).'
            );
            return $st->rowCount();
        } else {
            SimpleSAML_Logger::warning(
                'consent:Database - Attempted to delete nonexistent consent'
            );
        }
    }

    /**
     * Retrieve consents.
     *
     * This function should return a list of consents the user has saved.
     *
     * @param string $userId The hash identifying the user at an IdP.
     *
     * @return array Array of all destination ids the user has given consent for.
     */
    public function getConsents($userId)
    {
        assert('is_string($userId)');

        $ret = array();

        $st = $this->_execute(
            'SELECT service_id, attribute, consent_date, usage_date ' .
            'FROM ' . $this->_table . ' ' .
            'WHERE hashed_user_id = ?',
            array($userId)
        );

        if ($st === false) {
            return array();
        }

        while ($row = $st->fetch(PDO::FETCH_NUM)) {
            $ret[] = $row;
        }

        return $ret;
    }

    /**
     * Prepare and execute statement.
     *
     * This function prepares and executes a statement. On error, false will be
     * returned.
     *
     * @param string $statement  The statement which should be executed.
     * @param array  $parameters Parameters for the statement.
     *
     * @return PDOStatement|false  The statement, or false if execution failed.
     */
    private function _execute($statement, $parameters)
    {
        assert('is_string($statement)');
        assert('is_array($parameters)');

        $db = $this->_getDB();
        if ($db === false) {
            return false;
        }

        $st = $db->prepare($statement);
        if ($st === false) {
            if ($st === false) {
                SimpleSAML_Logger::error(
                    'consent:Database - Error preparing statement \'' .
                    $statement . '\': ' . self::_formatError($db->errorInfo())
                );
                return false;
            }
        }

        if ($st->execute($parameters) !== true) {
            SimpleSAML_Logger::error(
                'consent:Database - Error executing statement \'' .
                $statement . '\': ' . self::_formatError($st->errorInfo())
            );
            return false;
        }

        return $st;
    }

    /**
     * Get statistics from the database
     *
     * The returned array contains 3 entries
     * - total: The total number of consents
     * - users: Total number of uses that have given consent
     * ' services: Total number of services that has been given consent to
     *
     * @return array Array containing the statistics
     * @TODO Change fixed table name to condig option
     */
    public function getStatistics()
    {
        $ret = array();

        // Get total number of consents
        $st = $this->_execute('SELECT COUNT(*) AS no FROM consent', array());
        
        if ($st === false) {
            return array(); 
        }

        if ($row = $st->fetch(PDO::FETCH_NUM)) {
            $ret['total'] = $row[0];
        }

        // Get total number of users that has given consent
        $st = $this->_execute(
            'SELECT COUNT(*) AS no ' .
            'FROM (SELECT DISTINCT hashed_user_id FROM consent ) AS foo',
            array()
        );
        
        if ($st === false) {
            return array(); 
        }

        if ($row = $st->fetch(PDO::FETCH_NUM)) {
            $ret['users'] = $row[0];
        }

        // Get total number of services that has been given consent to
        $st = $this->_execute(
            'SELECT COUNT(*) AS no ' .
            'FROM (SELECT DISTINCT service_id FROM consent) AS foo',
            array()
        );
        
        if ($st === false) {
            return array();
        }

        if ($row = $st->fetch(PDO::FETCH_NUM)) {
            $ret['services'] = $row[0];
        }

        return $ret;
    }

    /**
     * Create consent table.
     *
     * This function creates the table with consent data.
     *
     * @return True if successful, false if not.
     *
     * @TODO Remove this function since it is not used
     */
    private function _createTable()
    {
        $db = $this->_getDB();
        if ($db === false) {
            return false;
        }

        $res = $this->db->exec(
            'CREATE TABLE ' . $this->_table . ' (' .
            'consent_date TIMESTAMP NOT null,' .
            'usage_date TIMESTAMP NOT null,' .
            'hashed_user_id VARCHAR(80) NOT null,' .
            'service_id VARCHAR(255) NOT null,' .
            'attribute VARCHAR(80) NOT null,' .
            'UNIQUE (hashed_user_id, service_id)' .
            ')'
        );
        if ($res === false) {
            SimpleSAML_Logger::error(
                'consent:Database - Failed to create table \'' .
                $this->_table . '\'.'
            );
            return false;
        }

        return true;
    }

    /**
     * Get database handle.
     *
     * @return PDO|false Database handle, or false if we fail to connect.
     */
    private function _getDB()
    {
        if ($this->_db !== null) {
            return $this->_db;
        }

        $driver_options = array();
        if (isset($this->_timeout)) {
            $driver_options[PDO::ATTR_TIMEOUT] = $this->_timeout;
        }

        $this->_db = new PDO($this->_dsn, $this->_username, $this->_password, $driver_options);

        return $this->_db;
    }

    /**
     * Format PDO error.
     *
     * This function formats a PDO error, as returned from errorInfo.
     *
     * @param array $error The error information.
     * 
     * @return string Error text.
     */
    private static function _formatError($error)
    {
        assert('is_array($error)');
        assert('count($error) >= 3');

        return $error[0] . ' - ' . $error[2] . ' (' . $error[1] . ')';
    }

    /**
     * A quick selftest of the consent database.
     *
     * @return boolen TRUE if OK, FALSE if not. Will throw an exception on connection errors.
     */
    public function selftest()
    {
        $st = $this->_execute(
            'SELECT * FROM ' . $this->_table . ' WHERE hashed_user_id = ? AND service_id = ? AND attribute = ?',
            array('test', 'test', 'test')
        );

        if ($st === FALSE) {
            /* Normally, the test will fail by an exception, so we won't reach this code. */
            return FALSE;
        }

	return TRUE;
    }
}
