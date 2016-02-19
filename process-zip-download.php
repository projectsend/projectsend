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

$files_to_zip = explode(',',substr(mysql_real_escape_string($_GET['file']), 0, -1));

foreach ($files_to_zip as $idx=>$file) {
    $file = UPLOADED_FILES_FOLDER . $file;
    error_log("Downloading $file");
    if(!(realpath($file) && substr(realpath($file),0,strlen(UPLOADED_FILES_FOLDER)) === UPLOADED_FILES_FOLDER)){
        error_log("Removing $file");
       unset($files_to_zip[$idx]);
    }
}


$added_files = 0;

$current_level = get_current_user_level();
$current_username = get_current_user_username();

/**
 * Get the list of different groups the client belongs to.
 */
$sql_groups = $database->query("SELECT DISTINCT group_id FROM tbl_members WHERE client_id='".$global_id."'");
$count_groups = mysql_num_rows($sql_groups);
if ($count_groups > 0) {
	while($row_groups = mysql_fetch_array($sql_groups)) {
		$groups_ids[] = $row_groups["group_id"];
	}
	$found_groups = implode(',',$groups_ids);
}

foreach ($files_to_zip as $file_to_zip) {
	/**
	 * If the file is being generated for a client, make sure
	 * that only files under his account can be added.
	 */
	if ($current_level == 0) {
		$sql_url = $database->query('SELECT id, expires, expiry_date FROM tbl_files WHERE url="' . $file_to_zip .'"');
		$row_url = mysql_fetch_array($sql_url);
		$this_file_id			= $row_url['id'];
		$this_file_expires		= $row_url['expires'];
		$this_file_expiry_date	= $row_url['expiry_date'];

		$this_file_expired		= false;
		if ($this_file_expires == '1' && time() > strtotime($this_file_expiry_date)) {
			$this_file_expired	= true;
		}
		
		if ($this_file_expires == '0' || $this_file_expired == false) {
			$fq = 'SELECT * FROM tbl_files_relations WHERE (client_id="' . $global_id . '" OR group_id IN ("' . $found_groups . '")) AND file_id="' . $this_file_id .'" AND hidden = "0"';
			//echo $fq.'<br />';
			$sql = $database->query($fq);
			$row = mysql_fetch_array($sql);
			/** Add the file */
			$allowed_to_zip[$row['file_id']] = $file_to_zip;

			/** Add the download row */
			$sql_sum = $database->query('INSERT INTO tbl_downloads (user_id , file_id) VALUES ("' . CURRENT_USER_ID .'", "' . $this_file_id .'")');
		}
	}
	else {
		$allowed_to_zip[] = $file_to_zip;
	}
}

$allowed_to_zip = array_unique($allowed_to_zip);

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