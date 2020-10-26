<?php
/**
 * Class that handles all the actions and functions that can be applied to
 * the already uploaded files.
 *
 * @package		ProjectSend
 * @subpackage	Classes
 */
namespace ProjectSend\Classes;

use \PDO;

class FilesActions
{
    private $dbh;
    private $logger;

	var $files = array();

    public function __construct(PDO $dbh = null)
    {
        if (empty($dbh)) {
            global $dbh;
        }

        $this->dbh = $dbh;
        $this->logger = new \ProjectSend\Classes\ActionsLog;
	}

	function deleteFiles($rel_id)
	{
		$this->can_delete		= false;
		$this->result			= '';
		$this->check_level		= array(9,8,7,0);

		if (isset($rel_id)) {
			/** Do a permissions check */
			if (isset($this->check_level) && current_role_in($this->check_level)) {
				$this->file_id = $rel_id;
				$this->sql = $this->dbh->prepare("SELECT url, original_url, uploader, filename FROM " . TABLE_FILES . " WHERE id = :file_id");
				$this->sql->bindParam(':file_id', $this->file_id, PDO::PARAM_INT);
				$this->sql->execute();
				$this->sql->setFetchMode(PDO::FETCH_ASSOC);
				while( $this->row = $this->sql->fetch() ) {
					if ( CURRENT_USER_LEVEL == '0' ) {
						if ( get_option('clients_can_delete_own_files') == '1' && $this->row['uploader'] == CURRENT_USER_USERNAME ) {
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
                    $this->title = $this->row['filename'];
                    
                    /**
 					 * Thumbnails should be deleted too.
 					 * Start by making a pattern with the file name, a shorter version of what's
 					 * used on make_thumbnail.
 					 */
 					$this->thumbnails_pattern = 'thumb_' . md5($this->row['url']);
 					$this->find_thumbnails = glob( THUMBNAILS_FILES_DIR . DS . $this->thumbnails_pattern . '*.*' );
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

                    /** Record the action log */
                    $record = $this->logger->addEntry([
                        'action' => 12,
                        'owner_id' => CURRENT_USER_ID,
                        'affected_file' => $this->file_id,
                        'affected_file_name' => $this->title
                    ]);

					return true;
				}

				return false;
			}
		}
	}

	function changeHiddenStatus($change_to, $file_id, $modify_type, $modify_id)
	{
        $this->check_level = array(9,8,7);
        
        if (empty($file_id)) {
            return false;
        }

        switch ($change_to) {
            case 1:
                $log_action_number = 21;
                break;
            case 0:
                $log_action_number = 22;
                break;
            default:
                throw new \Exception('Invalid status code');
                return false;
        }

        switch ($modify_type) {
            case 'client_id':
                $client = get_client_by_id($modify_id);
                $log_account_name = $client['name'];
                break;
            case 'group_id':
                $group = get_group_by_id($modify_id);
                $log_account_name = $group['name'];
                break;
            default:
                throw new \Exception('Invalid modify type');
                return false;
        }

        /** Do a permissions check */
        if (isset($this->check_level) && current_role_in($this->check_level)) {
            $this->sql = "UPDATE " . TABLE_FILES_RELATIONS . " SET hidden=:hidden WHERE file_id = :file_id AND " . $modify_type . " = :modify_id";
            $this->statement = $this->dbh->prepare($this->sql);
            $this->statement->bindParam(':hidden', $change_to, PDO::PARAM_INT);
            $this->statement->bindParam(':file_id', $file_id, PDO::PARAM_INT);
            $this->statement->bindParam(':modify_id', $modify_id, PDO::PARAM_INT);
            $this->statement->execute();

            $file = get_file_by_id($file_id);

            /** Record the action log */
            $record = $this->logger->addEntry([
                'action' => $log_action_number,
                'owner_id' => CURRENT_USER_ID,
                'affected_file' => $file_id,
                'affected_file_name' => $file['title'],
                'affected_account_name' => $log_account_name,
            ]);

            return true;
        }
        
        return false;
	}

	function hideForEveryone($file_id)
	{
        $this->check_level = array(9,8,7);
        
        if (empty($file_id)) {
            return false;
        }

        /** Do a permissions check */
        if (isset($this->check_level) && current_role_in($this->check_level)) {
            $this->sql = $this->dbh->prepare("UPDATE " . TABLE_FILES_RELATIONS . " SET hidden='1' WHERE file_id = :file_id");
            $this->sql->bindParam(':file_id', $file_id, PDO::PARAM_INT);
            $this->sql->execute();

            $file = get_file_by_id( $file_id );

            /** Record the action log */
            $record = $this->logger->addEntry([
                'action' => 40,
                'owner_id' => CURRENT_USER_ID,
                'affected_file' => $file_id,
                'affected_file_name' => $file['title']
            ]);

            return true;
        }

        return false;
	}

	function unassignFile($file_id,$modify_type,$modify_id)
	{
        $this->check_level = array(9,8,7);
        
        if (empty($file_id)) {
            return false;
        }

        switch ($modify_type) {
            case 'client_id':
                $log_action_number = 10;
                $client = get_client_by_id($modify_id);
                $log_account_name = $client['name'];
                break;
            case 'group_id':
                $log_action_number = 11;
                $group = get_group_by_id($modify_id);
                $log_account_name = $group['name'];
                break;
            default:
                throw new \Exception('Invalid modify type');
                return false;
        }

        /** Do a permissions check */
        if (isset($this->check_level) && current_role_in($this->check_level)) {
            $this->sql = "DELETE FROM " . TABLE_FILES_RELATIONS . " WHERE file_id = :file_id AND " . $modify_type . " = :modify_id";
            $this->statement = $this->dbh->prepare($this->sql);
            $this->statement->bindParam(':file_id', $file_id, PDO::PARAM_INT);
            $this->statement->bindParam(':modify_id', $modify_id, PDO::PARAM_INT);
            $this->statement->execute();

            $file = get_file_by_id( $file_id );

            /** Record the action log */
            $record = $this->logger->addEntry([
                'action' => $log_action_number,
                'owner_id' => CURRENT_USER_ID,
                'affected_file' => $file_id,
                'affected_file_name' => $file['title'],
                'affected_account_name' => $log_account_name,
            ]);

            return true;
        }

        return false;
	}
}
