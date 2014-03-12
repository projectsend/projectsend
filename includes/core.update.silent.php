<?php
/**
 * This file includes updates that should be done regardless of the
 * user / client status.
 *
 * @package		ProjectSend
 * @subpackage	Updates
 */
	/** Remove "r" from version */
	$current_version = substr(CURRENT_VERSION, 1);

	$version_query = "SELECT value FROM tbl_options WHERE name = 'last_update'";
	$version_sql = $database->query($version_query);

	if(mysql_num_rows($version_sql)) {
		while($vres = mysql_fetch_array($version_sql)) {
			$last_update = $vres['value'];
		}
	}
	
	if ($last_update < $current_version || !isset($last_update)) {

		/**
		 * r431 updates
		 * A new database table was added.
		 * Password reset support is now supported.
		 */
		if ($last_update < 520) {
			$q = $database->query("SELECT id FROM tbl_password_reset");
			if (!$q) {
				$q1 = '
				CREATE TABLE IF NOT EXISTS `tbl_password_reset` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `user_id` int(11) DEFAULT NULL,
				  `token` varchar(32) NOT NULL,
				  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
				  `used` int(1) DEFAULT \'0\',
				  FOREIGN KEY (`user_id`) REFERENCES tbl_users(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
				  PRIMARY KEY (`id`)
				) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;
				';
				$database->query($q1);
				$updates_made++;
			}
		}

		/**
		 * r437 updates
		 * A new database table was added.
		 * Password reset support is now supported.
		 */
		if ($last_update < 520) {
			$q = $database->query("ALTER TABLE tbl_users MODIFY user VARCHAR(".MAX_USER_CHARS.") NOT NULL");
			$q2 = $database->query("ALTER TABLE tbl_users MODIFY password VARCHAR(".MAX_PASS_CHARS.") NOT NULL");
			if ($q && $q2) {
				$updates_made++;
			}
		}
	}
?>