<?php

function upgrade_2025091602()
{
    global $dbh;
    
    // Create remember_tokens table for secure "remember me" functionality
    $query = "CREATE TABLE IF NOT EXISTS " . TABLE_REMEMBER_TOKENS . " (
        id int(11) NOT NULL AUTO_INCREMENT,
        user_id int(11) NOT NULL,
        token_hash varchar(64) NOT NULL,
        expires_at timestamp NOT NULL,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        last_used timestamp NULL,
        user_agent text,
        PRIMARY KEY (id),
        INDEX idx_token_hash (token_hash),
        INDEX idx_user_id (user_id),
        INDEX idx_expires_at (expires_at),
        FOREIGN KEY (user_id) REFERENCES " . TABLE_USERS . "(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";
    
    $statement = $dbh->prepare($query);
    $statement->execute();
    
    // Add configuration options for remember me functionality
    add_option_if_not_exists('remember_me_enabled', '1');
    add_option_if_not_exists('remember_me_duration_days', '30');
    add_option_if_not_exists('remember_me_max_tokens_per_user', '5');
}