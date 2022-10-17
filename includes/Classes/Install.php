<?php
namespace ProjectSend\Classes;

class Install {
    private $installed;
    
    public function __construct(Database $database, \League\Route\Router $router)
    {
        $this->router = $router;
        $this->database = $database;
        $this->dbh = $database->getPdo();
    }

    public function isInstalled()
    {
        $this->installed = false;

        $tables_need = array(
            $this->database->getTable('users')
        );
    
        $tables_missing = 0;
        foreach ($tables_need as $table) {
            if (!$this->database->tableExists($table)) {
                $tables_missing++;
            }
        }
        if ($tables_missing == 0) {
            $this->installed = true;
        }

        return $this->installed;
    }
}
