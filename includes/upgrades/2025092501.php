<?php
function upgrade_2025092501()
{
    global $dbh;

    try {
        // Create tbl_integrations table for external storage connections
        $integrations_sql = "CREATE TABLE IF NOT EXISTS " . TABLE_INTEGRATIONS . " (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `type` varchar(50) NOT NULL,
            `name` varchar(100) NOT NULL,
            `credentials_encrypted` text NOT NULL,
            `active` tinyint(1) NOT NULL DEFAULT '1',
            `created_by` varchar(" . MAX_USER_CHARS . ") NOT NULL,
            `created_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            `updated_date` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";

        $integrations_stmt = $dbh->prepare($integrations_sql);
        $integrations_stmt->execute();
        error_log("ProjectSend: Created tbl_integrations table");

        // Add external storage columns to tbl_files
        // Check if storage_type column exists
        $check_storage_type_sql = "SELECT COUNT(*)
                                  FROM INFORMATION_SCHEMA.COLUMNS
                                  WHERE table_name = '" . str_replace('`', '', TABLE_FILES) . "'
                                  AND column_name = 'storage_type'
                                  AND table_schema = DATABASE()";
        $check_stmt = $dbh->prepare($check_storage_type_sql);
        $check_stmt->execute();

        if ($check_stmt->fetchColumn() == 0) {
            $alter_storage_type_sql = "ALTER TABLE " . TABLE_FILES . "
                                     ADD COLUMN `storage_type` enum('local','s3','gcs','azure') NOT NULL DEFAULT 'local'
                                     AFTER `timestamp`";
            $alter_stmt = $dbh->prepare($alter_storage_type_sql);
            $alter_stmt->execute();
            error_log("ProjectSend: Added storage_type column to tbl_files");
        }

        // Check if external_path column exists
        $check_external_path_sql = "SELECT COUNT(*)
                                   FROM INFORMATION_SCHEMA.COLUMNS
                                   WHERE table_name = '" . str_replace('`', '', TABLE_FILES) . "'
                                   AND column_name = 'external_path'
                                   AND table_schema = DATABASE()";
        $check_stmt2 = $dbh->prepare($check_external_path_sql);
        $check_stmt2->execute();

        if ($check_stmt2->fetchColumn() == 0) {
            $alter_external_path_sql = "ALTER TABLE " . TABLE_FILES . "
                                       ADD COLUMN `external_path` text NULL
                                       AFTER `storage_type`";
            $alter_stmt2 = $dbh->prepare($alter_external_path_sql);
            $alter_stmt2->execute();
            error_log("ProjectSend: Added external_path column to tbl_files");
        }

        // Check if bucket_name column exists
        $check_bucket_name_sql = "SELECT COUNT(*)
                                 FROM INFORMATION_SCHEMA.COLUMNS
                                 WHERE table_name = '" . str_replace('`', '', TABLE_FILES) . "'
                                 AND column_name = 'bucket_name'
                                 AND table_schema = DATABASE()";
        $check_stmt3 = $dbh->prepare($check_bucket_name_sql);
        $check_stmt3->execute();

        if ($check_stmt3->fetchColumn() == 0) {
            $alter_bucket_name_sql = "ALTER TABLE " . TABLE_FILES . "
                                     ADD COLUMN `bucket_name` varchar(255) NULL
                                     AFTER `external_path`";
            $alter_stmt3 = $dbh->prepare($alter_bucket_name_sql);
            $alter_stmt3->execute();
            error_log("ProjectSend: Added bucket_name column to tbl_files");
        }

        // Check if integration_id column exists
        $check_integration_id_sql = "SELECT COUNT(*)
                                    FROM INFORMATION_SCHEMA.COLUMNS
                                    WHERE table_name = '" . str_replace('`', '', TABLE_FILES) . "'
                                    AND column_name = 'integration_id'
                                    AND table_schema = DATABASE()";
        $check_stmt4 = $dbh->prepare($check_integration_id_sql);
        $check_stmt4->execute();

        if ($check_stmt4->fetchColumn() == 0) {
            $alter_integration_id_sql = "ALTER TABLE " . TABLE_FILES . "
                                        ADD COLUMN `integration_id` int(11) NULL
                                        AFTER `bucket_name`,
                                        ADD FOREIGN KEY (`integration_id`) REFERENCES " . TABLE_INTEGRATIONS . "(`id`) ON DELETE SET NULL ON UPDATE CASCADE";
            $alter_stmt4 = $dbh->prepare($alter_integration_id_sql);
            $alter_stmt4->execute();
            error_log("ProjectSend: Added integration_id column to tbl_files with foreign key");
        }

    } catch (PDOException $e) {
        error_log("ProjectSend: Could not create external storage schema: " . $e->getMessage());
    }
}