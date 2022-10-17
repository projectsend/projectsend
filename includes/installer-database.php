<?php
/**
 * Contains the queries that will be used to create the database structure
 * when installing the system.
 */
function get_install_base_queries($params = []) {
    $current_version = substr(CURRENT_VERSION, 1);
    $database_version = INITIAL_DATABASE_VERSION;
    $now = date('d-m-Y');
    $expiry_default = date('Y') + 1 . "-01-01 00:00:00";
    $timezone = date_default_timezone_get();

    $install_queries = array(
        '0' =>  array(
            'table'	=> get_table('users'),
            'query'	=> 'CREATE TABLE IF NOT EXISTS `'.get_table('users').'` (
                            `id` int(11) NOT NULL AUTO_INCREMENT,
                            `user` varchar('.MAX_USER_CHARS.') NOT NULL,
                            `password` varchar('.MAX_PASS_CHARS.') NOT NULL,
                            `name` text NOT NULL,
                            `email` varchar(60) NOT NULL,
                            `level` tinyint(1) NOT NULL DEFAULT \'0\',
                            `address` text COLLATE utf8_general_ci NULL,
                            `phone` varchar(32) COLLATE utf8_general_ci NULL,
                            `notify` tinyint(1) NOT NULL DEFAULT \'0\',
                            `contact` text COLLATE utf8_general_ci NULL,
                            `created_by` varchar('.MAX_USER_CHARS.') NULL,
                            `active` tinyint(1) NOT NULL DEFAULT \'1\',
                            `account_requested` tinyint(1) NOT NULL DEFAULT \'0\',
                            `account_denied` tinyint(1) NOT NULL DEFAULT \'0\',
                            `max_file_size` int(20)  NOT NULL DEFAULT \'0\',
                            `can_upload_public` int(20)  NOT NULL DEFAULT \'0\',
                            `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                            PRIMARY KEY (`id`)
                        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
                        ',
        ),

        '1' => array(
                    'table'	=> get_table('files'),
                    'query'	=> 'CREATE TABLE IF NOT EXISTS `'.get_table('files').'` (
                                    `id` int(11) NOT NULL AUTO_INCREMENT,
                                    `user_id` int(11) DEFAULT NULL,
                                    `url` text NOT NULL,
                                    `original_url` text NOT NULL,
                                    `filename` text NOT NULL,
                                    `description` text NULL,
                                    `uploader` varchar('.MAX_USER_CHARS.') NOT NULL,
                                    `expires` INT(1) NOT NULL default \'0\',
                                    `expiry_date` TIMESTAMP NOT NULL DEFAULT "' . $expiry_default . '",
                                    `public_allow` INT(1) NOT NULL default \'0\',
                                    `public_token` varchar(32) NULL,
                                    `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                                    PRIMARY KEY (`id`),
                                    FOREIGN KEY (`user_id`) REFERENCES '.get_table('users').'(`id`) ON DELETE CASCADE ON UPDATE CASCADE
                                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
                                ',
        ),

        '2' =>  array(
                    'table'	=> get_table('options'),
                    'query'	=> 'CREATE TABLE IF NOT EXISTS `'.get_table('options').'` (
                                    `id` int(10) NOT NULL AUTO_INCREMENT,
                                    `name` varchar(50) COLLATE utf8_general_ci NOT NULL,
                                    `value` text COLLATE utf8_general_ci NULL,
                                    PRIMARY KEY (`id`)
                                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
                                ',
        ),

        '3' =>  array(
                    'table'	=> get_table('groups'),
                    'query'	=> 'CREATE TABLE IF NOT EXISTS `'.get_table('groups').'` (
                                    `id` int(11) NOT NULL AUTO_INCREMENT,
                                    `name` varchar(32) NOT NULL,
                                    `description` text NULL,
                                    `public` tinyint(1) NOT NULL DEFAULT \'0\',
                                    `public_token` varchar(32) NULL,
                                    `created_by` varchar(32) NOT NULL,
                                    `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                                    PRIMARY KEY (`id`)
                                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
                                ',
        ),

        '4' =>  array(
                    'table'	=> get_table('members'),
                    'query'	=> 'CREATE TABLE IF NOT EXISTS `'.get_table('members').'` (
                                    `id` int(11) NOT NULL AUTO_INCREMENT,
                                    `added_by` varchar(32) DEFAULT NULL,
                                    `client_id` int(11) NOT NULL,
                                    `group_id` int(11) NOT NULL,
                                    `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                                    PRIMARY KEY (`id`),
                                    FOREIGN KEY (`client_id`) REFERENCES '.get_table('users').'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                    FOREIGN KEY (`group_id`) REFERENCES '.get_table('groups').'(`id`) ON DELETE CASCADE ON UPDATE CASCADE
                                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
                                ',
        ),

        '5' =>  array(
                    'table'	=> get_table('members_requests'),
                    'query'	=> 'CREATE TABLE IF NOT EXISTS `'.get_table('members_requests').'` (
                                    `id` int(11) NOT NULL AUTO_INCREMENT,
                                    `requested_by` varchar(32) NOT NULL,
                                    `client_id` int(11) NOT NULL,
                                    `group_id` int(11) NOT NULL,
                                    `denied` int(1) NOT NULL DEFAULT \'0\',
                                    `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                                    PRIMARY KEY (`id`),
                                    FOREIGN KEY (`client_id`) REFERENCES '.get_table('users').'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                    FOREIGN KEY (`group_id`) REFERENCES '.get_table('groups').'(`id`) ON DELETE CASCADE ON UPDATE CASCADE
                                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
                                ',
        ),

        '6' =>  array(
                    'table'	=> get_table('folders'),
                    'query'	=> 'CREATE TABLE IF NOT EXISTS `'.get_table('folders').'` (
                                    `id` int(11) NOT NULL AUTO_INCREMENT,
                                    `parent` int(11) DEFAULT NULL,
                                    `name` varchar(32) NOT NULL,
                                    `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                                    `client_id` int(11) DEFAULT NULL,
                                    `group_id` int(11) DEFAULT NULL,
                                    FOREIGN KEY (`parent`) REFERENCES '.get_table('folders').'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                    FOREIGN KEY (`client_id`) REFERENCES '.get_table('users').'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                    FOREIGN KEY (`group_id`) REFERENCES '.get_table('groups').'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                    PRIMARY KEY (`id`)
                                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
                                ',
        ),

        '7' =>  array(
                    'table'	=> get_table('files_relations'),
                    'query'	=> 'CREATE TABLE IF NOT EXISTS `'.get_table('files_relations').'` (
                                    `id` int(11) NOT NULL AUTO_INCREMENT,
                                    `file_id` int(11) NOT NULL,
                                    `client_id` int(11) DEFAULT NULL,
                                    `group_id` int(11) DEFAULT NULL,
                                    `folder_id` int(11) DEFAULT NULL,
                                    `hidden` int(1) NOT NULL,
                                    `download_count` int(16) NOT NULL DEFAULT \'0\',
                                    `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                                    FOREIGN KEY (`file_id`) REFERENCES '.get_table('files').'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                    FOREIGN KEY (`client_id`) REFERENCES '.get_table('users').'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                    FOREIGN KEY (`group_id`) REFERENCES '.get_table('groups').'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                    FOREIGN KEY (`folder_id`) REFERENCES '.get_table('folders').'(`id`) ON UPDATE CASCADE,
                                    PRIMARY KEY (`id`)
                                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
                                ',
        ),

        '8' =>  array(
                    'table'	=> get_table('actions_log'),
                    'query'	=> 'CREATE TABLE IF NOT EXISTS `'.get_table('actions_log').'` (
                                    `id` int(11) NOT NULL AUTO_INCREMENT,
                                    `action` int(2) NOT NULL,
                                    `owner_id` int(11) NOT NULL,
                                    `owner_user` text DEFAULT NULL,
                                    `affected_file` int(11) DEFAULT NULL,
                                    `affected_account` int(11) DEFAULT NULL,
                                    `affected_file_name` text DEFAULT NULL,
                                    `affected_account_name` text DEFAULT NULL,
                                    `details` text DEFAULT NULL,
                                    `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                                    PRIMARY KEY (`id`)
                                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
                                ',
        ),

        '9' =>  array(
                    'table'	=> get_table('notifications'),
                    'query'	=> 'CREATE TABLE IF NOT EXISTS `'.get_table('notifications').'` (
                                    `id` int(11) NOT NULL AUTO_INCREMENT,
                                    `file_id` int(11) NOT NULL,
                                    `client_id` int(11) NOT NULL,
                                    `upload_type` int(11) NOT NULL,
                                    `sent_status` int(2) NOT NULL,
                                    `times_failed` int(11) NOT NULL,
                                    `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                                    FOREIGN KEY (`file_id`) REFERENCES '.get_table('files').'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                    FOREIGN KEY (`client_id`) REFERENCES '.get_table('users').'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                    PRIMARY KEY (`id`)
                                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
                                ',
        ),

        '10' =>  array(
                    'table'	=> get_table('password_reset'),
                    'query'	=> 'CREATE TABLE IF NOT EXISTS `'.get_table('password_reset').'` (
                                    `id` int(11) NOT NULL AUTO_INCREMENT,
                                    `user_id` int(11) DEFAULT NULL,
                                    `token` varchar(32) NOT NULL,
                                    `used` int(1) DEFAULT \'0\',
                                    `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                                    FOREIGN KEY (`user_id`) REFERENCES '.get_table('users').'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                    PRIMARY KEY (`id`)
                                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
                                ',
        ),

        '11' =>  array(
                    'table'	=> get_table('downloads'),
                    'query'	=> 'CREATE TABLE IF NOT EXISTS `'.get_table('downloads').'` (
                                    `id` int(11) NOT NULL AUTO_INCREMENT,
                                    `user_id` int(11) DEFAULT NULL,
                                    `file_id` int(11) NOT NULL,
                                    `remote_ip` varchar(45) DEFAULT NULL,
                                    `remote_host` text NULL,
                                    `anonymous` tinyint(1) DEFAULT \'0\',
                                    `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                                    FOREIGN KEY (`user_id`) REFERENCES '.get_table('users').'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                    FOREIGN KEY (`file_id`) REFERENCES '.get_table('files').'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                    PRIMARY KEY (`id`)
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
                                ',
        ),

        '12' =>  array(
                    'table'	=> '',
                    'query'	=> "INSERT INTO ".get_table('options')." (name, value) VALUES
                                ('base_uri', :base_uri),
                                ('max_thumbnail_width', '100'),
                                ('max_thumbnail_height', '100'),
                                ('thumbnails_folder', '../../assets/img/custom/thumbs/'),
                                ('thumbnail_default_quality', '90'),
                                ('max_logo_width', '300'),
                                ('max_logo_height', '300'),
                                ('this_install_title', :title),
                                ('selected_clients_template', 'default'),
                                ('logo_thumbnails_folder', '/assets/img/custom/thumbs'),
                                ('timezone', '".$timezone."'),
                                ('timeformat', 'd/m/Y'),
                                ('allowed_file_types', '7z,ace,ai,avi,bin,bmp,bz2,cdr,csv,doc,docm,docx,eps,fla,flv,gif,gz,gzip,htm,html,iso,jpeg,jpg,mp3,mp4,mpg,odt,oog,ppt,pptx,pptm,pps,ppsx,pdf,png,psd,rar,rtf,tar,tif,tiff,tgz,txt,wav,xls,xlsm,xlsx,xz,zip'),
                                ('logo_filename', ''),
                                ('admin_email_address', :email),
                                ('clients_can_register', '0'),
                                ('last_update', :version),
                                ('database_version', :db_version),
                                ('mail_system_use', 'mail'),
                                ('mail_smtp_host', ''),
                                ('mail_smtp_port', ''),
                                ('mail_smtp_user', ''),
                                ('mail_smtp_pass', ''),
                                ('mail_from_name', :from),
                                ('thumbnails_use_absolute', '0'),
                                ('mail_copy_user_upload', ''),
                                ('mail_copy_client_upload', ''),
                                ('mail_copy_main_user', ''),
                                ('mail_copy_addresses', ''),
                                ('version_last_check', :now),
                                ('version_new_found', '0'),
                                ('version_new_number', ''),
                                ('version_new_url', ''),
                                ('version_new_chlog', ''),
                                ('version_new_security', ''),
                                ('version_new_features', ''),
                                ('version_new_important', ''),
                                ('clients_auto_approve', '0'),
                                ('clients_auto_group', '0'),
                                ('clients_can_upload', '1'),
                                ('clients_can_set_expiration_date', '0'),
                                ('email_new_file_by_user_customize', '0'),
                                ('email_new_file_by_client_customize', '0'),
                                ('email_new_client_by_user_customize', '0'),
                                ('email_new_client_by_self_customize', '0'),
                                ('email_new_user_customize', '0'),
                                ('email_new_file_by_user_text', ''),
                                ('email_new_file_by_client_text', ''),
                                ('email_new_client_by_user_text', ''),
                                ('email_new_client_by_self_text', ''),
                                ('email_new_user_text', ''),
                                ('email_header_footer_customize', '0'),
                                ('email_header_text', ''),
                                ('email_footer_text', ''),
                                ('email_pass_reset_customize', '0'),
                                ('email_pass_reset_text', ''),
                                ('expired_files_hide', '1'),
                                ('notifications_max_tries', '2'),
                                ('notifications_max_days', '15'),
                                ('file_types_limit_to', 'all'),
                                ('pass_require_upper', '0'),
                                ('pass_require_lower', '0'),
                                ('pass_require_number', '0'),
                                ('pass_require_special', '0'),
                                ('mail_smtp_auth', 'none'),
                                ('use_browser_lang', '0'),
                                ('clients_can_delete_own_files', '0'),
                                ('google_client_id', ''),
                                ('google_client_secret', ''),
                                ('google_signin_enabled', '0'),
                                ('recaptcha_enabled', '0'),
                                ('recaptcha_site_key', ''),
                                ('recaptcha_secret_key', ''),
                                ('clients_can_select_group', 'none'),
                                ('files_descriptions_use_ckeditor', '0'),
                                ('enable_landing_for_all_files', '0'),
                                ('footer_custom_enable', '0'),
                                ('footer_custom_content', ''),
                                ('email_new_file_by_user_subject_customize', '0'),
                                ('email_new_file_by_client_subject_customize', '0'),
                                ('email_new_client_by_user_subject_customize', '0'),
                                ('email_new_client_by_self_subject_customize', '0'),
                                ('email_new_user_subject_customize', '0'),
                                ('email_pass_reset_subject_customize', '0'),
                                ('email_new_file_by_user_subject', ''),
                                ('email_new_file_by_client_subject', ''),
                                ('email_new_client_by_user_subject', ''),
                                ('email_new_client_by_self_subject', ''),
                                ('email_new_user_subject', ''),
                                ('email_pass_reset_subject', ''),
                                ('privacy_noindex_site', '0'),
                                ('email_account_approve_subject_customize', '0'),
                                ('email_account_deny_subject_customize', '0'),
                                ('email_account_approve_customize', '0'),
                                ('email_account_deny_customize', '0'),
                                ('email_account_approve_subject', ''),
                                ('email_account_deny_subject', ''),
                                ('email_account_approve_text', ''),
                                ('email_account_deny_text', ''),
                                ('email_client_edited_subject_customize', '0'),
                                ('email_client_edited_customize', '0'),
                                ('email_client_edited_subject', ''),
                                ('email_client_edited_text', ''),
                                ('public_listing_page_enable', '0'),
                                ('public_listing_logged_only', '0'),
                                ('public_listing_show_all_files', '0'),
                                ('public_listing_use_download_link', '0'),
                                ('svg_show_as_thumbnail', '0'),
                                ('pagination_results_per_page', '10'),
                                ('login_ip_whitelist', ''),
                                ('login_ip_blacklist', '')
                                ",
                    'params' => array(
                                        ':base_uri'	=> $params['base_uri'],
                                        ':title'	=> $params['install_title'],
                                        ':email'	=> $params['admin']['email'],
                                        ':version'	=> $current_version,
                                        ':db_version' => $database_version,
                                        ':from'		=> $params['install_title'],
                                        ':now'		=> $now,
                                ),
        ),

        '13' =>  array(
                        'table'	=> '',
                        'query'	=> "INSERT INTO ".get_table('users')." (id, user, password, name, email, level, active) VALUES
                                    (1, :username, :password, :name, :email, 9, 1)",
                        'params' => array(
                                        ':username'	=> $params['admin']['username'],
                                        ':password'	=> $params['admin']['pass'],
                                        ':name'		=> $params['admin']['name'],
                                        ':email'	=> $params['admin']['email'],
                        ),
        ),

        '14' =>  array(
                    'table'	=> get_table('categories'),
                    'query'	=> 'CREATE TABLE IF NOT EXISTS `'.get_table('categories').'` (
                                    `id` int(11) NOT NULL AUTO_INCREMENT,
                                    `name` varchar(32) NOT NULL,
                                    `parent` int(11) DEFAULT NULL,
                                    `description` text NULL,
                                    `created_by` varchar('.MAX_USER_CHARS.') NULL,
                                    `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                                    FOREIGN KEY (`parent`) REFERENCES '.get_table('categories').'(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                                    PRIMARY KEY (`id`)
                                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
                                ',
        ),

        '15' =>  array(
                    'table'	=> get_table('categories_relations'),
                    'query'	=> 'CREATE TABLE IF NOT EXISTS `'.get_table('categories_relations').'` (
                                    `id` int(11) NOT NULL AUTO_INCREMENT,
                                    `file_id` int(11) NOT NULL,
                                    `cat_id` int(11) NOT NULL,
                                    `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                                    FOREIGN KEY (`file_id`) REFERENCES '.get_table('files').'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                    FOREIGN KEY (`cat_id`) REFERENCES '.get_table('categories').'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                                    PRIMARY KEY (`id`)
                                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
                                ',
        ),
        
        '16' => array(
            'table' => get_table('user_meta'),
            'query' => 'CREATE TABLE IF NOT EXISTS `'.get_table('user_meta').'` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) DEFAULT NULL,
                `name` varchar(255) NOT NULL,
                `value` TEXT NULL,
                `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                FOREIGN KEY (`user_id`) REFERENCES '.get_table('users').'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                PRIMARY KEY (`id`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
            ',
        ),

        '17' => array(
            'table' => get_table('actions_log')INS_FAILED,
            'query' => 'CREATE TABLE IF NOT EXISTS `'.get_table('actions_log')INS_FAILED.'` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `ip_address` VARCHAR(60) NOT NULL,
                `username` VARCHAR(60) NOT NULL,
                `attempted_at` datetime NOT NULL,
                PRIMARY KEY (`id`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
            ',
        )
    );

    return $install_queries;
}
