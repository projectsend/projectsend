<?php
/**
 * Look for available updates for the main app.
 * The latest version information is retrieved from an online JSON response.
 *
 * @package		ProjectSend
 * @subpackage	Classes
 */
namespace ProjectSend;

class UpdatesCore extends Base
{
    public $latest_available_version;
    public $dbh;
    public $container;

    function __construct($container)
    {
        parent::__construct($container);
    }

    function __invoke()
    {
        $this->doSilentUpdates();
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
        $this->new_record_action = $logger->add_entry($this->log_action_args);
    }


    /** Helpers */

    /** Add a new row to the options table */
    function add_option_if_not_exists($row, $value)
    {
        global $dbh;
        $statement = $dbh->prepare("SELECT * FROM " . TABLE_OPTIONS . " WHERE name = :option");
        $statement->bindParam(':option', $row);
        $statement->execute();

        if ( $statement->rowCount() == 0 ) {
            $statement = $dbh->prepare("INSERT INTO " . TABLE_OPTIONS . " (name, value) VALUES (:option, :value)");
            $statement->bindParam(':option', $row);
            $statement->bindValue(':value', $value);
            $statement->execute();

            return true;
        }
        else {
            return false;
        }
    }

    /** Called on r348 */
    function update_chmod_emails()
    {
        global $updates_made;
        global $updates_errors;
        global $updates_error_messages;

        $chmods = 0;
        $emails_folder = EMAIL_TEMPLATES_DIR;
        if (@chmod($emails_folder, 0755)) { $chmods++; } else { $updates_errors++; }

        $emails_files = glob($emails_folder."*", GLOB_NOSORT);

        foreach ($emails_files as $emails_file) {
            if(is_file($emails_file)) {
                if (@chmod($emails_file, 0755)) { $chmods++; } else { $updates_errors++; }
            }
        }

        if ($chmods > 0) {
            $updates_made++;
        }

        if ($updates_errors > 0) {
            $updates_error_messages[] = __("The chmod values of the emails folder and the html templates inside couldn't be set. If ProjectSend isn't sending notifications emails, please set them manually to 777.", 'cftp_admin');
        }
    }

    /** Called on r352 */
    function chmod_main_files()
    {
        global $updates_made;
        global $updates_errors;
        global $updates_error_messages;

        $chmods = 0;
        $system_files = array(
                                'cfg' => CONFIG_FILE
                            );
        foreach ($system_files as $sys_file) {
            if (!file_exists($sys_file)) {
                $updates_errors++;
            }
            else {
                $current_chmod = substr(sprintf('%o', fileperms($sys_file)), -4);
                if ($current_chmod != '0644') {
                    @chmod($sys_file, 0644);
                    $chmods++;
                }
            }
        }

        if ($chmods > 0) {
            $updates_made++;
        }

        if ($updates_errors > 0) {
            $updates_error_messages[] = sprintf(__("A safe chmod value couldn't be set for one or more system files. Please make sure that at least %s has a chmod of 644 for security reasons.", 'cftp_admin'), CONFIG_FILE);
        }
    }

    /** Called on r354 */
    function import_files_relations()
    {
        global $dbh;
        global $updates_made;
        global $updates_errors;
        global $updates_error_messages;

        /**
         * Prepare the variables to be used on this update
         */
        $files_to_import = array();
        $get_clients_info = array();
        $imported_ok = 0;
        $imported_error = 0;
        $unimported_files = array();

        /**
         * Get every file and it's important information from the files database table.
         */
        $statement = $dbh->prepare("SELECT id, filename, timestamp, client_user, hidden, download_count FROM " . TABLE_FILES . " WHERE client_user != ''");
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        while( $row = $statement->fetch() ) {
            $files_to_import[$row['id']] = array(
                                    'file_id' => $row['id'],
                                    'title' => $row['filename'],
                                    'timestamp' => $row['timestamp'],
                                    'client_id' => $row['client_user'],
                                    'hidden' => $row['hidden'],
                                    'download_count' => $row['download_count']
                                );
            $get_clients_info[] = $row['client_user'];
        }

        /**
         * Get the information of each client found on the
         * previous step.
         */
        $users = implode(',', $get_clients_info);
        $statement = $dbh->prepare("SELECT id, username FROM " . TABLE_USERS . " WHERE FIND_IN_SET(username, :users)");
        $statement->bindParam(':users', $users);
        $statement->execute();
        $statement->setFetchMode(PDO::FETCH_ASSOC);
        while( $row = $statement->fetch() ) {
            $found_users[$row['username']] = $row['id'];
        }

        /**
         * Create a new record on the files_relations table
         * using the information from the previous 2 queries, to
         * relate every file to existing users/clients.
         */
        foreach ($files_to_import as $this_file) {
            /**
             * Only continue if the client exists on the database
             */
            if (array_key_exists($this_file['client_id'],$found_users)) {
                $statement = $dbh->prepare("INSERT INTO " . TABLE_FILES_RELATIONS . " (timestamp, file_id, client_id, hidden, download_count)"
                                        ." VALUES (:timestamp, :file_id, :client_id, :hidden, :download_count)");
                $statement->bindParam(':timestamp', $this_file['timestamp']);
                $statement->bindParam(':file_id', $this_file['file_id'], PDO::PARAM_INT);
                $statement->bindParam(':client_id', $found_users[$this_file['client_id']], PDO::PARAM_INT);
                $statement->bindParam(':hidden', $this_file['hidden'], PDO::PARAM_INT);
                $statement->bindParam(':download_count', $this_file['download_count'], PDO::PARAM_INT);
                $statement->execute();

                if ($statement) {
                    $imported_ok++;
                }
                else {
                    $imported_error++;
                    $unimported_files[] = array(
                                                'title' => $this_file['title'],
                                                'client' => $found_users[$this_file['client_id']]
                                            );
                }
            }
        }

        /**
         * Did any of the files relations fail?
         */
        if ($imported_error > 0) {
            $updates_error_messages[100] = __("This version changes the way files-to-clients relationships are stored on the database making it possible to assign a file to multiple clients. However some files did not update successfully. The following files may need to be reassigned to their clients by using the \"Find orphan files\" tool:", 'cftp_admin');
            $updates_error_messages[100] .= '<ul>';
                foreach ($unimported_files as $unimported) {
                    $updates_error_messages[100] .= '<li>File: <strong>'.$unimported['title'].'</strong> Assigned to: <strong>'.$unimported['client'].'</strong></li>';
                }
            $updates_error_messages[100] .= '</ul>';
        }

        if ($imported_ok > 0) {
            $updates_made++;
        }
    }

    /** Do silent updates */
        /**
     * r431 updates
     * A new database table was added.
     * Password reset support is now supported.
     */
    private function doSilentUpdates()
    {
        if (431 > LAST_UPDATE) {
            if ( !self::table_exists( TABLE_PASSWORD_RESET ) ) {
                $query = '
                CREATE TABLE IF NOT EXISTS `' . TABLE_PASSWORD_RESET . '` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `user_id` int(11) DEFAULT NULL,
                    `token` varchar(32) NOT NULL,
                    `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                    `used` int(1) DEFAULT \'0\',
                    FOREIGN KEY (`user_id`) REFERENCES ' . TABLE_USERS . '(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;
                ';
                $dbh->query($query);
                $updates_made++;
            }
        }

        /**
         * r437 updates
         * A new database table was added.
         * Password reset support is now supported.
         */
        if (520 > LAST_UPDATE) {
            $q = $dbh->query("ALTER TABLE " . TABLE_USERS . " MODIFY user VARCHAR(".MAX_USER_CHARS.") NOT NULL");
            $q2 = $dbh->query("ALTER TABLE " . TABLE_USERS . " MODIFY password VARCHAR(".MAX_PASS_CHARS.") NOT NULL");
            if ($q && $q2) {
                $updates_made++;
            }
        }

        /**
         * Pre 1.0.0 updates
         */
        if (1098 > LAST_UPDATE) {
            $statement = $dbh->query("ALTER TABLE " . TABLE_USERS . " CHANGE `user` `username` VARCHAR(60) CHARACTER SET utf8 COLLATE utf8mb4_general_ci NOT NULL");
            $updates_made++;
        }

        /*
        if (201807052 > LAST_UPDATE) {
            $statement = $dbh->query("ALTER TABLE " . TABLE_FILES . " CHANGE `filename` `title` TEXT CHARACTER SET utf8 COLLATE utf8mb4_general_ci NOT NULL");
            $updates_made++;
        }
        */
    }
}