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

	function __construct() {
		global $dbh;
		$this->dbh = $dbh;
	}

	/**
	 * Convert a string into a url safe address.
	 * Original name: formatURL
	 * John Magnolia / svick on StackOverflow
	 *
	 * @param string $unformatted
	 * @return string
	 * @link http://stackoverflow.com/questions/2668854/sanitizing-strings-to-make-them-url-and-filename-safe
	 */
	function generate_safe_filename($unformatted) {
	
		$got = pathinfo( strtolower( trim( $unformatted ) ) );
		$url = $got['filename'];
		$ext = $got['extension'];
	
		//replace accent characters, forien languages
		$search = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ'); 
		$replace = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o'); 
		$url = str_replace($search, $replace, $url);
	
		//replace common characters
		$search = array('&', '£', '$'); 
		$replace = array('and', 'pounds', 'dollars'); 
		$url= str_replace($search, $replace, $url);
	
		// remove - for spaces and union characters
		$find = array(' ', '&', '\r\n', '\n', '+', ',', '//');
		$url = str_replace($find, '-', $url);
	
		//delete and replace rest of special chars
		$find = array('/[^a-z0-9\-<>_]/', '/[\-]+/', '/<[^>]*>/');
		$replace = array('', '-', '');
		$uri = preg_replace($find, $replace, $url);
	
		return $uri . '.' . $ext;
	}
	
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
		$this->safe_filename = $this->generate_safe_filename( $this->name );
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
	function safe_rename_on_disk($name,$folder)
	{
		$this->name = $name;
		$this->folder = $folder;
		$this->safe_filename = $this->generate_safe_filename( $this->name );
		if(rename($this->folder.'/'.$this->name, $this->folder.'/'.$this->safe_filename)) {
			return $this->safe_filename;
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
		$this->post_file		= $arguments['file'];
		$this->name				= encode_html($arguments['name']);
		$this->description		= encode_html($arguments['description']);
		$this->uploader			= $arguments['uploader'];
		$this->uploader_id		= $arguments['uploader_id'];
		$this->uploader_type	= $arguments['uploader_type'];
		$this->hidden			= (!empty($arguments['hidden'])) ? 1 : 0;
		$this->expires			= (!empty($arguments['expires'])) ? 1 : 0;
		$this->expiry_date		= (!empty($arguments['expiry_date'])) ? date("Y-m-d", strtotime($arguments['expiry_date'])) : date("Y-m-d");
		$this->is_public		= (!empty($arguments['public'])) ? 1 : 0;
		$this->public_token		= generateRandomString(32);
		
		if (isset($arguments['add_to_db'])) {
			$this->statement = $this->dbh->prepare("INSERT INTO " . TABLE_FILES . " (url, filename, description, uploader, expires, expiry_date, public_allow, public_token)"
											."VALUES (:url, :name, :description, :uploader, :expires, :expiry_date, :public, :token)");
			$this->statement->bindParam(':url', $this->post_file);
			$this->statement->bindParam(':name', $this->name);
			$this->statement->bindParam(':description', $this->description);
			$this->statement->bindParam(':uploader', $this->uploader);
			$this->statement->bindParam(':expires', $this->expires, PDO::PARAM_INT);
			$this->statement->bindParam(':expiry_date', $this->expiry_date);
			$this->statement->bindParam(':public', $this->is_public, PDO::PARAM_INT);
			$this->statement->bindParam(':token', $this->public_token);
			$this->statement->execute();

			$this->file_id = $this->dbh->lastInsertId();
			$this->state['new_file_id'] = $this->file_id;

			$this->state['public_token'] = $this->public_token;

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
			$this->statement = $this->dbh->prepare("SELECT id, public_allow, public_token FROM " . TABLE_FILES . " WHERE url = :url");
			$this->statement->bindParam(':url', $this->post_file);
			$this->statement->execute();
			$this->statement->setFetchMode(PDO::FETCH_ASSOC);
			while( $row = $this->statement->fetch() ) {
				$this->file_id = $row["id"];
				$this->state['new_file_id'] = $this->file_id;
				if (!empty($row["public_token"])) {
					$this->public_token				= $row["public_token"];
					$this->state['public_token']	= $row["public_token"];
				}
				/**
				 * If a client is editing a file, the public settings should
				 * not be reset.
				 */
				if ( CURRENT_USER_LEVEL == 0 ) {
					$this->is_public = $row["public_allow"];
				}
			}
			$this->statement = $this->dbh->prepare("UPDATE " . TABLE_FILES . " SET
												filename = :title,
												description = :description,
												expires = :expires,
												expiry_date = :expiry_date,
												public_allow = :public,
												public_token = :token
												WHERE id = :id
											");
			$this->statement->bindParam(':title', $this->name);
			$this->statement->bindParam(':description', $this->description);
			$this->statement->bindParam(':expires', $this->expires, PDO::PARAM_INT);
			$this->statement->bindParam(':expiry_date', $this->expiry_date);
			$this->statement->bindParam(':public', $this->is_public, PDO::PARAM_INT);
			$this->statement->bindParam(':token', $this->public_token);
			$this->statement->bindParam(':id', $this->file_id, PDO::PARAM_INT);
			$this->statement->execute();
		}

		if(!empty($this->statement)) {
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
				$this->statement = $this->dbh->prepare("INSERT INTO " . TABLE_FILES_RELATIONS . " (file_id, $this->add_to, hidden)"
														."VALUES (:file_id, :assignment, :hidden)");
				$this->statement->bindParam(':file_id', $this->file_id, PDO::PARAM_INT);
				$this->statement->bindParam(':assignment', $this->assignment);
				$this->statement->bindParam(':hidden', $this->hidden, PDO::PARAM_INT);
				$this->statement->execute();
				
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

					$this->statement = $this->dbh->prepare("SELECT DISTINCT client_id FROM " . TABLE_MEMBERS . " WHERE group_id = :id");
					$this->statement->bindParam(':id', $this->id_only, PDO::PARAM_INT);
					$this->statement->execute();
					$this->statement->setFetchMode(PDO::FETCH_ASSOC);
					while( $this->row = $this->statement->fetch() ) {
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

							$this->statement = $this->dbh->prepare("INSERT INTO " . TABLE_NOTIFICATIONS . " (file_id, client_id, upload_type, sent_status, times_failed)
																	VALUES (:file_id, :client_id, :type, '0', '0')");
							$this->statement->bindParam(':file_id', $this->file_id, PDO::PARAM_INT);
							$this->statement->bindParam(':client_id', $this->add_notify, PDO::PARAM_INT);
							$this->statement->bindParam(':type', $this->notif_uploader_type);
							$this->statement->execute();

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
		$this->file_id = $arguments['file_id'];
		$this->file_name = $arguments['file_name'];
		$this->owner_id = $arguments['owner_id'];
		
		$this->delete_from_db_clients = array();
		$this->delete_from_db_groups = array();

		$this->statement = $this->dbh->prepare("SELECT id, file_id, client_id, group_id FROM " . TABLE_FILES_RELATIONS . " WHERE file_id = :id");
		$this->statement->bindParam(':id', $this->file_id, PDO::PARAM_INT);
		$this->statement->execute();
		$this->statement->setFetchMode(PDO::FETCH_ASSOC);
		while( $this->row = $this->statement->fetch() ) {
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
		$this->clients = $arguments['clients'];
		$this->groups = $arguments['groups'];
		$this->owner_id = $arguments['owner_id'];

		/**
		 * Get a list of clients names for the log
		 */
		if (!empty($this->clients)) {
			$this->delete_clients = implode(',',array_unique($this->clients));

			$this->statement = $this->dbh->prepare("SELECT id, name FROM " . TABLE_USERS . " WHERE FIND_IN_SET(id, :clients)");
			$this->statement->bindParam(':clients', $this->delete_clients);
			$this->statement->execute();
			$this->statement->setFetchMode(PDO::FETCH_ASSOC);
			while( $this->row = $this->statement->fetch() ) {
				$this->clients_names[$this->row['id']] = $this->row['name'];
			}


			/** Remove existing assignments of this file/clients */
			$this->statement = $this->dbh->prepare("DELETE FROM " . TABLE_FILES_RELATIONS . " WHERE file_id = :file_id AND FIND_IN_SET(client_id, :clients)");
			$this->statement->bindParam(':file_id', $this->file_id, PDO::PARAM_INT);
			$this->statement->bindParam(':clients', $this->delete_clients);
			$this->statement->execute();


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


			$this->statement = $this->dbh->prepare("SELECT id, name FROM " . TABLE_GROUPS . " WHERE FIND_IN_SET(id, :groups)");
			$this->statement->bindParam(':groups', $this->delete_groups);
			$this->statement->execute();
			$this->statement->setFetchMode(PDO::FETCH_ASSOC);
			while( $this->row = $this->statement->fetch() ) {
				$this->groups_names[$this->row['id']] = $this->row['name'];
			}


			/** Remove existing assignments of this file/groups */
			$this->statement = $this->dbh->prepare("DELETE FROM " . TABLE_FILES_RELATIONS . " WHERE file_id = :file_id AND FIND_IN_SET(group_id, :groups)");
			$this->statement->bindParam(':file_id', $this->file_id, PDO::PARAM_INT);
			$this->statement->bindParam(':groups', $this->delete_groups);
			$this->statement->execute();


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

	/**
	 * Used to save the categories relations
	 */
	function upload_save_categories($arguments)
	{
		$this->file_id		= $arguments['file_id'];
		$this->categories	= $arguments['categories'];
		
		if ( !empty( $this->categories ) ) {
			$this->categories_current	= array();
			$this->categories_to_delete	= array();
			
			$this->statement = $this->dbh->prepare("SELECT * FROM " . TABLE_CATEGORIES_RELATIONS . " WHERE file_id = :file_id");
			$this->statement->bindParam(':file_id', $this->file_id, PDO::PARAM_INT);
			$this->statement->execute();
			$this->statement->setFetchMode(PDO::FETCH_ASSOC);
			while( $this->row = $this->statement->fetch() ) {
				$this->categories_current[$this->row['cat_id']] = $this->row['cat_id'];
			}
	
			/**
			 * Add existing -on DB- but not selected on the form to
			 * the delete array. This uses the ID of the record.
			 */
			if ( !empty( $this->categories_current ) ) {
				foreach ( $this->categories_current as $cat ) {
					if ( !in_array( $cat, $this->categories ) ) {
						$this->categories_to_delete[$cat] = $cat;
					}
				}

				$this->categories_to_delete = implode( ',', array_unique($this->categories_to_delete ) );

				$this->statement = $this->dbh->prepare("DELETE FROM " . TABLE_CATEGORIES_RELATIONS . " WHERE file_id = :file_id AND FIND_IN_SET(cat_id, :categories)");
				$this->statement->bindParam(':file_id', $this->file_id, PDO::PARAM_INT);
				$this->statement->bindParam(':categories', $this->categories_to_delete);
				$this->statement->execute();
			}
	
			/**
			 * Compare the ones passed through the form to the
			 * ones that are already on the database.
			 * If it's not in the current array, add the row.
			 */
			foreach ( $this->categories as $cat ) {
				if ( !in_array( $cat, $this->categories_current ) ) {
					$this->statement = $this->dbh->prepare("INSERT INTO " . TABLE_CATEGORIES_RELATIONS . " (file_id, cat_id)"
															."VALUES (:file_id, :cat_id)");
					$this->statement->bindParam(':file_id', $this->file_id, PDO::PARAM_INT);
					$this->statement->bindParam(':cat_id', $cat, PDO::PARAM_INT);
					$this->statement->execute();
				}
			}
		}
		else {
			/** No value came from the form, so delete all existing */
			$this->statement = $this->dbh->prepare("DELETE FROM " . TABLE_CATEGORIES_RELATIONS . " WHERE file_id = :file_id");
			$this->statement->bindParam(':file_id', $this->file_id, PDO::PARAM_INT);
			$this->statement->execute();
		}

	}
}

?>