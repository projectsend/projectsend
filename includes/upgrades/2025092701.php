<?php
/**
 * Database upgrade: Create custom fields system
 * This upgrade creates the tables needed for custom user and client fields
 */

function upgrade_2025092701()
{
    global $dbh;

    try {
        // Create custom fields definition table
        $query = "CREATE TABLE IF NOT EXISTS " . TABLE_CUSTOM_FIELDS . " (
            id int(11) NOT NULL AUTO_INCREMENT,
            field_name varchar(100) NOT NULL,
            field_label varchar(255) NOT NULL,
            field_type enum('text','textarea','select','checkbox') NOT NULL DEFAULT 'text',
            field_options text NULL,
            default_value text NULL,
            is_required tinyint(1) NOT NULL DEFAULT 0,
            is_visible_to_client tinyint(1) NOT NULL DEFAULT 1,
            applies_to enum('user','client','both') NOT NULL DEFAULT 'client',
            sort_order int(11) NOT NULL DEFAULT 0,
            active tinyint(1) NOT NULL DEFAULT 1,
            created_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY field_name (field_name),
            KEY applies_to (applies_to),
            KEY active (active),
            KEY sort_order (sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";

        $statement = $dbh->prepare($query);
        $statement->execute();

        // Create custom field values table
        $query = "CREATE TABLE IF NOT EXISTS " . TABLE_CUSTOM_FIELD_VALUES . " (
            id int(11) NOT NULL AUTO_INCREMENT,
            field_id int(11) NOT NULL,
            user_id int(11) NOT NULL,
            field_value text NULL,
            created_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY field_user (field_id, user_id),
            KEY user_id (user_id),
            FOREIGN KEY (field_id) REFERENCES " . TABLE_CUSTOM_FIELDS . " (id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES " . TABLE_USERS . " (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";

        $statement = $dbh->prepare($query);
        $statement->execute();


        error_log('Database upgrade 2025092701: Successfully created custom fields system');

    } catch (Exception $e) {
        error_log('Database upgrade 2025092701 failed: ' . $e->getMessage());
        throw $e;
    }
}