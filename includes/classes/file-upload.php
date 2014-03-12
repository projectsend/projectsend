<?php
/**
 * Class that handles all the actions and functions that can be applied to
 * files that are being uploaded.
 *
 * @package		ProjectSend
 * @subpackage	Classes
 */

class PSend_Upload_File
{

	var $folder;
	var $assign_to;
	var $uploader;
	var $file;
	var $name;
	var $description;
	var $upload_state;
	/**
	 * the $separator is used to replace invalid characters on a file name.
	 */
	var $separator = '_';
	
	/**
	 * Check if the file extension is among the allowed ones, that are defined on
	 * the options page.
	 */
	function is_filetype_allowed($filename)
	{
		if ( true === CAN_UPLOAD_ANY_FILE_TYPE ) {
			return true;
		}
		else {
			global $options_values;
			$this->safe_filename = $filename;
			$allowed_file_types = str_replace(',','|',$options_values['allowed_file_types']);
			$file_types = "/^\.(".$allowed_file_types."){1}$/i";
			if (preg_match($file_types, strrchr($this->safe_filename, '.'))) {
				return true;
			}
		}
	}
	
	/**
	 * Generate a safe filename that includes only letters, numbers and underscores.
	 * If there are multiple invalid characters in a row, only one replacement character
	 * will be used, to avoid unnecessarily long file names.
	 */
	function safe_rename($name)
	{
		$this->name = $name;
		$this->safe_filename = preg_replace('/[^\w\._]+/', $this->separator, $this->name);
		return $this->safe_filename;
	}
	
	/**
	 * Rename a file using only letters, numbers and underscores.
	 * Used when reading the temp folder to add files to ProjectSend via the "Add from FTP"
	 * feature.
	 *
	 * Files are renamed before being shown on the list.
	 *
	 */
	function safe_rename_on_disc($name,$folder)
	{
		$this->name = $name;
		$this->folder = $folder;
		$this->new_filename = preg_replace('/[^\w\._]+/', $this->separator, $this->name);
		if(rename($this->folder.'/'.$this->name, $this->folder.'/'.$this->new_filename)) {
			return $this->new_filename;
		}
		else {
			return false;
		}
	}
	
	/**
	 * Used to copy a file from the temporary folder (the default location where it's put
	 * after uploading it) to the final folder.
	 * If succesful, the original file is then deleted.
	 */
	function upload_move($arguments)
	{
		$this->uploaded_name = $arguments['uploaded_name'];
		$this->filename = $arguments['filename'];

		//$this->file_final_name = time().'-'.$this->filename;
		$this->file_final_name = $this->filename;
		$this->path = UPLOADED_FILES_FOLDER.'/'.$this->file_final_name;
		if (rename($this->uploaded_name, $this->path)) {
			chmod($this->path, 0644);
			return $this->file_final_name;
		}
		else {
			return false;
		}
	}
	
	/**
	 * Called after correctly moving the file to the final location.
	 */
	function upload_add_to_database($arguments)
	{
		global $database;
		$this->post_file = $arguments['file'];
		$this->name = encode_html($arguments['name']);
		$this->description = encode_html($arguments['description']);
		$this->uploader = $arguments['uploader'];
		$this->uploader_id = $arguments['uploader_id'];
		$this->uploader_type = $arguments['uploader_type'];
		$this->hidden = (!empty($arguments['hidden'])) ? '1' : '0';
		$this->expires = (!empty($arguments['expires'])) ? '1' : '0';
		$this->expiry_date = (!empty($arguments['expiry_date'])) ? date("Y-m-d", strtotime($arguments['expiry_date'])) : date("Y-m-d");
		$this->is_public = (!empty($arguments['public'])) ? '1' : '0';
		$this->public_token	= generateRandomString(32);
		
		if(isset($arguments['add_to_db'])) {
			$result = $database->query("INSERT INTO tbl_files (url, filename, description, uploader, expires, expiry_date, public_allow, public_token)"
										."VALUES ('$this->post_file', '$this->name', '$this->description', '$this->uploader', '$this->expires', '$this->expiry_date', '$this->is_public', '$this->public_token')");
			$this->file_id = mysql_insert_id();
			$this->state['new_file_id'] = $this->file_id;

			/** Record the action log */
			if ($this->uploader_type == 'user') {
				$this->action_type = 5;
			}
			elseif ($this->uploader_type == 'client') {
				$this->action_type = 6;
			}
			$new_log_action = new LogActions();
			$log_action_args = array(
									'action' => $this->action_type,
									'owner_id' => $this->uploader_id,
									'affected_file' => $this->file_id,
									'affected_file_name' => $this->name,
									'affected_account_name' => $this->uploader
								);
			$new_record_action = $new_log_action->log_action_save($log_action_args);
		}
		else {
			$id_sql = $database->query("SELECT id, public_allow, public_token FROM tbl_files WHERE url = '$this->post_file'");
			while($row = mysql_fetch_array($id_sql)) {
				$this->file_id = $row["id"];
				$this->state['new_file_id'] = $this->file_id;
				if (!empty($row["public_token"])) {
					$this->public_token	= $row["public_token"];
				}
				/**
				 * If a client is editing a file, the public settings should
				 * not be reset.
				 */
				if ( CURRENT_USER_LEVEL == 0 ) {
					$this->is_public = $row["public_allow"];
				}
			}
			$result = $database->query("UPDATE tbl_files SET
											filename = '$this->name',
											description = '$this->description',
											expires = '$this->expires',
											expiry_date = '$this->expiry_date',
											public_allow = '$this->is_public',
											public_token = '$this->public_token'
											WHERE id = '$this->file_id'
										");
		}

		if(!empty($result)) {
			$this->state['database'] = true;
		}
		else {
			$this->state['database'] = false;
		}
		
		return $this->state;
	}

	/**
	 * Used to add new assignments and notifications
	 */
	function upload_add_assignment($arguments)
	{
		global $database;
		$this->name = encode_html($arguments['name']);
		$this->uploader_id = $arguments['uploader_id'];
		$this->groups = $arguments['all_groups'];
		$this->users = $arguments['all_users'];

		if (!empty($arguments['assign_to'])) {
			$this->assign_to = $arguments['assign_to'];
			foreach ($this->assign_to as $this->assignment) {
				$this->id_only = substr($this->assignment, 1);
				switch ($this->assignment[0]) {
					case 'c':
						$this->add_to = 'client_id';
						$this->account_name = $this->users[$this->id_only];
						$this->action_number = 25;
						break;
					case 'g':
						$this->add_to = 'group_id';
						$this->account_name = $this->groups[$this->id_only];
						$this->action_number = 26;
						break;
				}
				$this->assignment = substr($this->assignment, 1);
				$assign_file = $database->query("INSERT INTO tbl_files_relations (file_id, $this->add_to, hidden)"
											."VALUES ('$this->file_id', '$this->assignment', '$this->hidden')");
				
				if ($this->uploader_type == 'user') {
					/** Record the action log */
					$new_log_action = new LogActions();
					$log_action_args = array(
											'action' => $this->action_number,
											'owner_id' => $this->uploader_id,
											'affected_file' => $this->file_id,
											'affected_file_name' => $this->name,
											'affected_account' => $this->assignment,
											'affected_account_name' => $this->account_name
										);
					$new_record_action = $new_log_action->log_action_save($log_action_args);
				}
			}
		}
	}

	/**
	 * Used to create the new notifications on the database
	 */
	function upload_add_notifications($arguments)
	{
		global $database;
		$this->uploader_type = $arguments['uploader_type'];
		$this->file_id = $arguments['new_file_id'];

		/** Define type of uploader for the notifications queries. */
		if ($this->uploader_type == 'user') {
			$this->notif_uploader_type = 1;
		}
		elseif ($this->uploader_type == 'client') {
			$this->notif_uploader_type = 0;
		}

		if (!empty($arguments['assign_to'])) {
			$this->assign_to = $arguments['assign_to'];
			$this->distinct_notifications = array();

			foreach ($this->assign_to as $this->assignment) {
				$this->id_only = substr($this->assignment, 1);
				switch ($this->assignment[0]) {
					case 'c':
						$this->add_to = 'client_id';
						break;
					case 'g':
						$this->add_to = 'group_id';
						break;
				}
				/**
				 * Add the notification to the table
				 */
				$this->members_to_notify = array();
				
				if ($this->add_to == 'group_id') {
					$this->get_group_members_sql = "SELECT DISTINCT client_id FROM tbl_members WHERE group_id='$this->id_only'";
					$this->get_group_members = $database->query($this->get_group_members_sql);
					while ($this->row = mysql_fetch_array($this->get_group_members)) {
						$this->members_to_notify[] = $this->row['client_id'];
					}
				}
				else {
					$this->members_to_notify[] = $this->id_only;
				}
				
				if (!empty($this->members_to_notify)) {
					foreach ($this->members_to_notify as $this->add_notify) {
						$this->current_assignment = $this->file_id.'-'.$this->add_notify;
						if (!in_array($this->current_assignment, $this->distinct_notifications)) {
							$this->add_not_query = "INSERT INTO tbl_notifications (file_id, client_id, upload_type, sent_status, times_failed)
												VALUES ('$this->file_id', '$this->add_notify', '$this->notif_uploader_type', '0', '0')";
							$this->add_notification = $database->query($this->add_not_query);
							$this->distinct_notifications[] = $this->current_assignment;
						}
					}
				}
			}
		}

	}

	/**
	 * Used when editing a file
	 */
	function clean_assignments($arguments)
	{
		global $database;
		$this->assign_to = $arguments['assign_to'];
		$this->file_id = $arguments['file_id'];
		$this->file_name = $arguments['file_name'];
		$this->current_clients = $arguments['current_clients'];
		$this->current_groups = $arguments['current_groups'];
		$this->owner_id = $arguments['owner_id'];
		
		$this->assign_to_clients = array();
		$this->assign_to_groups = array();
		$this->delete_from_db_clients = array();
		$this->delete_from_db_groups = array();

		foreach ($this->assign_to as $this->assignment) {
			$this->id_only = substr($this->assignment, 1);
			switch ($this->assignment[0]) {
				case 'c':
					$this->assign_to_clients[] = $this->id_only;
					break;
				case 'g':
					$this->assign_to_groups[] = $this->id_only;
					break;
			}
		}
		
		foreach ($this->current_clients as $this->client) {
			if (!in_array($this->client, $this->assign_to_clients)) {
				$this->delete_from_db_clients[] = $this->client;
			}
		}
		foreach ($this->current_groups as $this->group) {
			if (!in_array($this->group, $this->assign_to_groups)) {
				$this->delete_from_db_groups[] = $this->group;
			}
		}
		
		$this->delete_arguments = array(
										'clients' => $this->delete_from_db_clients,
										'groups' => $this->delete_from_db_groups,
										'owner_id' => $this->owner_id
									);

		$this->delete_assignments($this->delete_arguments);
	}

	/**
	 * Used when editing a file
	 */
	function clean_all_assignments($arguments)
	{
		global $database;
		$this->file_id = $arguments['file_id'];
		$this->file_name = $arguments['file_name'];
		$this->owner_id = $arguments['owner_id'];
		
		$this->delete_from_db_clients = array();
		$this->delete_from_db_groups = array();
		$this->clean_query = "SELECT id, file_id, client_id, group_id FROM tbl_files_relations WHERE file_id = '$this->file_id'";
		$this->clean_sql = $database->query($this->clean_query);
		while ($this->row = mysql_fetch_array($this->clean_sql)) {
			if (!empty($this->row['client_id'])) {
				$this->delete_from_db_clients[] = $this->row['client_id'];
			}
			elseif (!empty($this->row['group_id'])) {
				$this->delete_from_db_groups[] = $this->row['group_id'];
			}
		}
		
		$this->delete_arguments = array(
										'clients' => $this->delete_from_db_clients,
										'groups' => $this->delete_from_db_groups,
										'owner_id' => $this->owner_id
									);

		$this->delete_assignments($this->delete_arguments);
	}


	/**
	 * Receives the data from any of the 2 clear assignments functions
	 */	
	private function delete_assignments($arguments)
	{
		global $database;
		$this->clients = $arguments['clients'];
		$this->groups = $arguments['groups'];
		$this->owner_id = $arguments['owner_id'];

		/**
		 * Get a list of clients names for the log
		 */
		if (!empty($this->clients)) {
			$this->delete_clients = implode(',',array_unique($this->clients));
			$this->clients_names_query = "SELECT id, name FROM tbl_users WHERE id IN ($this->delete_clients)";
			$this->clients_names_sql = $database->query($this->clients_names_query);
			while ($this->crow = mysql_fetch_array($this->clients_names_sql)) {
				$this->clients_names[$this->crow['id']] = $this->crow['name'];
			}

			$this->clean_query = "DELETE FROM tbl_files_relations WHERE file_id = '$this->file_id' AND client_id IN ($this->delete_clients)";
			$this->clean_sql = $database->query($this->clean_query);

			/** Record the action log */
			foreach ($this->clients as $this->deleted_client) {
				$new_log_action = new LogActions();
				$log_action_args = array(
										'action' => 10,
										'owner_id' => $this->owner_id,
										'affected_file' => $this->file_id,
										'affected_file_name' => $this->file_name,
										'affected_account' => $this->deleted_client,
										'affected_account_name' => $this->clients_names[$this->deleted_client]
									);
				$new_record_action = $new_log_action->log_action_save($log_action_args);
			}
		}
		/**
		 * Get a list of groups names for the log
		 */
		if (!empty($this->groups)) {
			$this->delete_groups = implode(',',array_unique($this->groups));
			$this->groups_names_query = "SELECT id, name FROM tbl_groups WHERE id IN ($this->delete_groups)";
			$this->groups_names_sql = $database->query($this->groups_names_query);
			while ($this->grow = mysql_fetch_array($this->groups_names_sql)) {
				$this->groups_names[$this->grow['id']] = $this->grow['name'];
			}

			$this->clean_query = "DELETE FROM tbl_files_relations WHERE file_id = '$this->file_id' AND group_id IN ($this->delete_groups)";
			$this->clean_sql = $database->query($this->clean_query);

			/** Record the action log */
			foreach ($this->groups as $this->deleted_group) {
				$new_log_action = new LogActions();
				$log_action_args = array(
										'action' => 11,
										'owner_id' => $this->owner_id,
										'affected_file' => $this->file_id,
										'affected_file_name' => $this->file_name,
										'affected_account' => $this->deleted_group,
										'affected_account_name' => $this->groups_names[$this->deleted_group]
									);
				$new_record_action = $new_log_action->log_action_save($log_action_args);
			}
		}
	}
}

?>