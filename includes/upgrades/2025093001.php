<?php
/**
 * Database upgrade: Add encryption support to files table
 *
 * Adds columns to store encryption metadata for files:
 * - encrypted: Flag to indicate if file is encrypted
 * - encryption_key_encrypted: Encrypted file key (encrypted with master key)
 * - encryption_iv: Initialization vector for file key encryption
 * - encryption_algorithm: Algorithm used (default: aes-256-gcm)
 * - encryption_file_iv: Initialization vector used for file content encryption
 */

function upgrade_2025093001()
{
    global $dbh;

    try {
        // Add encryption columns to tbl_files
        $columns_to_add = [
            [
                'name' => 'encrypted',
                'sql' => 'ALTER TABLE ' . TABLE_FILES . ' ADD COLUMN encrypted TINYINT(1) DEFAULT 0 AFTER public_token'
            ],
            [
                'name' => 'encryption_key_encrypted',
                'sql' => 'ALTER TABLE ' . TABLE_FILES . ' ADD COLUMN encryption_key_encrypted TEXT NULL AFTER encrypted'
            ],
            [
                'name' => 'encryption_iv',
                'sql' => 'ALTER TABLE ' . TABLE_FILES . ' ADD COLUMN encryption_iv VARCHAR(64) NULL AFTER encryption_key_encrypted'
            ],
            [
                'name' => 'encryption_algorithm',
                'sql' => 'ALTER TABLE ' . TABLE_FILES . ' ADD COLUMN encryption_algorithm VARCHAR(20) DEFAULT \'aes-256-gcm\' AFTER encryption_iv'
            ],
            [
                'name' => 'encryption_file_iv',
                'sql' => 'ALTER TABLE ' . TABLE_FILES . ' ADD COLUMN encryption_file_iv VARCHAR(64) NULL AFTER encryption_algorithm'
            ]
        ];

        foreach ($columns_to_add as $column) {
            // Check if column already exists
            $check_query = "SHOW COLUMNS FROM " . TABLE_FILES . " LIKE '" . $column['name'] . "'";
            $check_stmt = $dbh->query($check_query);

            if ($check_stmt->rowCount() === 0) {
                // Column doesn't exist, add it
                $dbh->exec($column['sql']);
            }
        }

        // Add encryption configuration options
        add_option_if_not_exists('files_encryption_enabled', 'false');
        add_option_if_not_exists('files_encryption_required', 'false');
        add_option_if_not_exists('files_encryption_max_file_size', '0'); // 0 = no limit

        // Add index on encrypted column for faster queries
        $index_check = "SHOW INDEX FROM " . TABLE_FILES . " WHERE Key_name = 'idx_encrypted'";
        $index_stmt = $dbh->query($index_check);

        if ($index_stmt->rowCount() === 0) {
            $index_query = "ALTER TABLE " . TABLE_FILES . " ADD INDEX idx_encrypted (encrypted)";
            $dbh->exec($index_query);
        }

        return true;

    } catch (\PDOException $e) {
        error_log('Upgrade 2025093001 failed: ' . $e->getMessage());
        return false;
    }
}
