<?php
/**
 * This file includes updates that should be done regardless of the
 * user / client status.
 *
 * @package		ProjectSend
 * @subpackage	Updates
 */
global $updates_made;
global $dbh;

/**
 * r431 updates
 * A new database table was added.
 * Password reset support is now supported.
 */
if (431 > LAST_UPDATE) {
    if ( !table_exists( TABLE_PASSWORD_RESET ) ) {
        $query = '
        CREATE TABLE IF NOT EXISTS `' . TABLE_PASSWORD_RESET . '` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) DEFAULT NULL,
            `token` varchar(32) NOT NULL,
            `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            `used` int(1) DEFAULT \'0\',
            FOREIGN KEY (`user_id`) REFERENCES ' . TABLE_USERS . '(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
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
if (520 > LAST_UPDATE) {
    $q = $dbh->query("ALTER TABLE " . TABLE_USERS . " MODIFY user VARCHAR(".MAX_USER_CHARS.") NOT NULL");
    $q2 = $dbh->query("ALTER TABLE " . TABLE_USERS . " MODIFY password VARCHAR(".MAX_PASS_CHARS.") NOT NULL");
    if ($q && $q2) {
        $updates_made++;
    }
}

/**
 * Pre 1.0.0 updates
 */
if (1098 > LAST_UPDATE) {
    $statement = $dbh->query("ALTER TABLE " . TABLE_USERS . " CHANGE `user` `username` VARCHAR(60) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL");
    $updates_made++;
}

/*
if (201807052 > LAST_UPDATE) {
    $statement = $dbh->query("ALTER TABLE " . TABLE_FILES . " CHANGE `filename` `title` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL");
    $updates_made++;
}
*/