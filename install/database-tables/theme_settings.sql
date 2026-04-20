-- Theme Settings Table for ProjectSend
-- Stores individual theme configuration options with theme-specific prefixes

CREATE TABLE IF NOT EXISTS tbl_theme_settings (
    id int(11) NOT NULL AUTO_INCREMENT,
    theme_name varchar(100) NOT NULL,
    setting_name varchar(100) NOT NULL,
    setting_value text,
    setting_type varchar(50) DEFAULT 'string',
    created_date timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_date timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_theme_setting (theme_name, setting_name),
    INDEX idx_theme_name (theme_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings for retro90s theme
INSERT INTO tbl_theme_settings (theme_name, setting_name, setting_value, setting_type) VALUES
('retro90s', 'show_entertainment', '1', 'checkbox'),
('retro90s', 'entertainment_items_count', '3', 'number')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);