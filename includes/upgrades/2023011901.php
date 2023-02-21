<?php

function upgrade_2023011901()
{
    if ( !table_exists( TABLE_CUSTOM_DOWNLOADS ) ) {
        global $dbh;
        $query = "
        CREATE TABLE IF NOT EXISTS `".TABLE_CUSTOM_DOWNLOADS."` (
            `link` varchar(255) NOT NULL,
            `client_id` int(11) DEFAULT NULL,
            `file_id` int(11) DEFAULT NULL,
            `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            `expiry_date` TIMESTAMP NULL DEFAULT NULL,
            `visit_count` int(16) NOT NULL DEFAULT '0',
            PRIMARY KEY (`link`),
            FOREIGN KEY (`client_id`) REFERENCES ".TABLE_USERS."(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`file_id`) REFERENCES ".TABLE_FILES."(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ";
        $statement = $dbh->prepare($query);
        $statement->execute();
        add_option_if_not_exists('files_default_public', '0');
        add_option_if_not_exists('custom_download_uri', '');
    }
}
