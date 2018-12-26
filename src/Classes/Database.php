<?php

namespace ProjectSend\Classes;

use \PDO;

class Database
{
    protected $dbh;

    function __construct(PDO $dbh)
    {
        $this->dbh = $dbh;
    }

    /**
     * Check if a table exists in the current database.
     * by esbite on http://stackoverflow.com/questions/1717495/check-if-a-database-table-exists-using-php-pdo
     *
     * @param string $table Table to search for.
     * @return bool TRUE if table exists, FALSE if no table found.
     */
    public function table_exists($table)
    {
        try {
            $this->result = $this->dbh->prepare("SELECT 1 FROM $table LIMIT 1");
            $this->result->execute();
        } catch (Exception $e) {
            return false;
        }

        // Result is either boolean FALSE (no table found) or PDOStatement Object (table found)
        return $this->result !== false;
    }

}