<?php
/**
 * Runs databases updates from the admin area, and after the installing process
 * Database update number format is YYYYMMDDXX, where XX is an incremental update
 * number of the day, starting from 01.
 *
 * @package		ProjectSend
 * @subpackage	Updates
 */
namespace ProjectSend\Classes;
use \PDO;

class DatabaseUpgrade
{
    private $updates_applied;
    private $current_database_version;
    private $current_version;
    private $dbh;
    private $sql_mode_dates_status;
    private $upgrades;
    private $last_upgrade;

    public function __construct()
    {
        global $dbh;
        $this->dbh = $dbh;

        $this->current_version = substr(CURRENT_VERSION, 1);
    
        $this->updates_applied = [];
        $this->current_database_version = get_option('database_version');
        $this->sql_mode_dates_status = false;

        foreach (glob(UPGRADES_DIR.DS.'*.php') as $file) {
            $this->upgrades[] = basename($file, '.php');
        }
    }

    public function getAppliedUpdates()
    {
        return $this->updates_applied;
    }

    private function runBeforeUpgrades()
    {
        $statement = $this->dbh->prepare("SET SQL_MODE='ALLOW_INVALID_DATES';");
        $statement->execute();
        $this->sql_mode_dates_status = true;
    }

    private function runAfterUpgrades()
    {
        $statement = $this->dbh->prepare("SET SQL_MODE='';");
        $statement->execute();
        $this->sql_mode_dates_status = false;

		/** Record the action log */
        $user_id = (defined('IS_INSTALL')) ? 1 : CURRENT_USER_ID;
        $logger = new \ProjectSend\Classes\ActionsLog;
        $logger->addEntry([
            'action' => 49,
            'owner_id' => $user_id,
            'details' => [
                'database_version' => $this->last_upgrade,
            ],
        ]);
        unset($logger);
    }

    public function upgradeDatabase($requires_system_user = false)
    {
        if ($requires_system_user) {
            $allowed_update = array(9,8,7);
            if (!current_role_in($allowed_update)) {
                return false;
            }
        }

        foreach ($this->upgrades as $database_number) {
            if ($this->current_database_version < $database_number) {
                $this->doUpgrade($database_number);
            }
        }

        if ($this->sql_mode_dates_status == true) {
            $this->runAfterUpgrades();
        }
    }

    private function doUpgrade($number)
    {
        if ($this->sql_mode_dates_status == false) {
            $this->runBeforeUpgrades();
        }

        require_once(UPGRADES_DIR.DS.$number.'.php');
        $function = 'upgrade_'.$number;
        $function();

        $this->setCurrentVersion($number);

        $this->updates_applied[] = $number;
    }

    private function setCurrentVersion($version)
    {
        $this->last_upgrade = $version;

        save_option('database_version', $version);
    }
}