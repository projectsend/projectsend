<?php
function upgrade_2022091101()
{
    if ( !table_exists( get_table('custom_assets') ) ) {
        $dbh = get_dbh();
        $query = "
        CREATE TABLE IF NOT EXISTS `".get_table('custom_assets')."` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            `title` varchar(500) NOT NULL,
            `content` TEXT NULL,
            `language` varchar(32) NOT NULL,
            `location` varchar(500) NOT NULL,
            `position` varchar(500) NOT NULL,
            `enabled` int(1) NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ";
        $statement = $dbh->prepare($query);
        $statement->execute();
    }
}
