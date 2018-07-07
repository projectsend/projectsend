<?php
/**
 * Check if a group id exists on the database.
 * Used on the Edit group page.
 *
 * @return bool
 */
function group_exists_id($id)
{
	global $dbh;
	$statement = $dbh->prepare("SELECT * FROM " . TABLE_GROUPS . " WHERE id=:id");
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
 * Get all the group information knowing only the id
 *
 * @return array
 */
function get_group_by_id($id)
{
	global $dbh;
	$statement = $dbh->prepare("SELECT * FROM " . TABLE_GROUPS . " WHERE id=:id");
	$statement->bindParam(':id', $id, PDO::PARAM_INT);
	$statement->execute();
	$statement->setFetchMode(PDO::FETCH_ASSOC);

	while ( $row = $statement->fetch() ) {
		$information = array(
							'id'			=> html_output($row['id']),
							'created_by'	=> html_output($row['created_by']),
							'created_date'	=> html_output($row['timestamp']),
							'name'			=> html_output($row['name']),
							'description'	=> html_output($row['description']),
							'public'		=> html_output($row['public']),
							'public_token'	=> html_output($row['public_token']),
						);
		if ( !empty( $information ) ) {
			return $information;
		}
		else {
			return false;
		}
	}
}
