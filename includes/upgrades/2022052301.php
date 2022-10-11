<?php
function upgrade_2022052301()
{
    add_option_if_not_exists('cron_save_log_database', '1');

    if ( !table_exists( TABLE_CRON_LOG ) ) {
        $dbh = get_dbh();
        $query = "
        CREATE TABLE IF NOT EXISTS `".TABLE_CRON_LOG."` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            `sapi` varchar(32) NOT NULL,
            `results` TEXT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ";
        $statement = $dbh->prepare($query);
        $statement->execute();
    }
}
