<?php
function upgrade_2022092301()
{
    if ( !table_exists( TABLE_AUTHENTICATION_CODES ) ) {
        $dbh = get_dbh();
        $query = "
        CREATE TABLE IF NOT EXISTS `".TABLE_AUTHENTICATION_CODES."` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `token` varchar(32) NOT NULL,
            `code` int(6) NOT NULL,
            `used` int(1) NOT NULL default '0',
            `used_timestamp` TIMESTAMP NULL,
            `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            PRIMARY KEY (`id`),
            FOREIGN KEY (`user_id`) REFERENCES ".TABLE_USERS."(`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ";
        $statement = $dbh->prepare($query);
        $statement->execute();
    }

    add_option_if_not_exists('authentication_require_email_code', '0');
}
