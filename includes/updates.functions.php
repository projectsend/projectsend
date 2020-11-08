<?php
/**
 * Define the common functions used on the installer and updates.
 *
 * @package		ProjectSend
 * @subpackage	Functions
 */

function get_latest_version_data()
{
    /** Remove "r" from version */
    $current_version = substr(CURRENT_VERSION, 1);
    
    /**
     * Compare against the online value.
     */
    $versions = getJson(UPDATES_FEED_URI, '-1 days');
    $versions = json_decode($versions);

    $latest = $versions[0];

    $online_version = substr($latest->version, 1);

    if ($online_version > $current_version) {
        $return = [
            'local_version' => $current_version,
            'latest_version' => $online_version,
            'update_available' => '1',
            'url' => $latest->download,
            'chlog' => $latest->changelog,
            'diff' => [
                'security' => $latest->diff->security,
                'features' => $latest->diff->features,
                'important' => $latest->diff->important,
            ],
        ];

        return json_encode($return);
    }
    else {
        $return = [
            'local_version' => $current_version,
            'latest_version' => $online_version,
            'update_available' => '0',
        ];

        return json_encode($return);
    }
}

/** Add a new row to the options table */
function add_option_if_not_exists ($row, $value) {
	global $dbh;
	$statement = $dbh->prepare("SELECT * FROM " . TABLE_OPTIONS . " WHERE name = :option");
	$statement->bindParam(':option', $row);
	$statement->execute();

	if( $statement->rowCount() == 0 ) {
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
	$chmods = 0;
    $errors = [];

    $emails_folder = ROOT_DIR.'/emails';
	if (!@chmod($emails_folder, 0755)) {
        $errors[] = sprintf(__("A safe chmod value of 755 could not be set for directory: %s", 'cftp_admin'), $emails_folder);
    }

	$emails_files = glob($emails_folder."*", GLOB_NOSORT);

	foreach ($emails_files as $emails_file) {
		if (is_file($emails_file)) {
			if (!@chmod($emails_file, 0755)) {
                $errors[] = sprintf(__("A safe chmod value of 644 could not be set for file: %s", 'cftp_admin'), $emails_file);
            }
		}
	}

    if (!empty($errors)) {
        $return = [];
        $return[] = __("Please correct the following errors to make sure that ProjectSend can send notifications emails.", 'cftp_admin');
        foreach ($errors as $error) {
            $return[] = $error;
        }

        return $errors;
    }
    
    return null;
}

/** Called on r352 */
function chmod_main_files() {
    $chmods = 0;
    $errors = [];
	$system_files = array(
							'sys' => ROOT_DIR.'/includes/app.php',
							'cfg' => ROOT_DIR.'/includes/sys.config.php'
						);
	foreach ($system_files as $sys_file) {
		if (!file_exists($sys_file)) {
            $errors[] = sprintf(__("System file does not exist: %s", 'cftp_admin'), $sys_file);
		}
		else {
			$current_chmod = substr(sprintf('%o', fileperms($sys_file)), -4);
			if ($current_chmod != '0644') {
				if (!@chmod($sys_file, 0644)) {
                    $errors[] = sprintf(__("A safe chmod value of 644 could not be set for file: %s", 'cftp_admin'), $sys_file);
                }
			}
		}
	}

	if (!empty($errors)) {
		return $errors;
    }
    
    return null;
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
	$statement = $dbh->prepare("SELECT id, user FROM " . TABLE_USERS . " WHERE FIND_IN_SET(user, :users)");
	$statement->bindParam(':users', $users);
	$statement->execute();
	$statement->setFetchMode(PDO::FETCH_ASSOC);
	while( $row = $statement->fetch() ) {
		$found_users[$row['user']] = $row['id'];
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

function reset_update_status()
{
	global $dbh;

	$dbh->query("UPDATE " . TABLE_OPTIONS . " SET value ='' WHERE name='version_new_number'");
	$dbh->query("UPDATE " . TABLE_OPTIONS . " SET value ='' WHERE name='version_new_url'");
	$dbh->query("UPDATE " . TABLE_OPTIONS . " SET value ='' WHERE name='version_new_chlog'");
	$dbh->query("UPDATE " . TABLE_OPTIONS . " SET value ='' WHERE name='version_new_security'");
	$dbh->query("UPDATE " . TABLE_OPTIONS . " SET value ='' WHERE name='version_new_features'");
	$dbh->query("UPDATE " . TABLE_OPTIONS . " SET value ='' WHERE name='version_new_important'");
	$dbh->query("UPDATE " . TABLE_OPTIONS . " SET value ='0' WHERE name='version_new_found'");
}