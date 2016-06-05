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

	function __construct() {
		global $dbh;
		$this->dbh = $dbh;
	}

	function delete_files($rel_id)
	{
		$this->check_level = array(9,8);
		if (isset($rel_id)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->file_id = $rel_id;
				$this->sql = $this->dbh->prepare("SELECT url FROM " . TABLE_FILES . " WHERE id = :file_id");
				$this->sql->bindParam(':file_id', $this->file_id, PDO::PARAM_INT);
				$this->sql->execute();
				$this->sql->setFetchMode(PDO::FETCH_ASSOC);
				while( $this->row = $this->sql->fetch() ) {
					$this->file_url = $this->row['url'];
				}

				/** Delete the reference to the file on the database */
				$this->sql = $this->dbh->prepare("DELETE FROM " . TABLE_FILES . " WHERE id = :file_id");
				$this->sql->bindParam(':file_id', $this->file_id, PDO::PARAM_INT);
				$this->sql->execute();
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
	
	function change_files_hide_status($file_id,$change_to,$modify_type,$modify_id)
	{
		$this->check_level = array(9,8,7);
		if (isset($file_id)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->sql = $this->dbh->prepare("UPDATE " . TABLE_FILES_RELATIONS . " SET hidden=:hidden WHERE file_id = :file_id AND $modify_type = :modify_id");
				$this->sql->bindParam(':hidden', $change_to, PDO::PARAM_INT);
				$this->sql->bindParam(':file_id', $file_id, PDO::PARAM_INT);
				$this->sql->bindParam(':modify_id', $modify_id, PDO::PARAM_INT);
				$this->sql->execute();
			}
		}
	}

	function hide_for_everyone($file_id)
	{
		$this->check_level = array(9,8,7);
		if (isset($file_id)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->sql = $this->dbh->prepare("UPDATE " . TABLE_FILES_RELATIONS . " SET hidden='1' WHERE file_id = :file_id");
				$this->sql->bindParam(':file_id', $file_id, PDO::PARAM_INT);
				$this->sql->execute();
			}
		}
	}

	function unassign_file($file_id,$modify_type,$modify_id)
	{
		$this->check_level = array(9,8,7);
		if (isset($file_id)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->sql = $this->dbh->prepare("DELETE FROM " . TABLE_FILES_RELATIONS . " WHERE file_id = :file_id AND $modify_type = :modify_id");
				$this->sql->bindParam(':file_id', $file_id, PDO::PARAM_INT);
				$this->sql->bindParam(':modify_id', $modify_id, PDO::PARAM_INT);
				$this->sql->execute();
			}
		}
	}

}

?>