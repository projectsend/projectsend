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
$get_groups		= new MembersActions();
$get_arguments	= array(
								'client_id'	=> CURRENT_USER_ID,
								'return'	=> 'list',
							);
$found_groups	= $get_groups->client_get_groups($get_arguments); 

foreach ($files_to_zip as $file_to_zip) {
	/**
	 * If the file is being generated for a client, make sure
	 * that only files under his account can be added.
	 */
	$statement = $dbh->prepare("SELECT id, url, original_url, expires, expiry_date FROM " . TABLE_FILES . " WHERE id = :file");
	$statement->bindParam(':file', $file_to_zip, PDO::PARAM_INT);
	$statement->execute();
	$statement->setFetchMode(PDO::FETCH_ASSOC);
	$row = $statement->fetch();

	$this_file_id			= $row['id'];
	$this_file_on_disk		= $row['url'];
	$this_file_save_as		= (!empty( $row['original_url'] ) ) ? $row['original_url'] : $row['url'];
	$this_file_expires		= $row['expires'];
	$this_file_expiry_date	= $row['expiry_date'];

	$this_file_expired		= false;
	if ($this_file_expires == '1' && time() > strtotime($this_file_expiry_date)) {
		$this_file_expired	= true;
	}
		
	if ($current_level == 0) {
		if ($this_file_expires == '0' || $this_file_expired == false) {
			$statement = $dbh->prepare("SELECT * FROM " . TABLE_FILES_RELATIONS . " WHERE (client_id = :client_id OR FIND_IN_SET(group_id, :groups)) AND file_id = :file_id AND hidden = '0'");
			$statement->bindValue(':client_id', CURRENT_USER_ID, PDO::PARAM_INT);
			$statement->bindParam(':groups', $found_groups);
			$statement->bindParam(':file_id', $this_file_id, PDO::PARAM_INT);
			$statement->execute();
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			$row = $statement->fetch();
			
			if ( $row ) {
				/** Add the file */
				$allowed_to_zip[$row['file_id']] = array(
														'on_disk'	=> $this_file_on_disk,
														'save_as'	=> $this_file_save_as
													);
			}
		}
	}
	else {
		$allowed_to_zip[] = array(
								'on_disk'	=> $this_file_on_disk,
								'save_as'	=> $this_file_save_as
							);
	}


	/** Add the download row */
	$statement = $dbh->prepare("INSERT INTO " . TABLE_DOWNLOADS . " (user_id , file_id, remote_ip, remote_host)"
								." VALUES (:user_id, :file_id, :remote_ip, :remote_host)");
	$statement->bindValue(':user_id', CURRENT_USER_ID, PDO::PARAM_INT);
	$statement->bindParam(':file_id', $this_file_id, PDO::PARAM_INT);
	$statement->bindParam(':remote_ip', $_SERVER['REMOTE_ADDR']);
	$statement->bindParam(':remote_host', $_SERVER['REMOTE_HOST']);
	$statement->execute();
}

//echo $zip_file;print_r($allowed_to_zip); die();

/** Start adding the files to the zip */
foreach ($allowed_to_zip as $allowed_file_id => $allowed_file_info) {
	$zip->addFile(UPLOADED_FILES_FOLDER.$allowed_file_info['on_disk'],$allowed_file_info['save_as']);
	$added_files++;
}

$zip->close();

if ($added_files > 0) {
	/** Record the action log */
	$new_log_action = new LogActions();
	$log_action_args = array(
							'action' => 9,
							'owner_id' => CURRENT_USER_ID,
							'affected_account_name' => $current_username
						);
	$new_record_action = $new_log_action->log_action_save($log_action_args);

	if (file_exists($zip_file)) {
		setCookie("download_started", 1, time() + 20, '/', "", false, false);
		$zip_file_name = 'projectsend_'.generateRandomString().'.zip';
		session_write_close(); 
		while (ob_get_level()) ob_end_clean();
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="'.$zip_file_name.'"');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Content-Length: ' . get_real_size($zip_file));
		header('Pragma: public');
		header('Cache-Control: private',false);
		header('Connection: close');
		$context = stream_context_create();
		$file = fopen($zip_file, 'rb', false, $context);
		while ( !feof( $file ) ) {
		//usleep(1000000); //Reduce download speed
			echo stream_get_contents($file, 2014);
		}
		fclose( $file );
		unlink($zip_file);
	}
}