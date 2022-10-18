<?php
namespace ProjectSend\Classes;

class ServerRequirements
{
    public function __construct(\ProjectSend\Classes\Database $database = null)
    {
        $this->dbh = (!empty($database)) ? $database->getPdo() : null;
    }

    public function checkRequirements()
    {
        $errors_found = [];

        // Check for PDO extensions
        $pdo_available_drivers = \PDO::getAvailableDrivers();
        if (empty($pdo_available_drivers)) {
            $errors_found[] = sprintf(__('Missing required extension: %s', 'cftp_admin'), 'pdo');
        } else {
            if (defined('DB_DRIVER') && (DB_DRIVER == 'mysql') && !defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                $errors_found[] = sprintf(__('Missing required extension: %s', 'cftp_admin'), 'pdo');
            }
            if (defined('DB_DRIVER') && (DB_DRIVER == 'mssql') && !in_array('dblib', $pdo_available_drivers)) {
                $errors_found[] = sprintf(__('Missing required extension: %s', 'cftp_admin'), 'pdo');
            }
        }

        // Version requirements
        $version_not_met = __('%s minimum version not met. Please upgrade to at least version %s', 'cftp_admin');

        // php
        if (version_compare(phpversion(), REQUIRED_VERSION_PHP, "<")) {
            $errors_found[] = sprintf($version_not_met, 'php', REQUIRED_VERSION_PHP);
        }

        // mysql
        if (!empty($this->dbh)) {
            $version_mysql = $this->dbh->query('SELECT version()')->fetchColumn();
            if (version_compare($version_mysql, REQUIRED_VERSION_MYSQL, "<")) {
                $errors_found[] = sprintf($version_not_met, 'MySQL', REQUIRED_VERSION_MYSQL);
            }
        }

        return $errors_found;
    }

    public function requirementsMet()
    {
        $errors = $this->checkRequirements();

        if (count($errors) > 0) {
            return false;
        }

        return true;
    }

    public function getErrors()
    {
        return $this->checkRequirements();
    }
}
