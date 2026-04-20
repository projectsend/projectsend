<?php
function upgrade_2026032701()
{
    global $dbh;

    // Add TOTP columns to users table
    $columns_to_add = [
        'totp_secret' => "VARCHAR(512) DEFAULT NULL",
        'totp_enabled' => "TINYINT(1) NOT NULL DEFAULT 0",
        'two_factor_method' => "VARCHAR(20) DEFAULT NULL",
    ];

    foreach ($columns_to_add as $column => $definition) {
        try {
            $query = "ALTER TABLE " . TABLE_USERS . " ADD COLUMN `{$column}` {$definition}";
            $statement = $dbh->prepare($query);
            $statement->execute();
        } catch (\Exception $e) {
            // Column may already exist
        }
    }

    // Create backup codes table
    if (!table_exists(TABLE_TOTP_BACKUP_CODES)) {
        $query = "
        CREATE TABLE IF NOT EXISTS `" . TABLE_TOTP_BACKUP_CODES . "` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `code_hash` varchar(255) NOT NULL,
            `used` tinyint(1) NOT NULL DEFAULT 0,
            `used_timestamp` DATETIME DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`user_id`) REFERENCES " . TABLE_USERS . "(`id`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ";
        $statement = $dbh->prepare($query);
        $statement->execute();
    }

    // New global options
    add_option_if_not_exists('two_factor_required', '0');
    add_option_if_not_exists('two_factor_allow_email', '1');
    add_option_if_not_exists('two_factor_allow_totp', '1');

    // Migrate existing setting
    $existing = get_option('authentication_require_email_code');
    if ($existing == '1') {
        save_option('two_factor_required', '1');
    }
}
