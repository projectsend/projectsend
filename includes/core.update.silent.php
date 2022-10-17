<?php
/**
 * This file includes updates that should be done regardless of the
 * user / client status.
 */
global $updates_made;

/** Remove "r" from version */
$current_version = substr(CURRENT_VERSION, 1);

$statement = $dbh->prepare("SELECT value FROM " . get_table('options') . " WHERE name = 'last_update'");
$statement->execute();
if ( $statement->rowCount() > 0 ) {
	$statement->setFetchMode(PDO::FETCH_ASSOC);
	while( $row = $statement->fetch() ) {
		$last_update = $row['value'];
	}
}

if ($last_update < $current_version || !isset($last_update)) {
	/**
	 * r431 updates
	 * A new database table was added.
	 * Password reset support is now supported.
	 */
	if ($last_update < 431) {
		if ( !table_exists( get_table('password_reset') ) ) {
			$query = '
			CREATE TABLE IF NOT EXISTS `' . get_table('password_reset') . '` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `user_id` int(11) DEFAULT NULL,
			  `token` varchar(32) NOT NULL,
			  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
			  `used` int(1) DEFAULT \'0\',
			  FOREIGN KEY (`user_id`) REFERENCES ' . get_table('users') . '(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
			  PRIMARY KEY (`id`)
			) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;
			';
			$dbh->query($query);
			$updates_made++;
		}
	}

	/**
	 * r437 updates
	 * A new database table was added.
	 * Password reset support is now supported.
	 */
	if ($last_update < 520) {
		$q = $dbh->query("ALTER TABLE " . get_table('users') . " MODIFY user VARCHAR(".MAX_USER_CHARS.") NOT NULL");
		$q2 = $dbh->query("ALTER TABLE " . get_table('users') . " MODIFY password VARCHAR(".MAX_PASS_CHARS.") NOT NULL");
		if ($q && $q2) {
			$updates_made++;
		}
    }

    /**
     * r1118 updates
     * A new database table was added to store users meta data
     */
    if ($last_update < 1118) {
        if ( !table_exists( get_table('user_meta') ) ) {
            $query = "
            CREATE TABLE IF NOT EXISTS `".get_table('user_meta')."` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) DEFAULT NULL,
                `name` varchar(255) NOT NULL,
                `value` TEXT NULL,
                `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                FOREIGN KEY (`user_id`) REFERENCES ".get_table('users')."(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
            ";
            $statement = $dbh->prepare($query);
            $statement->execute();

            $updates_made++;
        }
    }

    /**
     * r1270 updates
     * Added meta updated time column and action log details column
     */
    if ($last_update < 1270) {
        $statement = $dbh->prepare("SHOW COLUMNS FROM `" . get_table('user_meta') . "` LIKE 'updated_at'");  
        try {
            $statement->execute();
            if (!$statement->fetchColumn()) {
                $statement = $dbh->query("
                    ALTER TABLE `" . get_table('user_meta') . "` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL
                ");
            }
        } catch(PDOException $e){
            //die($e->getMessage());
        }

        $updates_made++;

        $statement = $dbh->prepare("SHOW COLUMNS FROM `" . get_table('actions_log') . "` LIKE 'details'");  
        try {
            $statement->execute();
            if (!$statement->fetchColumn()) {
                $statement = $dbh->query("ALTER TABLE `" . get_table('actions_log') . "` ADD COLUMN `details` TEXT DEFAULT NULL after `affected_account_name`");
            }
        } catch(PDOException $e){
            //die($e->getMessage());
        }

        $updates_made++;
    }


    /**
     * r1271 updates
     * Added download method option. Set according to XSendFile value
     */
    if ($last_update < 1271) {
        $download_method = 'php';
        if (get_option('xsendfile_enable') == 1) {
            $download_method = 'apache_xsendfile';
        }
        $new_database_values = [
            'download_method' => $download_method,
        ];
        
        foreach($new_database_values as $row => $value) {
            if ( add_option_if_not_exists($row, $value) ) {
                $updates_made++;
            }
        }
    }

    /**
     * r1275 updates
     * Added download method option. Set according to XSendFile value
     */
    if ($last_update < 1275) {
        $new_database_values = [
            'pagination_results_per_page' => 10,
        ];
        
        foreach($new_database_values as $row => $value) {
            if ( add_option_if_not_exists($row, $value) ) {
                $updates_made++;
            }
        }
    }
}