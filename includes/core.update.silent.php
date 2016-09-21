<?php

/**
 * This file includes updates that should be done regardless of the
 * user / client status.
 *
 * @package		ProjectSend
 * @subpackage	Updates
 */

// TODO : limit global usage
global $updates_made;

$last_update = false;

/** Remove "r" from version */
$current_version = substr(CURRENT_VERSION, 1);

$query = "
	SELECT
		value
	FROM
		`" . TABLE_OPTIONS . "`
	WHERE
		name = 'last_update';
";

// Why a prepared query ?
$statement = $dbh->prepare($query);
$statement->execute();

if ($statement->rowCount() === 1) {

	$statement->setFetchMode(PDO::FETCH_ASSOC);
	
	if ($row = $statement->fetch()) {
	
		$last_update = $row['value'];
	
	}

}

// cleanup
unset($query);
unset($statement);


/**
 * Check if $last_update is correct
 */
if ($last_update !== false) {

	// Test version
	if ($last_update < $current_version || !isset($last_update)) {
	
		/**
		 * r431 updates
		 * A new database table was added.
		 * Password reset support is now supported.
		 */
		if ($last_update < 431) {
		
			if (!tableExists(TABLE_PASSWORD_RESET)) {
			
				$query = "
				CREATE TABLE IF NOT EXISTS `" . TABLE_PASSWORD_RESET . "` (
					`id` int(11) NOT NULL AUTO_INCREMENT,
					`user_id` int(11) DEFAULT NULL,
					`token` varchar(32) NOT NULL,
					`timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
					`used` int(1) DEFAULT '0',
					FOREIGN KEY (`user_id`) REFERENCES " . TABLE_USERS . "(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
					PRIMARY KEY (`id`)
				) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;
				";
				
				$result = $dbh->query($query);
				
				if ($result) {
				
					$updates_made ++;
				
				}
				
				// cleanup
				unset($result);
				unset($query);
			
			}
		
		}
		
		/**
		 * r437 updates
		 * A new database table was added.
		 * Password reset support is now supported.
		 */
		if ($last_update < 520) {
		
			$query = "
				ALTER TABLE `" . TABLE_USERS . "`
				MODIFY user VARCHAR(" . MAX_USER_CHARS . ") NOT NULL;
			";
			
			$query2 = "
				ALTER TABLE `" . TABLE_USERS . "`
				MODIFY password VARCHAR(" . MAX_PASS_CHARS . ") NOT NULL;
			";
			
			$result = $dbh->query($query);
			$result2 = $dbh->query($query2);
			
			// need more conditions and error reporting
			if ($result && $result2) {
			
				$updates_made ++;
			
			}
			
			// cleanup
			unset($result);
			unset($result2);
			unset($query);
			unset($query2);
		
		}
	
	}

}


?>