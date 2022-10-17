<?php
function upgrade_2022091501()
{
    if ( !table_exists( get_table('user_limit_upload_to') ) ) {
        $dbh = get_dbh();
        $query = "
        CREATE TABLE IF NOT EXISTS `".get_table('user_limit_upload_to')."` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `client_id` int(11) NOT NULL,
            `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            PRIMARY KEY (`id`),
            FOREIGN KEY (`user_id`) REFERENCES ".get_table('users')."(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            FOREIGN KEY (`client_id`) REFERENCES ".get_table('users')."(`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ";
        $statement = $dbh->prepare($query);
        $statement->execute();
    }
}
