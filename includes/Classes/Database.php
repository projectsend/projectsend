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
}
