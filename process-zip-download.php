<?php
/**
 * Generates the zip file for multi downloads.
 *
 * @package		ProjectSend
 */
$allowed_levels = array(9,8,7,0);
require_once('sys.includes.php');
require_once('header.php');

$zip_file = tempnam("tmp", "zip");
$zip = new ZipArchive();
$zip->open($zip_file, ZipArchive::OVERWRITE);

$files_to_zip = explode( ',', $_GET['ids'] );

foreach ($files_to_zip as $idx => $file) {
    $file = UPLOADED_FILES_FOLDER . $file;
    if ( !( realpath( $file ) && substr( realpath( $file ),0, strlen( UPLOADED_FILES_FOLDER ) ) ) === UPLOADED_FILES_FOLDER ){
       unset( $files_to_zip[$idx] );
    }
}

$added_files = 0;

$current_level = get_current_user_level();
$current_username = get_current_user_username();

/**
 * Get the list of different groups the client belongs to.
 */
$statement = $dbh->prepare("SELECT DISTINCT group_id FROM " . TABLE_MEMBERS . " WHERE client_id = :client_id");
$statement->bindParam(':client_id', $global_id, PDO::PARAM_INT);
$statement->execute();
if ( $statement->rowCount() > 0) {
	$statement->setFetchMode(PDO::FETCH_ASSOC);
	while( $row = $statement->fetch() ) {
		$groups_ids[] = $row["group_id"];
	}
	$found_groups = implode(',', $groups_ids);
}

foreach ($files_to_zip as $file_to_zip) {
	/**
	 * If the file is being generated for a client, make sure
	 * that only files under his account can be added.
	 */
	if ($current_level == 0) {
		$statement = $dbh->prepare("SELECT id, url, expires, expiry_date FROM " . TABLE_FILES . " WHERE id = :file");
		$statement->bindParam(':file', $file_to_zip, PDO::PARAM_INT);
		$statement->execute();
		$statement->setFetchMode(PDO::FETCH_ASSOC);
		$row = $statement->fetch();

		$this_file_id			= $row['id'];
		$this_file_filename		= $row['url'];
		$this_file_expires		= $row['expires'];
		$this_file_expiry_date	= $row['expiry_date'];

		$this_file_expired		= false;
		if ($this_file_expires == '1' && time() > strtotime($this_file_expiry_date)) {
			$this_file_expired	= true;
		}
		
		if ($this_file_expires == '0' || $this_file_expired == false) {
			$statement = $dbh->prepare("SELECT * FROM " . TABLE_FILES_RELATIONS . " WHERE (client_id = :client_id OR FIND_IN_SET(group_id, :groups)) AND file_id = :file_id AND hidden = '0'");
			$statement->bindParam(':client_id', $global_id, PDO::PARAM_INT);
			$statement->bindParam(':groups', $found_groups);
			$statement->bindParam(':file_id', $this_file_id, PDO::PARAM_INT);
			$statement->execute();
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			$row = $statement->fetch();
			
			if ( $row ) {
				/** Add the file */
				$allowed_to_zip[$row['file_id']] = $this_file_filename;
	
				/** Add the download row */
				$statement = $dbh->prepare("INSERT INTO " . TABLE_DOWNLOADS . " (user_id , file_id, remote_ip, remote_host)"
											." VALUES (:user_id, :file_id, :remote_ip, :remote_host)");
				$statement->bindValue(':user_id', CURRENT_USER_ID, PDO::PARAM_INT);
				$statement->bindParam(':file_id', $this_file_id, PDO::PARAM_INT);
				$statement->bindParam(':remote_ip', $_SERVER['REMOTE_ADDR']);
				$statement->bindParam(':remote_host', $_SERVER['REMOTE_HOST']);
				$statement->execute();
			}
		}
	}
	else {
		$allowed_to_zip[] = $this_file_filename;
	}

}

$allowed_to_zip = array_unique($allowed_to_zip);

//echo $zip_file;print_r($allowed_to_zip); die();

/** Start adding the files to the zip */
foreach ($allowed_to_zip as $allowed_file_id => $this_allowed_file) {
	$zip->addFile(UPLOADED_FILES_FOLDER.$this_allowed_file,$this_allowed_file);
	$added_files++;
}

$zip->close();

if ($added_files > 0) {

	

	/** Record the action log */
	$new_log_action = new LogActions();
	$log_action_args = array(
							'action' => 9,
							'owner_id' => $global_id,
							'affected_account_name' => $current_username
						);
	$new_record_action = $new_log_action->log_action_save($log_action_args);

	if (file_exists($zip_file)) {
		$zip_file_name = 'download_files_'.generateRandomString().'.zip';
		header('Content-Type: application/zip');
		header('Content-Length: ' . filesize($zip_file));
		header('Content-Disposition: attachment; filename="'.$zip_file_name.'"');
		ob_clean();
		flush();
		readfile($zip_file);
		unlink($zip_file);
	}
}
?>