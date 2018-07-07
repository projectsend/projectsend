<?php
/**
 * Check if a user id exists on the database.
 * Used on the Edit user page.
 *
 * @return bool
 */
function user_exists_id($id)
{
	global $dbh;
	$statement = $dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE id=:id");
	$statement->bindParam(':id', $id, PDO::PARAM_INT);
	$statement->execute();
	if ( $statement->rowCount() > 0 ) {
		return true;
	}
	else {
		return false;
	}
}

/**
 * Get all the user information knowing only the id
 *
 * @return array
 */
function get_user_by_id($id)
{
	global $dbh;
	$statement = $dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE id=:id");
	$statement->bindParam(':id', $id, PDO::PARAM_INT);
	$statement->execute();
	$statement->setFetchMode(PDO::FETCH_ASSOC);

	while ( $row = $statement->fetch() ) {
		$information = array(
							'id'			=> html_output($row['id']),
							'username'		=> html_output($row['username']),
							'name'			=> html_output($row['name']),
							'email'			=> html_output($row['email']),
                            'level'			=> html_output($row['level']),
                            'active'		=> html_output($row['active']),
							'max_file_size'	=> html_output($row['max_file_size']),
							'created_date'	=> html_output($row['timestamp']),
						);
		if ( !empty( $information ) ) {
			return $information;
		}
		else {
			return false;
		}
	}
}

/**
 * Get all the user information knowing only the log in username
 *
 * @return array
 * @uses get_user_by_id
 */
function get_user_by_username($user)
{
	global $dbh;
	$statement = $dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE username=:user");
	$statement->execute(
						array(
							':user'	=> $user
						)
					);
	$statement->setFetchMode(PDO::FETCH_ASSOC);

	if ( $statement->rowCount() > 0 ) {
		while ( $row = $statement->fetch() ) {
            $found_id = html_output($row['id']);
            if ( !empty( $found_id ) ) {
                $information = get_user_by_id($found_id);
				return $information;
			}
			else {
				return false;
			}
		}
	}
    else {
        return false;
    }
}