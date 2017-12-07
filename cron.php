<?php


//exit;
	require_once('sys.includes.php');
	echo timestamp_check_for_orphan_file_deletion();
	$work_folder = UPLOADED_FILES_FOLDER;
	/** Count clients to show an error message, or the form */
	$statement		= $dbh->query("SELECT id FROM " . TABLE_USERS . " WHERE level = '0'");
	$count_clients	= $statement->rowCount();
	$statement		= $dbh->query("SELECT id FROM " . TABLE_GROUPS);
	$count_groups	= $statement->rowCount();

	if ( ( !$count_clients or $count_clients < 1 ) && ( !$count_groups or $count_groups < 1 ) ) {
		message_no_clients();
	}

	/**
	 * Make a list of existing files on the database.
	 * When a file doesn't correspond to a record, it can
	 * be safely renamed.
	 */
	$sql = $dbh->query("SELECT url, id, public_allow FROM " . TABLE_FILES );
	$db_files = array();
	$sql->setFetchMode(PDO::FETCH_ASSOC);
	while ( $row = $sql->fetch() ) {
		$db_files[$row["url"]] = $row["id"];
		if ($row['public_allow'] == 1) {$db_files_public[$row["url"]] = $row["id"];}
	}

	/** Make an array of already assigned files */
	$sql = $dbh->query("SELECT DISTINCT file_id FROM " . TABLE_FILES_RELATIONS . " WHERE client_id IS NOT NULL OR group_id IS NOT NULL OR folder_id IS NOT NULL");
	$assigned = array();
	$sql->setFetchMode(PDO::FETCH_ASSOC);
	while ( $row = $sql->fetch() ) {
		$assigned[] = $row["file_id"];
	}
	
	/** We consider public file as assigned file */
	foreach ($db_files_public as $file_id){
		$assigned[] = $file_id;
	}

	/** Read the temp folder and list every allowed file */
	if ($handle = opendir($work_folder)) {
		while (false !== ($filename = readdir($handle))) {
			$filename_path = $work_folder.'/'.$filename;
			if(!is_dir($filename_path)) {
				if ($filename != "." && $filename != "..") {
					/** Check types of files that are not on the database */							
					if (!array_key_exists($filename,$db_files)) {
						$file_object = new PSend_Upload_File();
						$new_filename = $file_object->safe_rename_on_disk($filename,$work_folder);
						/** Check if the filetype is allowed */
						if ($file_object->is_filetype_allowed($new_filename)) {
							/** Add it to the array of available files */
							$new_filename_path = $work_folder.'/'.$new_filename;
							//$files_to_add[$new_filename] = $new_filename_path;
							$files_to_add[] = array(
													'path'		=> $new_filename_path,
													'name'		=> $new_filename,
													'reason'	=> 'not_on_db',
												);
						}
					}
				}
			}
		}
		closedir($handle);
	}
	
//	echo "<pre>";print_r($files_to_add);echo "</pre>";
//exit;
	if(!empty($files_to_add)){
		foreach($files_to_add as $files){
			echo $files['path']."<br>";
			unlink($files['path']);
			exit;
		}
	}
