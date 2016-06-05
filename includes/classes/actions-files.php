<?php
/**
 * Class that handles all the actions and functions that can be applied to
 * the already uploaded files.
 *
 * @package		ProjectSend
 * @subpackage	Classes
 */

class FilesActions
{

	var $files = array();
	
	/**
	 * This function is used to get all the information of a file on a
	 * single function, to avoid repetition of code when doing other
	 * actions.
	 *
	 * @return array
	 */
	function get_file_data_by_id($file_id)
	{
		global $database;
		/**
		 * Query 1
		 * Get the file name that was generated on upload (row url) and
		 * the client that the file belongs to.
		 */
		$this->sql1 = $database->query('SELECT url,client_user FROM tbl_files WHERE id="' . $file_id .'"');
		$this->file_data = mysql_fetch_assoc($this->sql1);
		$this->file_information = array(
										'url' => $this->file_data['url'],
										'client_user' => $this->file_data['client_user']
									);
		/**
		 * Query 2
		 * Get the id of the the client that the file is assigned to.
		 */
		$this->sql2 = $database->query('SELECT id FROM tbl_clients WHERE client_user="' . $this->file_information['client_user'] .'"');
		$this->client_data = mysql_fetch_row($this->sql2);
		$this->file_information['client_id'] = $this->client_data[0];

		return $this->file_information;
	}

	function delete_files($rel_id)
	{
		global $database;
		$this->check_level = array(9,8,7);
		if (isset($rel_id)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->file_id = $rel_id;
				$this->sql_url = $database->query('SELECT url FROM tbl_files WHERE id="'.$this->file_id.'"');
				while($this->data_file_2 = mysql_fetch_array($this->sql_url)) {
					$this->file_url = $this->data_file_2['url'];
				}
				/** Delete the reference to the file on the database */
				$this->sql = $database->query('DELETE FROM tbl_files WHERE id="' . $this->file_id . '"');
				/**
				 * Use the id and uri information to delete the file.
				 *
				 * @see delete_file
				 */
				delete_file(UPLOADED_FILES_FOLDER . $this->file_url);
				
				return $this->file_url;
			}
		}
	}
	
	function change_files_hide_status($file_id,$change_to)
	{
		global $database;
		$this->check_level = array(9,8,7);
		if (isset($file_id)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->sql = $database->query('UPDATE tbl_files_relations SET hidden='.$change_to.' WHERE id="' . $file_id . '"');
			}
		}
	}

	function hide_for_everyone($file_id)
	{
		global $database;
		$this->check_level = array(9,8,7);
		if (isset($file_id)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->sql = $database->query('UPDATE tbl_files_relations SET hidden="1" WHERE file_id="' . $file_id . '"');
			}
		}
	}

	function unassign_file($file_id)
	{
		global $database;
		$this->check_level = array(9,8,7);
		if (isset($file_id)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->sql = $database->query('DELETE FROM tbl_files_relations WHERE id="' . $file_id . '"');
			}
		}
	}

}

?>
