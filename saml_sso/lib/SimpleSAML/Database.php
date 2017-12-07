<?php
namespace SimpleSAML;

/**
 * This file implements functions to read and write to a group of database
 * servers.
 *
 * This database class supports a single database, or a master/slave
 * configuration with as many defined slaves as a user would like.
 *
 * The goal of this class is to provide a single mechanism to connect to a database
 * that can be reused by any component within SimpleSAMLphp including modules.
 * When using this class, the global configuration should be passed here, but
 * in the case of a module that has a good reason to use a different database,
 * such as sqlauth, an alternative config file can be provided.
 *
 * @author Tyler Antonio, University of Alberta. <tantonio@ualberta.ca>
 * @package SimpleSAMLphp
 */

class Database
{

    /**
     * This variable holds the instance of the session - Singleton approach.
     */
    private static $instance = array();

    /**
     * PDO Object for the Master database server
     */
    private $dbMaster;

    /**
     * Array of PDO Objects for configured database
     * slaves
     */
    private $dbSlaves = array();

    /**
     * Prefix to apply to the tables
     */
    private $tablePrefix;

    /**
     * Array with information on the last error occurred.
     */
    private $lastError;


    /**
     * Retrieves the current database instance. Will create a new one if there isn't an existing connection.
     *
     * @param \SimpleSAML_Configuration $altConfig Optional: Instance of a SimpleSAML_Configuration class
     *
     * @return \SimpleSAML\Database The shared database connection.
     */
    public static function getInstance($altConfig = null)
    {
        $config = ($altConfig) ? $altConfig : \SimpleSAML_Configuration::getInstance();
        $instanceId = self::generateInstanceId($config);

        // check if we already have initialized the session
        if (isset(self::$instance[$instanceId])) {
            return self::$instance[$instanceId];
        }

        // create a new session
        self::$instance[$instanceId] = new Database($config);
        return self::$instance[$instanceId];
    }


    /**
     * Private constructor that restricts instantiation to getInstance().
     *
     * @param \SimpleSAML_Configuration $config Instance of the SimpleSAML_Configuration class
     */
    private function __construct($config)
    {
        $driverOptions = $config->getArray('database.driver_options', array());
        if ($config->getBoolean('database.persistent', true)) {
            $driverOptions = array(\PDO::ATTR_PERSISTENT => true);
        }

        // connect to the master
        $this->dbMaster = $this->connect(
            $config->getString('database.dsn'),
            $config->getString('database.username', null),
            $config->getString('database.password', null),
            $driverOptions
        );

        // connect to any configured slaves
        $slaves = $config->getArray('database.slaves', array());
        if (count($slaves >= 1)) {
            foreach ($slaves as $slave) {
                array_push(
                    $this->dbSlaves,
                    $this->connect(
                        $slave['dsn'],
                        $slave['username'],
                        $slave['password'],
                        $driverOptions
                    )
                );
            }
        }

        $this->tablePrefix = $config->getString('database.prefix', '');
    }


    /**
     * Generate an Instance ID based on the database
     * configuration.
     *
     * @param \SimpleSAML_Configuration $config Configuration class
     *
     * @return string $instanceId
     */
    private static function generateInstanceId($config)
    {
        $assembledConfig = array(
            'master' => array(
                'database.dsn'        => $config->getString('database.dsn'),
                'database.username'   => $config->getString('database.username', null),
                'database.password'   => $config->getString('database.password', null),
                'database.prefix'     => $config->getString('database.prefix', ''),
                'database.persistent' => $config->getBoolean('database.persistent', false),
            ),
            'slaves' => $config->getArray('database.slaves', array()),
        );

        return sha1(serialize($assembledConfig));
    }


    /**
     * This function connects to a database.
     *
     * @param string $dsn Database connection string
     * @param string $username SQL user
     * @param string $password SQL password
     * @param array  $options PDO options
     *
     * @throws \Exception If an error happens while trying to connect to the database.
     * @return \PDO object
     */
    private function connect($dsn, $username, $password, $options)
    {
        try {
            $db = new \PDO($dsn, $username, $password, $options);
            $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            return $db;
        } catch (\PDOException $e) {
            throw new \Exception("Database error: ".$e->getMessage());
        }
    }


    /**
     * This function randomly selects a slave database server
     * to query. In the event no slaves are configured, it
     * will return the master.
     *
     * @return \PDO object
     */
    private function getSlave()
    {
        if (count($this->dbSlaves) > 0) {
            $slaveId = rand(0, count($this->dbSlaves) - 1);
            return $this->dbSlaves[$slaveId];
        } else {
            return $this->dbMaster;
        }
    }


    /**
     * This function simply applies the table prefix to a supplied table name.
     *
     * @param string $table Table to apply prefix to, if configured
     *
     * @return string Table with configured prefix
     */
    public function applyPrefix($table)
    {
        return $this->tablePrefix.$table;
    }


    /**
     * This function queries the database
     *
     * @param \PDO   $db PDO object to use
     * @param string $stmt Prepared SQL statement
     * @param array  $params Parameters
     *
     * @throws \Exception If an error happens while trying to execute the query.
     * @return \PDOStatement object
     */
    private function query($db, $stmt, $params)
    {
        assert('is_object($db)');
        assert('is_string($stmt)');
        assert('is_array($params)');

        try {
            $query = $db->prepare($stmt);

            foreach ($params as $param => $value) {
                if (is_array($value)) {
                    $query->bindValue(":$param", $value[0], ($value[1]) ? $value[1] : \PDO::PARAM_STR);
                } else {
                    $query->bindValue(":$param", $value, \PDO::PARAM_STR);
                }
            }

            $query->execute();

            return $query;
        } catch (\PDOException $e) {
            $this->lastError = $db->errorInfo();
            throw new \Exception("Database error: ".$e->getMessage());
        }
    }


    /**
     * This function queries the database without using a
     * prepared statement.
     *
     * @param \PDO   $db PDO object to use
     * @param string $stmt Prepared SQL statement
     *
     * @throws \Exception If an error happens while trying to execute the query.
     * @return \PDOStatement object
     */
    private function exec($db, $stmt)
    {
        assert('is_object($db)');
        assert('is_string($stmt)');

        try {
            $query = $db->exec($stmt);

            return $query;
        } catch (\PDOException $e) {
            $this->lastError = $db->errorInfo();
            throw new \Exception("Database error: ".$e->getMessage());
        }
    }


    /**
     * This executes queries directly on the master.
     *
     * @param string $stmt Prepared SQL statement
     * @param array  $params Parameters
     *
     * @return \PDOStatement object
     */
    public function write($stmt, $params = array())
    {
        $db = $this->dbMaster;

        if (is_array($params)) {
            return $this->query($db, $stmt, $params);
        } else {
            return $this->exec($db, $stmt);
        }
    }


    /**
     * This executes queries on a database server
     * that is determined by this::getSlave()
     *
     * @param string $stmt Prepared SQL statement
     * @param array  $params Parameters
     *
     * @return \PDOStatement object
     */
    public function read($stmt, $params = array())
    {
        $db = $this->getSlave();

        return $this->query($db, $stmt, $params);
    }


    /**
     * Return an array with information about the last operation performed in the database.
     *
     * @return array The array with error information.
     */
    public function getLastError()
    {
        return $this->lastError;
    }
}
