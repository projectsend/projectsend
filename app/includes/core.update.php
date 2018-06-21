<?php
/**
 * This file is called on header.php and checks the database to see
 * if it up to date with the current software version.
 *
 * In case you are updating from an old one, the new values, columns
 * and rows will be created, and a message will appear under the menu
 * one time only.
 *
 * @package		ProjectSend
 * @subpackage	Updates
 */

$allowed_update = array(9,8,7);
if (in_session_or_cookies($allowed_update)) {
	/**
	 * Versions prior to 1.0 used the number of the current commit, with a preceding "r"
	 * as Google Code used to do.
	 * If the currently installed version is indeed named like this, then the extracted
	 * number from the version is divided by 10000 so the value will be 0.verion, which
	 * will force the updating process.
	 */
	if ( CURRENT_VERSION[0] == 'r') {
		$current_version = ( substr(CURRENT_VERSION, 1) / 10000 );
		define('IS_OLD_VERSION_NAMING', true);
	}
	else {
		$current_version = CURRENT_VERSION;
		define('IS_OLD_VERSION_NAMING', false);
	}

	$updates_made = 0;
	$updates_errors = 0;
	$updates_error_messages = array();

	/**
	 * Check for updates only if the option exists.
	 */
	if (defined('VERSION_LAST_CHECK')) {
		/**
		 * Compare the date for the last checked with
		 * today's. Checks are done only once per day.
		 */
		 $today = date('d-m-Y');
		 $today_timestamp = strtotime($today);
		 if (VERSION_LAST_CHECK != $today) {
			if (VERSION_NEW_FOUND == '0') {
				/**
				 * Compare against the online value.
				 */
				$feed = simplexml_load_file(UPDATES_FEED_URI);
				$v = 0;
				$max_items = 1;
				foreach ($feed->channel->item as $item) {
					while ($v < $max_items) {
						$namespaces = $item->getNameSpaces(true);
						$release = $item->children($namespaces['release']);
						$diff = $item->children($namespaces['diff']);
						$online_version = substr($release->version, 1);

						 if ($online_version > $current_version) {
							/**
							 * The values are set here since they didn't
							 * come from the database.
							 */
							define('VERSION_NEW_NUMBER',$online_version);
							define('VERSION_NEW_URL',$item->link);
							define('VERSION_NEW_CHLOG',$release->changelog);
							define('VERSION_NEW_SECURITY',$diff->security);
							define('VERSION_NEW_FEATURES',$diff->features);
							define('VERSION_NEW_IMPORTANT',$diff->important);
							/**
							 * Save the information from the new release
							 * to the database.
							 */
							$statement = $dbh->prepare("UPDATE " . TABLE_OPTIONS . " SET value = :version WHERE name='version_new_number'");		$statement->bindParam(':version', $release->version); $statement->execute();
							$statement = $dbh->prepare("UPDATE " . TABLE_OPTIONS . " SET value = :link WHERE name='version_new_url'");				$statement->bindParam(':link', $item->link); $statement->execute();
							$statement = $dbh->prepare("UPDATE " . TABLE_OPTIONS . " SET value = :changelog WHERE name='version_new_chlog'");		$statement->bindParam(':changelog', $release->changelog); $statement->execute();
							$statement = $dbh->prepare("UPDATE " . TABLE_OPTIONS . " SET value = :security WHERE name='version_new_security'");		$statement->bindParam(':security', $diff->security); $statement->execute();
							$statement = $dbh->prepare("UPDATE " . TABLE_OPTIONS . " SET value = :features WHERE name='version_new_features'");		$statement->bindParam(':features', $diff->features); $statement->execute();
							$statement = $dbh->prepare("UPDATE " . TABLE_OPTIONS . " SET value = :important WHERE name='version_new_important'");	$statement->bindParam(':important', $diff->important); $statement->execute();
							$statement = $dbh->prepare("UPDATE " . TABLE_OPTIONS . " SET value ='1' WHERE name='version_new_found'");
						 }
						 else {
							 reset_update_status();
						 }

						/**
						 * Change the date and versions values on the
						 * database so it's not checked again today.
						 */
						$statement = $dbh->prepare("UPDATE " . TABLE_OPTIONS . " SET value = :today WHERE name='version_last_check'");
						$statement->bindParam(':today', $today);
						$statement->execute();

						/** Stop the foreach loop */
						$v++;
					}
				}
			 }
		 }
	}

	/**
	 * r264 updates
	 * Save the value of the last update on the database, to prevent
	 * running all this queries everytime a page is loaded.
	 * Done on top for convenience.
	 */
	$statement = $dbh->prepare("SELECT value FROM " . TABLE_OPTIONS . " WHERE name = 'last_update'");
	$statement->execute();
	if ( $statement->rowCount() == 0 ) {
		$dbh->query( "INSERT INTO " . TABLE_OPTIONS . " (name, value) VALUES ('last_update', '264')" );
		$updates_made++;
	}
	else {
		$statement->setFetchMode(PDO::FETCH_ASSOC);
		while ( $row = $statement->fetch() ) {
			$last_update = $row['value'];
		}
	}

	/**
	 * Convert old rxxxx number to a float smaller than 1 based
	 * on it's actual version number
	 */
	function convert_old_version_number($v) {
		$v = ( str_pad($v, 4, "0", STR_PAD_LEFT) ) / 10000;
		return $v;
	}

	/**
	 * Use the old routine first
	 */
	if ( IS_OLD_VERSION_NAMING == true ) {
		include_once INCLUDES_DIR . '/core.update.legacy.php';
	}
}
