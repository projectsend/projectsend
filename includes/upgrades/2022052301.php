<?php
function upgrade_2022052301()
{
    add_option_if_not_exists('cron_save_log_database', '1');

    if ( !table_exists( get_table('cron_log') ) ) {
        $dbh = get_dbh();
        $query = "
        CREATE TABLE IF NOT EXISTS `".get_table('cron_log')."` (
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
