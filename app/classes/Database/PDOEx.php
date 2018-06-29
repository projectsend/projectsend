<?php
/**
 * Simple PDO extensions
 *
 * @package		ProjectSend
 *
 */

namespace ProjectSend\Database;
use PDO;

class PDOEx extends \PDO
{
    private $queries = 0;

    public function query($query, $options = array())
    {
        ++$this->queries;
        return parent::query($query);
    }

    public function prepare($statement, $options = array())
    {
        ++$this->queries;
        return parent::prepare($statement);
    }

    public function GetCount()
    {
        return $this->queries;
    }
}