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
		$this->can_delete		= false;
		$this->result			= '';
		$this->check_level		= array(9,8,0);

		if (isset($rel_id)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->file_id = $rel_id;
				$this->sql = $this->dbh->prepare("SELECT url, uploader FROM " . TABLE_FILES . " WHERE id = :file_id");
				$this->sql->bindParam(':file_id', $this->file_id, PDO::PARAM_INT);
				$this->sql->execute();
				$this->sql->setFetchMode(PDO::FETCH_ASSOC);
				while( $this->row = $this->sql->fetch() ) {
					if ( CURRENT_USER_LEVEL == '0' ) {
						if ( CLIENTS_CAN_DELETE_OWN_FILES == '1' && $this->row['uploader'] == CURRENT_USER_USERNAME ) {
							$this->can_delete	= true;
						}
					}
					else {
						$this->can_delete	= true;
					}

					$this->file_url = $this->row['url'];
				}

				/** Delete the reference to the file on the database */
				if ( true === $this->can_delete ) {
					$this->sql = $this->dbh->prepare("DELETE FROM " . TABLE_FILES . " WHERE id = :file_id");
					$this->sql->bindParam(':file_id', $this->file_id, PDO::PARAM_INT);
					$this->sql->execute();
					/**
					 * Use the id and uri information to delete the file.
					 *
					 * @see delete_file_from_disk
					 */
					delete_file_from_disk(UPLOADED_FILES_FOLDER . $this->file_url);
					$this->result = true;
				}
				else {
					$this->result = false;
				}
				
				return $this->result;
			}
		}
	}
	function delete_inbox_files($rel_id)
	{
		$this->can_delete		= false;
		$this->result			= '';
		$this->check_level		= array(9,8,0);

		if (isset($rel_id)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->file_id = $rel_id;
				$this->sql = $this->dbh->prepare("SELECT url, uploader FROM " . TABLE_FILES . " WHERE id = :file_id");
				$this->sql->bindParam(':file_id', $this->file_id, PDO::PARAM_INT);
				$this->sql->execute();
				$this->sql->setFetchMode(PDO::FETCH_ASSOC);
				while( $this->row = $this->sql->fetch() ) {
					if ( CURRENT_USER_LEVEL == '0' ) {
						if ( CLIENTS_CAN_DELETE_OWN_FILES == '1' && $this->row['uploader'] == CURRENT_USER_USERNAME ) {
							$this->can_delete	= true;
						}
					}
					else {
						$this->can_delete	= true;
					}

					$this->file_url = $this->row['url'];
				}

				/** Delete the reference to the file on the database */
				if ( true === $this->can_delete ) {
					// $assign = $this->dbh->prepare("SELECT * FROM " . TABLE_FILES_RELATIONS . " WHERE file_id =".$this->file_id);
					// $assign->execute();
					// if($assign->rowCount() > 1 ){
					//
					// 	$this->sql = $this->dbh->prepare("DELETE FROM " . TABLE_FILES_RELATIONS. " WHERE file_id = :file_id AND client_id =".CURRENT_USER_ID);
					// 	$this->sql->bindParam(':file_id', $this->file_id, PDO::PARAM_INT);
					// 	$this->sql->execute();
					//
					// }
					// else {
					// 	$this->sql = $this->dbh->prepare("DELETE FROM " . TABLE_FILES . " WHERE id = :file_id");
					// 	$this->sql->bindParam(':file_id', $this->file_id, PDO::PARAM_INT);
					// 	$this->sql->execute();
					// 	delete_file_from_disk(UPLOADED_FILES_FOLDER . $this->file_url);
					// }
					$this->sql = $this->dbh->prepare("DELETE FROM " . TABLE_FILES_RELATIONS. " WHERE file_id = :file_id AND client_id =".CURRENT_USER_ID);
					$this->sql->bindParam(':file_id', $this->file_id, PDO::PARAM_INT);
					$this->sql->execute();
					$prev_assign = $this->dbh->prepare("UPDATE " . TABLE_FILES . " SET prev_assign ='1' WHERE id = :file_id");
					$prev_assign->bindParam(':file_id', $this->file_id, PDO::PARAM_INT);
					$prev_assign->execute();
					$this->result = true;
				}
				else {
					$this->result = false;
				}

				return $this->result;
			}
		}
	}


	function delete_public_files($rel_id)
	{
		$this->can_delete		= false;
		$this->result			= '';
		$this->check_level		= array(9,8,0);

		if (isset($rel_id)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->file_id = $rel_id;
				$this->sql = $this->dbh->prepare("SELECT url, uploader FROM " . TABLE_FILES . " WHERE id = :file_id");
				$this->sql->bindParam(':file_id', $this->file_id, PDO::PARAM_INT);
				$this->sql->execute();
				$this->sql->setFetchMode(PDO::FETCH_ASSOC);
				while( $this->row = $this->sql->fetch() ) {
						$this->can_delete	= true;
						$this->file_url = $this->row['url'];
				}

				/** Delete the reference to the file on the database */
				if ( true === $this->can_delete ) {
					$this->sql = $this->dbh->prepare("DELETE FROM " . TABLE_FILES . " WHERE id = :file_id");
					$this->sql->bindParam(':file_id', $this->file_id, PDO::PARAM_INT);
					$this->sql->execute();
					/**
					 * Use the id and uri information to delete the file.
					 *
					 * @see delete_file_from_disk
					 */
					delete_file_from_disk(UPLOADED_FILES_FOLDER . $this->file_url);
					$this->result = true;
				}
				else {
					$this->result = false;
				}
				
				return $this->result;
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
	function hide_n_show($file_id,$change_to)
	{
		$this->check_level = array(9,8,7);
		if (isset($file_id)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->sql = $this->dbh->prepare("UPDATE " . TABLE_FILES_RELATIONS . " SET hidden=:hidden WHERE file_id = :file_id");
				$this->sql->bindParam(':hidden', $change_to, PDO::PARAM_INT);
				$this->sql->bindParam(':file_id', $file_id, PDO::PARAM_INT);
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
	function unassign($file_id)
	{
		$this->check_level = array(9,8,7);
		if (isset($file_id)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$check = $this->dbh->prepare("SELECT * FROM " . TABLE_FILES_RELATIONS . " WHERE file_id =".$file_id);
				$check->execute();
				if($check->rowCount() > 1 ){
					$this->sql = $this->dbh->prepare("DELETE FROM " . TABLE_FILES_RELATIONS. " WHERE file_id = :file_id AND client_id =".CURRENT_USER_ID);
					$this->sql->bindParam(':file_id', $file_id, PDO::PARAM_INT);
					$this->sql->execute();
				 }
				else{
					$this->sql = $this->dbh->prepare("DELETE FROM " . TABLE_FILES_RELATIONS . " WHERE file_id = :file_id");
					$this->sql->bindParam(':file_id', $file_id, PDO::PARAM_INT);
					$this->sql->execute();
				}

				$unassign = $this->dbh->prepare("UPDATE " . TABLE_FILES . " SET prev_assign ='1' WHERE id = :file_id");
				$unassign->bindParam(':file_id', $file_id, PDO::PARAM_INT);
				$unassign->execute();

			}
		}
	}

}

?>