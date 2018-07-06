<?php
/**
 * Look for available updates for the main app.
 * The latest version information is retrieved from an online JSON response.
 *
 * @package		ProjectSend
 * @subpackage	Classes
 */
namespace ProjectSend;
use \PDO;

class UpdatesCore
{
    public $latest_available_version;

    function __construct()
    {
        global $dbh;
        $this->dbh = $dbh;

        $this->has_update_available();
    }

    /**
     * Compare against the online releases archive.
     *
     * @return bool
     */
    private function lookup_latest_version()
    {
        $this->ret = false;

        $this->versions_get = file_get_contents(UPDATES_JSON_URI);
        $this->versions_json = json_decode( $this->versions_get );
        if ( !empty( $this->versions_json ) ) {
            $this->latest = $this->versions_json[0];
            $this->latest_available_version = $this->latest->version;

            if ( version_compare(CURRENT_VERSION, $this->latest->version, '<') ) {
                /**
                * Save the information from the new release to the database.
                */
                $this->save_options = [
                    'version_new_number'	=> $this->latest->version,
                    'version_new_url'		=> $this->latest->download,
                    'version_new_chlog'		=> $this->latest->changelog,
                    'version_new_security'	=> $this->latest->diff->security,
                    'version_new_features'	=> $this->latest->diff->features,
                    'version_new_important'	=> $this->latest->diff->important,
                    'version_new_found'		=> 1,
                ];
                foreach ( $this->save_options as $this->option => $this->value ) {
                    $this->query = "UPDATE " . TABLE_OPTIONS . " SET value=:value WHERE name=:name";
                    $this->sql = $this->dbh->prepare( $this->query );
                    $this->sql->bindParam(':value', $this->value);
                    $this->sql->bindParam(':name', $this->option);
                    $this->sql->execute();
                }

                $this->ret = true;
            }
            else {
                $this->reset_update_status();
            }

            /**
            * Change the date and versions values on the db so it's not checked again today.
            */
            $this->statement = $this->dbh->prepare("UPDATE " . TABLE_OPTIONS . " SET value = :today WHERE name='version_last_check'");
            $this->statement->bindParam(':today', $this->today);
            $this->statement->execute();
        }


        return $this->ret;
    }

    /**
     * Compares the current version with the latest one from the JSON response.
     *
     * @return bool
     */
    public function has_update_available()
    {
        $this->has_update = false;

        /**
         * Compare the date for the last checked with 
         * today's. Checks are done only once per day.
         */
        $this->today = date('d-m-Y');

        if (VERSION_LAST_CHECK != $this->today) {
            $this->has_update = $this->lookup_latest_version();
        }
        else {
            if (VERSION_NEW_FOUND == '1' && version_compare(CURRENT_VERSION, VERSION_NEW_NUMBER, '<')) {
                $this->has_update = true;
            }
        }

        return $this->has_update;
    }

    /**
     * Get the latest version information from the database
     * 
     * @return array
     */
    public function get_new_version_info()
    {
        $this->return = [];

        $this->get_options = [
            'version_new_number'	=> 'version',
            'version_new_url'		=> 'download',
            'version_new_chlog'		=> 'changelog',
            'version_new_security'	=> 'security',
            'version_new_features'	=> 'features',
            'version_new_important'	=> 'important',
            'version_new_found'		=> 'found',
        ];
        $this->placeholders = str_repeat('?, ', count($this->get_options) - 1) . '?';

        $this->query = "SELECT name, value FROM " . TABLE_OPTIONS . " WHERE name IN($this->placeholders)";
        $this->statement = $this->dbh->prepare($this->query);
        $this->statement->execute(array_keys($this->get_options));
        $this->statement->setFetchMode(PDO::FETCH_ASSOC);

        if ( $this->statement->rowCount() > 0) {
            while ( $this->row = $this->statement->fetch() ) {
                $this->return[$this->get_options[$this->row['name']]] = $this->row['value'];
            }
        }

        return $this->return;
    }

    /**
     * Resets all values to 0 so the core update available message is not shown.
     *
     * @return void
     */
    public function reset_update_status()
    {
        // Reset lookup status only if the current version >= latest found version
        // A reset can be triggered by just updating the database, not the whole app
        if ( version_compare(CURRENT_VERSION, VERSION_NEW_NUMBER, '>=') ) {
            $this->save_options = [
                'version_new_number'	=> '',
                'version_new_url'		=> '',
                'version_new_chlog'		=> '',
                'version_new_security'	=> '',
                'version_new_features'	=> '',
                'version_new_important'	=> '',
                'version_new_found'		=> 0,
            ];
            foreach ( $this->save_options as $this->option => $this->value ) {
                $this->query = "UPDATE " . TABLE_OPTIONS . " SET value=:value WHERE name=:name";
                $this->sql = $this->dbh->prepare( $this->query );
                $this->sql->bindParam(':value', $this->value);
                $this->sql->bindParam(':name', $this->option);
                $this->sql->execute();
            }
        }
    }

    /**
     * Apply updates for versions older than 1.0.0
     */
    public function apply_legacy_updates()
    {
        if (LAST_UPDATE < DATABASE_VERSION) {
            require_once INCLUDES_DIR . DS . 'core.update.legacy.php';
        }
    }

    /**
     * Set the last update value on the database
     * Database version number is defined on config.php
     */
    public function save_database_version_number()
    {
        /** Update the database */
        $this->statement = $this->dbh->prepare("UPDATE " . TABLE_OPTIONS . " SET value = :version WHERE name='last_update'");
        $this->statement->bindValue(':version', DATABASE_VERSION);
        $this->statement->execute();

        /** Record the action log */
        global $logger;
        $this->log_action_args = array(
                                'action' => 30,
                                'owner_id' => CURRENT_USER_ID,
                                'affected_account_name' => CURRENT_VERSION,
                                'affected_file_name' => DATABASE_VERSION
                            );
        $this->new_record_action = $logger->log_action_save($this->log_action_args);
    }
}