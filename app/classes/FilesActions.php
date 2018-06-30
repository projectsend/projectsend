<?php
/**
 * Class that handles all the actions and functions that can be applied to
 * the already uploaded files.
 *
 * @package		ProjectSend
 * @subpackage	Classes
 */

namespace ProjectSend;
use PDO;

class FilesActions
{

	var $files = array();

	function __construct() {
		global $dbh;
		$this->dbh = $dbh;
	}

	/**
	 * Standarized way to get a list of files
	 * @todo finish adding the filters
	 * @todo implement accross the system (manage-files.php, templates/common.php, etc)
	 */
	function get_files($arguments)
	{
		$this->file_id		= !empty( $arguments['file_id'] ) ? $arguments['file_id'] : '';
		$this->uploader	= !empty( $arguments['uploader'] ) ? $arguments['uploader'] : '';
		$this->group_ids	= !empty( $arguments['group_ids'] ) ? $arguments['group_ids'] : array();
		$this->group_ids	= is_array( $this->group_ids ) ? $this->group_ids : array( $this->group_ids );
		$this->client_ids	= !empty( $arguments['client_ids'] ) ? $arguments['client_ids'] : array();
		$this->client_ids	= is_array( $this->client_ids ) ? $this->client_ids : array( $this->client_ids );
		$this->group_ids	= !empty( $arguments['group_ids'] ) ? $arguments['group_ids'] : array();
		$this->uploader	    = !empty( $arguments['uploader'] ) ? $arguments['uploader'] : '';
		$this->is_public	= !empty( $arguments['public'] ) ? $arguments['public'] : '';
		$this->expires		= !empty( $arguments['expires'] ) ? $arguments['expires'] : '';
		$this->expired		= !empty( $arguments['expired'] ) ? $arguments['expired'] : '';
		$this->search		= !empty( $arguments['search'] ) ? $arguments['search'] : '';
		$this->categories	= !empty( $arguments['categories'] ) ? $arguments['categories'] : array();
		$this->categories	= is_array( $this->categories ) ? $this->categories : array( $this->categories );
		$this->limit		= !empty( $arguments['limit'] ) ? $arguments['limit'] : '';
		$this->offset		= !empty( $arguments['offset'] ) ? $arguments['offset'] : '';

		$this->files		= array();

		/**
		 * 1- If filtering by group or client, get a list of relations
		 */
		 if ( !empty( $this->group_ids ) || !empty( $this->client_ids ) ) {
			 if ( !empty( $this->group_ids ) ) {
				 $files_filter = array();
				 $files_filter_sql = "SELECT id, file_id, group_id FROM " . TABLE_FILES_RELATIONS . " WHERE group_id=:group_id AND hidden = '0'";
			 }
			 if ( !empty( $this->client_ids ) ) {
				 $files_filter = array();
				 $files_filter_sql = "SELECT id, file_id, client_id FROM " . TABLE_FILES_RELATIONS . " WHERE client_id=:client_id AND hidden = '0'";
			 }
 		}

		$this->state['files'] = array();
		$this->query = "SELECT * FROM " . TABLE_FILES;

		$this->parameters = array();
		if ( !empty( $this->is_public ) ) {
			$this->parameters[] = "public_allow=:public";
		}
		if ( !empty( $this->expires ) ) {
			$this->parameters[] = "expires=:expires";
		}
		if ( !empty( $this->expired ) ) {
			$this->parameters[] = "public=:expired";
		}
		if ( !empty( $this->uploader ) ) {
			$this->parameters[] = "uploader=:uploader";
		}
		if ( !empty( $this->search ) ) {
			$this->parameters[] = "(original_url LIKE :original_url OR filename LIKE :title OR description LIKE :description)";
		}

		/** Add the parameters */
		if ( !empty( $this->parameters ) ) {
			$this->p = 1;
			foreach ( $this->parameters as $this->parameter ) {
				if ( $this->p == 1 ) {
					$this->connector = " WHERE ";
				}
				else {
					$this->connector = " AND ";
				}
				$this->p++;

				$this->query .= $this->connector . $this->parameter;
			}
		}

		//echo $this->query;

		$this->statement = $this->dbh->prepare($this->query);

		if ( !empty( $this->is_public ) ) {
			$this->statement->bindValue(':public', $this->is_public, PDO::PARAM_INT);
		}
		if ( !empty( $this->expires ) ) {
			$this->statement->bindValue(':expires', $this->expires, PDO::PARAM_INT);
		}


		/** Execute the main files query */
		$this->statement->execute();
		$this->statement->setFetchMode(PDO::FETCH_ASSOC);
		while( $this->data = $this->statement->fetch() ) {
			$this->state['files'][$this->data['id']] = array(
										'id'				=> $this->data['id'],
										'title'			=> $this->data['filename'],
										'description'	=> $this->data['description'],
									);
		}

		$this->state['count'] = count( $this->state['files'] );

		return $this->state;

	}

	function delete_files($rel_id)
	{
		$this->can_delete		= false;
		$this->result			= '';
		$this->check_level		= array(9,8,7,0);

		if (isset($rel_id)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->file_id = $rel_id;
				$this->sql = $this->dbh->prepare("SELECT original_url, url, uploader FROM " . TABLE_FILES . " WHERE id = :file_id");
				$this->sql->bindParam(':file_id', $this->file_id, PDO::PARAM_INT);
				$this->sql->execute();
				$this->sql->setFetchMode(PDO::FETCH_ASSOC);
				while( $this->row = $this->sql->fetch() ) {
					if ( CURRENT_USER_LEVEL == '0' ) {
						if ( CLIENTS_CAN_DELETE_OWN_FILES == '1' && $this->row['uploader'] == CURRENT_USER_USERNAME ) {
							$this->can_delete	= true;
						}
					}
					elseif ( CURRENT_USER_LEVEL == '7' ) {
						if ( $this->row['uploader'] == CURRENT_USER_USERNAME ) {
							$this->can_delete	= true;
						}
					}
					else {
						$this->can_delete	= true;
					}

					$this->file_url = $this->row['url'];

					/**
					 * Thumbnails should be deleted too.
					 * Start by making a pattern with the file name, a shorter version of what's
					 * used on make_thumbnail.
					 */
					$this->thumbnails_pattern = 'thumb_' . md5($this->row['url']);
					$this->find_thumbnails = glob( THUMBNAILS_FILES_DIR . '/' . $this->thumbnails_pattern . '*.*' );
					//print_array($this->find_thumbnails);
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
					delete_file_from_disk(UPLOADED_FILES_DIR . DS . $this->file_url);

					/** Delete the thumbnails */
					foreach ( $this->find_thumbnails as $this->thumbnail ) {
						delete_file_from_disk($this->thumbnail);
					}
					$this->result = true;
				}
				else {
					$this->result = false;
				}

				return $this->result;
			}
		}
	}

	function change_files_hide_status($change_to,$file_id,$modify_type,$modify_id)
	{
		$this->check_level = array(9,8,7);
		if (isset($file_id)) {
			/** Do a permissions check */
			if (isset($this->check_level) && in_session_or_cookies($this->check_level)) {
				$this->sql = "UPDATE " . TABLE_FILES_RELATIONS . " SET hidden=:hidden WHERE file_id = :file_id AND " . $modify_type . " = :modify_id";
				$this->statement = $this->dbh->prepare($this->sql);
				$this->statement->bindParam(':hidden', $change_to, PDO::PARAM_INT);
				$this->statement->bindParam(':file_id', $file_id, PDO::PARAM_INT);
            $this->statement->bindParam(':modify_id', $modify_id, PDO::PARAM_INT);
				$this->statement->execute();
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
				$this->sql = "DELETE FROM " . TABLE_FILES_RELATIONS . " WHERE file_id = :file_id AND " . $modify_type . " = :modify_id";
				$this->statement = $this->dbh->prepare($this->sql);
				$this->statement->bindParam(':file_id', $file_id, PDO::PARAM_INT);
            $this->statement->bindParam(':modify_id', $modify_id, PDO::PARAM_INT);
				$this->statement->execute();
			}
		}
	}

}
