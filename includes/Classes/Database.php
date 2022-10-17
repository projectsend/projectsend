<?php
namespace ProjectSend\Classes;
use \PDO;
use \PDOException;

class Database
{
    protected $dbh;
    protected $connected;
    private $error;

    public function __construct($args = [])
    {        
        $this->dbh = null;
        $this->connected = false;

        if (empty($args)) {
            throw new \Exception(__('Database arguments not set','cftp_admin'), 1);
        }

        $driver = isset($args['driver']) ? $args['driver'] : 'mysql';
        $host = isset($args['host']) ? $args['host'] : 'localhost';
        $port = isset($args['port']) ? $args['port'] : '3306';
        $charset = isset($args['charset']) ? $args['charset'] : 'utf8';
        $database = isset($args['database']) ? $args['database'] : '';
        $username = isset($args['username']) ? $args['username'] : '';
        $password = isset($args['password']) ? $args['password'] : '';
        $exit = isset($args['exit']) ? $args['exit'] : true;

        try {
            $this->dbh = new PDO("$driver:host=$host;port=$port;dbname=$database;charset=$charset", $username, $password, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
            $this->dbh->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
            $this->dbh->setAttribute( PDO::ATTR_EMULATE_PREPARES, false );
            $this->connected = true;
        }
        catch(PDOException $e) {
            $this->error = $e->getMessage();
            if ($exit) {
                exit;
            }
        }

        $this->setUpTables();
    }

    public function getPdo()
    {
        return $this->dbh;
    }

    public function isConnected()
    {
        return $this->connected;
    }

    public function getError()
    {
        return $this->error;
    }

    public function setUpTables()
    {
        $this->prefix = 'tbl_';
        if (defined('TABLES_PREFIX')) {
            $this->prefix = TABLES_PREFIX;
        }

        $this->tables = $this->getTables();
    }

    public function getTablesPrefix()
    {
        return $this->prefix;
    }

    public function getTables()
    {
        return [
            'files',
            'files_relations',
            'downloads',
            'notifications',
            'options',
            'users',
            'user_meta',
            'groups',
            'members',
            'members_requests',
            'folders',
            'categories',
            'categories_relations',
            'actions_log',
            'password_reset',
            'logins_failed',
            'cron_log',
            'custom_assets',
            'user_limit_upload_to',
            'authentication_codes',
        ];
    }

    public function getTable($table)
    {
        if (empty($this->tables)) {
            $this->setUpTables();
        }

        if (!in_array($table, $this->tables)) {
            return null;
        }

        return $this->prefix . $table;
    }

    public function tableExists($table)
    {
        $result = false;

        if (!empty($this->dbh)) {
            try {
                $statement = $this->dbh->prepare("SELECT 1 FROM $table LIMIT 1");
                $result = $statement->execute();
            } catch (\Exception $e) {
                return false;
            }
        }
    
        // Result is either boolean FALSE (no table found) or PDOStatement Object (table found)
        return $result !== false;
    }
}
