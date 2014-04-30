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
	
	$install_queries = array(
	
	'0' => '
	CREATE TABLE IF NOT EXISTS `tbl_files` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `url` text NOT NULL,
	  `filename` text NOT NULL,
	  `description` text NOT NULL,
	  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
	  `uploader` varchar('.MAX_USER_CHARS.') NOT NULL,
	  `expires` INT(1) NOT NULL default \'0\',
	  `expiry_date` TIMESTAMP NOT NULL,
	  `public_allow` INT(1) NOT NULL default \'0\',
	  `public_token` varchar(32) NULL,
	  `password` varchar(60) NULL,
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
	',

	'1' => '
	CREATE TABLE IF NOT EXISTS `tbl_options` (
	  `id` int(10) NOT NULL AUTO_INCREMENT,
	  `name` varchar(50) COLLATE utf8_general_ci NOT NULL,
	  `value` text COLLATE utf8_general_ci NOT NULL,
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
	',
	
	'2' => '
	CREATE TABLE IF NOT EXISTS `tbl_users` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `user` varchar('.MAX_USER_CHARS.') NOT NULL,
	  `password` varchar('.MAX_PASS_CHARS.') NOT NULL,
	  `name` text NOT NULL,
	  `email` varchar(60) NOT NULL,
	  `level` tinyint(1) NOT NULL DEFAULT \'0\',
	  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
	  `address` text COLLATE utf8_general_ci NULL,
	  `phone` varchar(32) COLLATE utf8_general_ci NULL,
	  `notify` tinyint(1) NOT NULL DEFAULT \'0\',
	  `contact` text COLLATE utf8_general_ci NULL,
	  `created_by` varchar('.MAX_USER_CHARS.') NULL,
	  `active` tinyint(1) NOT NULL DEFAULT \'1\',
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
	',
	
	'3' => '
	CREATE TABLE IF NOT EXISTS `tbl_groups` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
	  `created_by` varchar(32) NOT NULL,
	  `name` varchar(32) NOT NULL,
	  `description` text NOT NULL,
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
	',
	
	'4' => '
	CREATE TABLE IF NOT EXISTS `tbl_members` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
	  `added_by` varchar(32) NOT NULL,
	  `client_id` int(11) NOT NULL,
	  `group_id` int(11) NOT NULL,
	  PRIMARY KEY (`id`),
	  FOREIGN KEY (`client_id`) REFERENCES tbl_users(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	  FOREIGN KEY (`group_id`) REFERENCES tbl_groups(`id`) ON DELETE CASCADE ON UPDATE CASCADE
	) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
	',
	
	'5' => '
	CREATE TABLE IF NOT EXISTS `tbl_folders` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `parent` int(11) DEFAULT NULL,
	  `name` varchar(32) NOT NULL,
	  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
	  `client_id` int(11) DEFAULT NULL,
	  `group_id` int(11) DEFAULT NULL,
	  FOREIGN KEY (`parent`) REFERENCES tbl_folders(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	  FOREIGN KEY (`client_id`) REFERENCES tbl_users(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	  FOREIGN KEY (`group_id`) REFERENCES tbl_groups(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
	',
	
	'6' => '
	CREATE TABLE IF NOT EXISTS `tbl_files_relations` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
	  `file_id` int(11) NOT NULL,
	  `client_id` int(11) DEFAULT NULL,
	  `group_id` int(11) DEFAULT NULL,
	  `folder_id` int(11) DEFAULT NULL,
	  `hidden` int(1) NOT NULL,
	  `download_count` int(16) NOT NULL DEFAULT \'0\',
	  FOREIGN KEY (`file_id`) REFERENCES tbl_files(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	  FOREIGN KEY (`client_id`) REFERENCES tbl_users(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	  FOREIGN KEY (`group_id`) REFERENCES tbl_groups(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	  FOREIGN KEY (`folder_id`) REFERENCES tbl_folders(`id`) ON UPDATE CASCADE,
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
	',
	
	'7' => '
	CREATE TABLE IF NOT EXISTS `tbl_actions_log` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
	  `action` int(2) NOT NULL,
	  `owner_id` int(11) NOT NULL,
	  `owner_user` text DEFAULT NULL,
	  `affected_file` int(11) DEFAULT NULL,
	  `affected_account` int(11) DEFAULT NULL,
	  `affected_file_name` text DEFAULT NULL,
	  `affected_account_name` text DEFAULT NULL,
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
	',
	
	'8' => '
	CREATE TABLE IF NOT EXISTS `tbl_notifications` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
	  `file_id` int(11) NOT NULL,
	  `client_id` int(11) NOT NULL,
	  `upload_type` int(11) NOT NULL,
	  `sent_status` int(2) NOT NULL,
	  `times_failed` int(11) NOT NULL,
	  FOREIGN KEY (`file_id`) REFERENCES tbl_files(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	  FOREIGN KEY (`client_id`) REFERENCES tbl_users(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
	',
	
	'9' => '
	INSERT INTO `tbl_options` (`name`, `value`) VALUES
	(\'base_uri\', \''.$base_uri.'\'),
	(\'max_thumbnail_width\', \'100\'),
	(\'max_thumbnail_height\', \'100\'),
	(\'thumbnails_folder\', \'../../img/custom/thumbs/\'),
	(\'thumbnail_default_quality\', \'90\'),
	(\'max_logo_width\', \'300\'),
	(\'max_logo_height\', \'300\'),
	(\'this_install_title\', \''.$this_install_title.'\'),
	(\'selected_clients_template\', \'default\'),
	(\'logo_thumbnails_folder\', \'/img/custom/thumbs\'),
	(\'timezone\', \'America/Argentina/Buenos_Aires\'),
	(\'timeformat\', \'d/m/Y\'),
	(\'allowed_file_types\', \'7z,ace,ai,avi,bin,bmp,cdr,doc,docm,docx,eps,fla,flv,gif,gz,gzip,htm,html,iso,jpeg,jpg,mp3,mp4,mpg,odt,oog,ppt,pptx,pptm,pps,ppsx,pdf,png,psd,rar,rtf,tar,tif,tiff,txt,wav,xls,xlsm,xlsx,zip\'),
	(\'logo_filename\', \'logo.png\'),
	(\'admin_email_address\', \''.$got_admin_email.'\'),
	(\'clients_can_register\', \'0\'),
	(\'last_update\', \''.$current_version.'\'),
	(\'mail_system_use\', \'mail\'),
	(\'mail_smtp_host\', \'\'),
	(\'mail_smtp_port\', \'\'),
	(\'mail_smtp_user\', \'\'),
	(\'mail_smtp_pass\', \'\'),
	(\'mail_from_name\', \''.$this_install_title.'\'),
	(\'thumbnails_use_absolute\', \'0\'),
	(\'mail_copy_user_upload\', \'\'),
	(\'mail_copy_client_upload\', \'\'),
	(\'mail_copy_main_user\', \'\'),
	(\'mail_copy_addresses\', \'\'),
	(\'version_last_check\', \''.$now.'\'),
	(\'version_new_found\', \'0\'),
	(\'version_new_number\', \'\'),
	(\'version_new_url\', \'\'),
	(\'version_new_chlog\', \'\'),
	(\'version_new_security\', \'\'),
	(\'version_new_features\', \'\'),
	(\'version_new_important\', \'\'),
	(\'clients_auto_approve\', \'0\'),
	(\'clients_auto_group\', \'0\'),
	(\'clients_can_upload\', \'1\'),
	(\'email_new_file_by_user_customize\', \'0\'),
	(\'email_new_file_by_client_customize\', \'0\'),
	(\'email_new_client_by_user_customize\', \'0\'),
	(\'email_new_client_by_self_customize\', \'0\'),
	(\'email_new_user_customize\', \'0\'),
	(\'email_new_file_by_user_text\', \'\'),
	(\'email_new_file_by_client_text\', \'\'),
	(\'email_new_client_by_user_text\', \'\'),
	(\'email_new_client_by_self_text\', \'\'),
	(\'email_new_user_text\', \'\'),
	(\'email_header_footer_customize\', \'0\'),
	(\'email_header_text\', \'\'),
	(\'email_footer_text\', \'\'),
	(\'email_pass_reset_customize\', \'0\'),
	(\'email_pass_reset_text\', \'\'),
	(\'expired_files_hide\', \'1\'),
	(\'notifications_max_tries\', \'2\'),
	(\'notifications_max_days\', \'15\'),
	(\'file_types_limit_to\', \'all\'),
	(\'pass_require_upper\', \'0\'),
	(\'pass_require_lower\', \'0\'),
	(\'pass_require_number\', \'0\'),
	(\'pass_require_special\', \'0\'),
	(\'mail_smtp_auth\', \'none\')
	',
	
	'10' => '
	CREATE TABLE IF NOT EXISTS `tbl_password_reset` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `user_id` int(11) DEFAULT NULL,
	  `token` varchar(32) NOT NULL,
	  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
	  `used` int(1) DEFAULT \'0\',
	  FOREIGN KEY (`user_id`) REFERENCES tbl_users(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
	',

	'11' => '
	CREATE TABLE IF NOT EXISTS `tbl_downloads` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `user_id` int(11) DEFAULT NULL,
	  `file_id` int(11) NOT NULL,
	  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
	  FOREIGN KEY (`user_id`) REFERENCES tbl_users(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	  FOREIGN KEY (`file_id`) REFERENCES tbl_files(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
	',
	
	'12' => '
	INSERT INTO `tbl_users` (`id`, `user`, `password`, `name`, `email`, `level`, `active`) VALUES
	(1, \''.$got_admin_username.'\', \''.$got_admin_pass.'\', \''.$got_admin_name.'\', \''.$got_admin_email.'\', 9, 1);
	',

	);
}
?>