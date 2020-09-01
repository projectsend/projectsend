<?php
/**
 * Define the common functions used on the installer and updates.
 *
 * @package		ProjectSend
 * @subpackage	Functions
 */

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

/** Called on r346 */
function update_chmod_timthumb()
{
	global $updates_made;
	global $updates_errors;
	global $updates_error_messages;

	$chmods = 0;
	$timthumb_folder = ROOT_DIR.'/includes/timthumb/';
	$timthumb_file = ROOT_DIR.'/includes/timthumb/timthumb.php';
	$cache_folder = ROOT_DIR.'/includes/timthumb/cache';
	$index_file = ROOT_DIR.'/includes/timthumb/cache/index.html';
	$touch_file = ROOT_DIR.'/includes/timthumb/cache/timthumb_cacheLastCleanTime.touch';
	if (@chmod($timthumb_folder, 0711)) { $chmods++; }
	if (@chmod($timthumb_file, 0700)) { $chmods++; }
	if (@chmod($cache_folder, 0755)) { $chmods++; }
	if (@chmod($index_file, 0666)) { $chmods++; }
	if (@chmod($touch_file, 0666)) { $chmods++; }

	if ($chmods > 0) {
		$updates_made++;
	}
	
	/** This message is mandatory */
	$updates_errors++;
	if ($updates_errors > 0) {
		$updates_error_messages[] = __("If images thumbnails aren't showing on your client's files lists (even your company logo there and on the branding page) please chmod the includes/timthumb/cache folder to 777 and then do the same with the 'index.html' and 'timthumb_cacheLastCleanTime.touch' files inside that folder. Then try lowering each file to 644 and see if everything is still working.", 'cftp_admin');
	}
}

/** Called on r348 */
function update_chmod_emails()
{
	global $updates_made;
	global $updates_errors;
	global $updates_error_messages;

	$chmods = 0;
	$emails_folder = ROOT_DIR.'/emails';
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
function chmod_main_files() {
	global $updates_made;
	global $updates_errors;
	global $updates_error_messages;

	$chmods = 0;
	$system_files = array(
							'sys' => ROOT_DIR.'/sys.vars.php',
							'cfg' => ROOT_DIR.'/includes/sys.config.php'
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
		$updates_error_messages[] = __("A safe chmod value couldn't be set for one or more system files. Please make sure that at least includes/sys.config.php has a chmod of 644 for security reasons.", 'cftp_admin');
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