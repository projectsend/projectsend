<?php
/**
 * Current installation options functions
 *
 * @package ProjectSend
 */

/**
 * Get a single option from the database
 *
 * @param string option
 * @return string
 */
function get_option( $option )
{
    global $dbh;

    if ( !empty( $option ) ) {
        $statement = $dbh->prepare("SELECT value FROM " . TABLE_OPTIONS . " WHERE name = :name");
        $statement->bindParam(':name', $option);
        $statement->execute();
        if ( $statement->rowCount() > 0 ) {
            $row = $statement->fetch();
            return $row['value'];
        }
        else {
            return false;
        }
    }
    else {
        return false;
    }
}


/**
 * Simple file upload. Used on normal file fields, eg: logo on branding page
 */
function option_file_upload( $file, $validate_ext = '', $option = '', $action = '' )
{
	global $dbh;
	$return = array();
	$continue = true;

	/** Validate file extensions */
	if ( !empty( $validate_ext ) ) {
		switch ( $validate_ext ) {
			case 'image':
				$validate_types = "/^\.(jpg|jpeg|gif|png){1}$/i";
				break;
			default:
				break;
		}
	}

	if ( is_uploaded_file( $file['tmp_name'] ) ) {

		$this_upload = new ProjectSend\FilesUpload();
		$safe_filename = $this_upload->safe_rename( $file['name'] );
		/**
		 * Check the file type for allowed extensions.
		 */
		if ( !empty( $validate_types) && !preg_match( $validate_types, strrchr( $safe_filename, '.' ) ) ) {
			$continue = false;
		}

		if ( $continue ) {
			/**
			 * Move the file to the destination defined on sys.vars.php. If ok, add the
			 * new file name to the database.
			 */
			if ( move_uploaded_file( $file['tmp_name'], LOGO_FOLDER . $safe_filename ) ) {
				if ( !empty( $option ) ) {
					$query = "UPDATE " . TABLE_OPTIONS . " SET value=:value WHERE name='" . $option . "'";
					$sql = $dbh->prepare( $query );
					$sql->execute(
								array(
									':value'	=> $safe_filename
								)
							);
				}

				$return['status'] = '1';

				/** Record the action log */
				if ( !empty( $action ) ) {
					$new_log_action = new ProjectSend\LogActions();
					$log_action_args = array(
											'action' => $action,
											'owner_id' => CURRENT_USER_ID
										);
					$new_record_action = $new_log_action->log_action_save($log_action_args);
				}
			}
			else {
				$return['status'] = '2';
			}
		}
		else {
			$return['status'] = '3';
		}
	}
	else {
		$return['status'] = '4';
	}

	return $return;
}