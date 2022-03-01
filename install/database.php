<?php
/**
 * Contains the queries that will be used to create the database structure
 * when installing the system.
 *
 * @package		ProjectSend
 * @subpackage	Install
 */
if (defined('TRY_INSTALL')) {
	$timestamp = time();
	$current_version = substr(CURRENT_VERSION, 1);
	$now = date('d-m-Y');
	$expiry_default = date('Y') + 1 . "-01-01 00:00:00";

	$install_queries = array(
		'0' =>  array(
            'table'	=> TABLE_USERS,
            'query'	=> 'CREATE TABLE IF NOT EXISTS `'.TABLE_USERS.'` (
                          `id` int(11) NOT NULL AUTO_INCREMENT,
                          `user` varchar('.MAX_USER_CHARS.') NOT NULL,
                          `password` varchar('.MAX_PASS_CHARS.') NOT NULL,
                          `name` text NOT NULL,
                          `email` text NOT NULL,
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
            'params' => array(),
        ),


		'1' => array(
					'table'	=> TABLE_FILES,
					'query'	=> 'CREATE TABLE IF NOT EXISTS `'.TABLE_FILES.'` (
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
                                  FOREIGN KEY (`user_id`) REFERENCES '.TABLE_USERS.'(`id`) ON DELETE CASCADE ON UPDATE CASCADE
								) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
								',
					'params' => array(),
		),

		'2' =>  array(
					'table'	=> TABLE_OPTIONS,
					'query'	=> 'CREATE TABLE IF NOT EXISTS `'.TABLE_OPTIONS.'` (
								  `id` int(10) NOT NULL AUTO_INCREMENT,
								  `name` varchar(50) COLLATE utf8_general_ci NOT NULL,
								  `value` text COLLATE utf8_general_ci NULL,
								  PRIMARY KEY (`id`)
								) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
								',
					'params' => array(),
		),

		'3' =>  array(
					'table'	=> TABLE_GROUPS,
					'query'	=> 'CREATE TABLE IF NOT EXISTS `'.TABLE_GROUPS.'` (
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
					'params' => array(),
		),

		'4' =>  array(
					'table'	=> TABLE_MEMBERS,
					'query'	=> 'CREATE TABLE IF NOT EXISTS `'.TABLE_MEMBERS.'` (
								  `id` int(11) NOT NULL AUTO_INCREMENT,
								  `added_by` varchar(32) DEFAULT NULL,
								  `client_id` int(11) NOT NULL,
								  `group_id` int(11) NOT NULL,
								  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
								  PRIMARY KEY (`id`),
								  FOREIGN KEY (`client_id`) REFERENCES '.TABLE_USERS.'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
								  FOREIGN KEY (`group_id`) REFERENCES '.TABLE_GROUPS.'(`id`) ON DELETE CASCADE ON UPDATE CASCADE
								) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
								',
					'params' => array(),
		),

		'5' =>  array(
					'table'	=> TABLE_MEMBERS_REQUESTS,
					'query'	=> 'CREATE TABLE IF NOT EXISTS `'.TABLE_MEMBERS_REQUESTS.'` (
								  `id` int(11) NOT NULL AUTO_INCREMENT,
								  `requested_by` varchar(32) NOT NULL,
								  `client_id` int(11) NOT NULL,
								  `group_id` int(11) NOT NULL,
								  `denied` int(1) NOT NULL DEFAULT \'0\',
								  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
								  PRIMARY KEY (`id`),
								  FOREIGN KEY (`client_id`) REFERENCES '.TABLE_USERS.'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
								  FOREIGN KEY (`group_id`) REFERENCES '.TABLE_GROUPS.'(`id`) ON DELETE CASCADE ON UPDATE CASCADE
								) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
								',
					'params' => array(),
		),

		'6' =>  array(
					'table'	=> TABLE_FOLDERS,
					'query'	=> 'CREATE TABLE IF NOT EXISTS `'.TABLE_FOLDERS.'` (
								  `id` int(11) NOT NULL AUTO_INCREMENT,
								  `parent` int(11) DEFAULT NULL,
								  `name` varchar(32) NOT NULL,
								  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
								  `client_id` int(11) DEFAULT NULL,
								  `group_id` int(11) DEFAULT NULL,
								  FOREIGN KEY (`parent`) REFERENCES '.TABLE_FOLDERS.'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
								  FOREIGN KEY (`client_id`) REFERENCES '.TABLE_USERS.'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
								  FOREIGN KEY (`group_id`) REFERENCES '.TABLE_GROUPS.'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
								  PRIMARY KEY (`id`)
								) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
								',
					'params' => array(),
		),

		'7' =>  array(
					'table'	=> TABLE_FILES_RELATIONS,
					'query'	=> 'CREATE TABLE IF NOT EXISTS `'.TABLE_FILES_RELATIONS.'` (
								  `id` int(11) NOT NULL AUTO_INCREMENT,
								  `file_id` int(11) NOT NULL,
								  `client_id` int(11) DEFAULT NULL,
								  `group_id` int(11) DEFAULT NULL,
								  `folder_id` int(11) DEFAULT NULL,
								  `hidden` int(1) NOT NULL,
								  `download_count` int(16) NOT NULL DEFAULT \'0\',
								  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
								  FOREIGN KEY (`file_id`) REFERENCES '.TABLE_FILES.'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
								  FOREIGN KEY (`client_id`) REFERENCES '.TABLE_USERS.'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
								  FOREIGN KEY (`group_id`) REFERENCES '.TABLE_GROUPS.'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
								  FOREIGN KEY (`folder_id`) REFERENCES '.TABLE_FOLDERS.'(`id`) ON UPDATE CASCADE,
								  PRIMARY KEY (`id`)
								) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
								',
					'params' => array(),
		),

		'8' =>  array(
					'table'	=> TABLE_LOG,
					'query'	=> 'CREATE TABLE IF NOT EXISTS `'.TABLE_LOG.'` (
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
					'params' => array(),
		),

		'9' =>  array(
					'table'	=> TABLE_NOTIFICATIONS,
					'query'	=> 'CREATE TABLE IF NOT EXISTS `'.TABLE_NOTIFICATIONS.'` (
								  `id` int(11) NOT NULL AUTO_INCREMENT,
								  `file_id` int(11) NOT NULL,
								  `client_id` int(11) NOT NULL,
								  `upload_type` int(11) NOT NULL,
								  `sent_status` int(2) NOT NULL,
								  `times_failed` int(11) NOT NULL,
								  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
								  FOREIGN KEY (`file_id`) REFERENCES '.TABLE_FILES.'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
								  FOREIGN KEY (`client_id`) REFERENCES '.TABLE_USERS.'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
								  PRIMARY KEY (`id`)
								) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
								',
					'params' => array(),
		),

		'10' =>  array(
					'table'	=> TABLE_PASSWORD_RESET,
					'query'	=> 'CREATE TABLE IF NOT EXISTS `'.TABLE_PASSWORD_RESET.'` (
								  `id` int(11) NOT NULL AUTO_INCREMENT,
								  `user_id` int(11) DEFAULT NULL,
								  `token` varchar(32) NOT NULL,
								  `used` int(1) DEFAULT \'0\',
								  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
								  FOREIGN KEY (`user_id`) REFERENCES '.TABLE_USERS.'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
								  PRIMARY KEY (`id`)
								) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
								',
					'params' => array(),
		),

		'11' =>  array(
					'table'	=> TABLE_DOWNLOADS,
					'query'	=> 'CREATE TABLE IF NOT EXISTS `'.TABLE_DOWNLOADS.'` (
								  `id` int(11) NOT NULL AUTO_INCREMENT,
								  `user_id` int(11) DEFAULT NULL,
								  `file_id` int(11) NOT NULL,
								  `remote_ip` varchar(45) DEFAULT NULL,
								  `remote_host` text NULL,
								  `anonymous` tinyint(1) DEFAULT \'0\',
								  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
								  FOREIGN KEY (`user_id`) REFERENCES '.TABLE_USERS.'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
								  FOREIGN KEY (`file_id`) REFERENCES '.TABLE_FILES.'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
								  PRIMARY KEY (`id`)
								) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
								',
					'params' => array(),
		),

		'12' =>  array(
					'table'	=> '',
					'query'	=> "INSERT INTO ".TABLE_OPTIONS." (name, value) VALUES
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
								('timezone', 'America/Argentina/Buenos_Aires'),
								('timeformat', 'd/m/Y'),
								('allowed_file_types', '7z,ace,ai,avi,bin,bmp,bz2,cdr,csv,doc,docm,docx,eps,fla,flv,gif,gz,gzip,htm,html,iso,jpeg,jpg,mp3,mp4,mpg,odt,oog,ppt,pptx,pptm,pps,ppsx,pdf,png,psd,rar,rtf,tar,tif,tiff,tgz,txt,wav,xls,xlsm,xlsx,xz,zip'),
								('logo_filename', ''),
								('admin_email_address', :email),
								('clients_can_register', '0'),
								('last_update', :version),
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
										':base_uri'	=> $base_uri,
										':title'	=> $install_title,
										':email'	=> $admin_email,
										':version'	=> $current_version,
										':from'		=> $install_title,
										':now'		=> $now,
								),
		),

		'13' =>  array(
						'table'	=> '',
						'query'	=> "INSERT INTO ".TABLE_USERS." (id, user, password, name, email, level, active) VALUES
									(1, :username, :password, :name, :email, 9, 1)",
						'params' => array(
										':username'	=> $admin_username,
										':password'	=> $admin_pass,
										':name'		=> $admin_name,
										':email'	=> $admin_email,
						),
		),

		'14' =>  array(
					'table'	=> TABLE_CATEGORIES,
					'query'	=> 'CREATE TABLE IF NOT EXISTS `'.TABLE_CATEGORIES.'` (
								  `id` int(11) NOT NULL AUTO_INCREMENT,
								  `name` varchar(32) NOT NULL,
								  `parent` int(11) DEFAULT NULL,
								  `description` text NULL,
								  `created_by` varchar('.MAX_USER_CHARS.') NULL,
								  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
								  FOREIGN KEY (`parent`) REFERENCES '.TABLE_CATEGORIES.'(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
								  PRIMARY KEY (`id`)
								) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
								',
					'params' => array(),
		),

		'15' =>  array(
					'table'	=> TABLE_CATEGORIES_RELATIONS,
					'query'	=> 'CREATE TABLE IF NOT EXISTS `'.TABLE_CATEGORIES_RELATIONS.'` (
								  `id` int(11) NOT NULL AUTO_INCREMENT,
								  `file_id` int(11) NOT NULL,
								  `cat_id` int(11) NOT NULL,
								  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
								  FOREIGN KEY (`file_id`) REFERENCES '.TABLE_FILES.'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
								  FOREIGN KEY (`cat_id`) REFERENCES '.TABLE_CATEGORIES.'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
								  PRIMARY KEY (`id`)
								) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
								',
					'params' => array(),
        ),
        
        '16' => array(
            'table' => TABLE_USER_META,
            'query' => 'CREATE TABLE IF NOT EXISTS `'.TABLE_USER_META.'` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) DEFAULT NULL,
                `name` varchar(255) NOT NULL,
                `value` TEXT NULL,
                `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                FOREIGN KEY (`user_id`) REFERENCES '.TABLE_USERS.'(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                PRIMARY KEY (`id`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
            ',
            'params' => array(),
        ),

        '17' => array(
            'table' => TABLE_LOGINS_FAILED,
            'query' => 'CREATE TABLE IF NOT EXISTS `'.TABLE_LOGINS_FAILED.'` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `ip_address` VARCHAR(60) NOT NULL,
                `username` VARCHAR(60) NOT NULL,
                `attempted_at` datetime NOT NULL,
                PRIMARY KEY (`id`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
            ',
            'params' => array(),
        )

	);
}
