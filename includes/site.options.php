<?php
/**
 * Gets all the options from the database and define each as a constant.
 *
 * @package		ProjectSend
 * @subpackage	Core
 *
 */

/**
 * Gets the values from the options table, which has 2 columns.
 * The first one is the option name, and the second is the assigned value.
 *
 * @return array
 */
error_reporting(0);

global $dbh;

$options_values = array();
try {
	$options = $dbh->query("SELECT * FROM " . TABLE_OPTIONS);
	$options->setFetchMode(PDO::FETCH_ASSOC);

	if ( $options->rowCount() > 0) {
		while ( $row = $options->fetch() ) {
			$options_values[$row['name']] = $row['value'];
		}
	}
}
catch ( Exception $e ) {
	return FALSE;
}

/**
 * Set the options returned before as constants.
 */
if(!empty($options_values)) {
	/**
	 * The allowed file types array is set as variable and not a constant
	 * because it is re-set later on other pages (the options and the upload
	 * forms currently).
	 */
	$allowed_file_types = $options_values['allowed_file_types'];
	
	define('BASE_URI',$options_values['base_uri']);
	define('BLOCKSIZE',256);
	define('ENCRYPTION_KEY','psend_key1234567'); // 16 bit , 32 
	define('THUMBS_MAX_WIDTH',$options_values['max_thumbnail_width']);
	define('THUMBS_MAX_HEIGHT',$options_values['max_thumbnail_height']);
	define('THUMBS_FOLDER',$options_values['thumbnails_folder']);
	define('THUMBS_QUALITY',$options_values['thumbnail_default_quality']);
	define('THUMBS_USE_ABSOLUTE',$options_values['thumbnails_use_absolute']);
	define('LOGO_MAX_WIDTH',$options_values['max_logo_width']);
	define('LOGO_MAX_HEIGHT',$options_values['max_logo_height']);
	define('LOGO_FILENAME',$options_values['logo_filename']);
	define('THIS_INSTALL_SET_TITLE',$options_values['this_install_title']);
	define('TEMPLATE_USE',$options_values['selected_clients_template']);
	define('TIMEZONE_USE',$options_values['timezone']);
	define('TIMEFORMAT_USE',$options_values['timeformat']);
	define('CLIENTS_CAN_REGISTER',$options_values['clients_can_register']);
	define('BRAND_NAME',$options_values['branding_title']);

	/** Define the template path */
	define('TEMPLATE_PATH',ROOT_DIR.'/templates/'.TEMPLATE_USE.'/template.php');
	/**
	 * Wrap the e-mail definition in an IF statement in case the user 
	 * just updated to r135 and this value doesn't exist yet to prevent
	 * a php notice.
	 */	
	if (isset($options_values['admin_email_address'])) {
		define('ADMIN_EMAIL_ADDRESS',$options_values['admin_email_address']);
	}
	/**
	 * For versions 282 and up
	 */	

	if (isset($options_values['mail_system_use'])) {
		define('MAIL_SYSTEM',$options_values['mail_system_use']);
		define('SMTP_HOST',$options_values['mail_smtp_host']);
		define('SMTP_PORT',$options_values['mail_smtp_port']);
		define('SMTP_USER',$options_values['mail_smtp_user']);
		define('SMTP_PASS',$options_values['mail_smtp_pass']);
		define('MAIL_FROM_NAME',$options_values['mail_from_name']);
	}
	/**
	 * For versions 364 and up
	 */	
	if (isset($options_values['mail_copy_user_upload'])) {
		define('COPY_MAIL_ON_USER_UPLOADS',$options_values['mail_copy_user_upload']);
		define('COPY_MAIL_ON_CLIENT_UPLOADS',$options_values['mail_copy_client_upload']);
		define('COPY_MAIL_MAIN_USER',$options_values['mail_copy_main_user']);
		define('COPY_MAIL_ADDRESSES',$options_values['mail_copy_addresses']);
		define('MAIL_DROP_OFF_REQUEST',$options_values['mail_drop_off_request']);
	}

	/**
	 * For versions 377 and up
	 */	
	if (isset($options_values['version_last_check'])) {
		define('VERSION_LAST_CHECK',$options_values['version_last_check']);
		define('VERSION_NEW_FOUND',$options_values['version_new_found']);
		if (VERSION_NEW_FOUND == '1') {
			define('VERSION_NEW_NUMBER',$options_values['version_new_number']);
			define('VERSION_NEW_URL',$options_values['version_new_url']);
			define('VERSION_NEW_CHLOG',$options_values['version_new_chlog']);
			define('VERSION_NEW_SECURITY',$options_values['version_new_security']);
			define('VERSION_NEW_FEATURES',$options_values['version_new_features']);
			define('VERSION_NEW_IMPORTANT',$options_values['version_new_important']);
		}
	}
	/**
	 * For versions 386 and up
	 */	
	if (isset($options_values['clients_auto_approve'])) {
		define('CLIENTS_AUTO_APPROVE',$options_values['clients_auto_approve']);
		define('CLIENTS_AUTO_GROUP',$options_values['clients_auto_group']);
		define('CLIENTS_CAN_UPLOAD',$options_values['clients_can_upload']);
	}

	/**
	 * For versions 419 and up
	 */	
	if (isset($options_values['email_new_file_by_user_customize'])) {
		/** Checkboxes */
		define('EMAILS_FILE_BY_USER_USE_CUSTOM',$options_values['email_new_file_by_user_customize']);
		define('EMAILS_FILE_BY_CLIENT_USE_CUSTOM',$options_values['email_new_file_by_client_customize']);
		define('EMAILS_CLIENT_BY_USER_USE_CUSTOM',$options_values['email_new_client_by_user_customize']);
		define('EMAILS_CLIENT_BY_SELF_USE_CUSTOM',$options_values['email_new_client_by_self_customize']);
		define('EMAILS_NEW_USER_USE_CUSTOM',$options_values['email_new_user_customize']);
		/** Texts */
		define('EMAILS_FILE_BY_USER_TEXT',$options_values['email_new_file_by_user_text']);
		define('EMAILS_FILE_BY_CLIENT_TEXT',$options_values['email_new_file_by_client_text']);
		define('EMAILS_CLIENT_BY_USER_TEXT',$options_values['email_new_client_by_user_text']);
		define('EMAILS_CLIENT_BY_SELF_TEXT',$options_values['email_new_client_by_self_text']);
		define('EMAILS_NEW_USER_TEXT',$options_values['email_new_user_text']);
	}

	/**
	 * For versions 426 and up
	 */	
	if (isset($options_values['email_header_footer_customize'])) {
		/** Checkbox */
		define('EMAILS_HEADER_FOOTER_CUSTOM',$options_values['email_header_footer_customize']);
		/** Texts */
		define('EMAILS_HEADER_TEXT',$options_values['email_header_text']);
		define('EMAILS_FOOTER_TEXT',$options_values['email_footer_text']);
	}

	/**
	 * For versions 442 and up
	 */	
	if (isset($options_values['email_pass_reset_customize'])) {
		/** Checkbox */
		define('EMAILS_PASS_RESET_USE_CUSTOM',$options_values['email_pass_reset_customize']);
		/** Text */
		define('EMAILS_PASS_RESET_TEXT',$options_values['email_pass_reset_text']);
	}
	// -- added by B)
		if (isset($options_values['email_drop_off_request'])) {
		/** Checkbox */
		define('DROPOFF_REQUEST_CUSTOM',$options_values['email_drop_off_request']);
		/** Text */
		define('DROPOFF_REQUEST_CUSTOM_TEXT',$options_values['email_drop_off_request_text']);
	}
	/**
	 * For versions 464 and up
	 */	
	if (isset($options_values['expired_files_hide'])) {
		define('EXPIRED_FILES_HIDE',$options_values['expired_files_hide']);
	}

	/**
	 * For versions 487 and up
	 */	
	if (isset($options_values['notifications_max_tries'])) {
		define('NOTIFICATIONS_MAX_TRIES',$options_values['notifications_max_tries']);
		define('NOTIFICATIONS_MAX_DAYS',$options_values['notifications_max_days']);
	}
	else {
		define('NOTIFICATIONS_MAX_TRIES','2');
		define('NOTIFICATIONS_MAX_DAYS','15');
	}

	/**
	 * For versions 528 and up
	 */	
	if (isset($options_values['file_types_limit_to'])) {
		define('FILE_TYPES_LIMIT_TO',$options_values['file_types_limit_to']);
		define('PASS_REQ_UPPER',$options_values['pass_require_upper']);
		define('PASS_REQ_LOWER',$options_values['pass_require_lower']);
		define('PASS_REQ_NUMBER',$options_values['pass_require_number']);
		define('PASS_REQ_SPECIAL',$options_values['pass_require_special']);
		define('SMTP_AUTH',$options_values['mail_smtp_auth']);
	}

	/**
	 * For versions 645 and up
	 */	
	if (isset($options_values['use_browser_lang'])) {
		define('USE_BROWSER_LANG',$options_values['use_browser_lang']);
	}
	
	/**
	 * For versions 672 and up
	 */	
	if (isset($options_values['clients_can_delete_own_files'])) {
		define('CLIENTS_CAN_DELETE_OWN_FILES',$options_values['clients_can_delete_own_files']);
	}

	/**
	 * For versions 673 and up
	 * For Google Login
	 */
	include(__DIR__.'/../aes_class.php');

	if (isset($options_values['google_client_id'])) {
		$aesgoogleidarray = new AES($options_values['google_client_id'], ENCRYPTION_KEY, BLOCKSIZE);
		$aesgoogleid = $aesgoogleidarray->decrypt();
		define('GOOGLE_CLIENT_ID',$aesgoogleid);
		$aesgooglesecretarray = new AES($options_values['google_client_secret'], ENCRYPTION_KEY, BLOCKSIZE);
		$aesgooglesecret = $aesgooglesecretarray->decrypt();
		define('GOOGLE_CLIENT_SECRET',$aesgooglesecret);
		define('GOOGLE_SIGNIN_ENABLED', $options_values['google_signin_enabled']);
	}

		/**
	 * For versions 673 and up
	 * For facebook Login
	 */
	
	if (isset($options_values['facebook_client_id'])) {
		$facebookidarray = new AES($options_values['facebook_client_id'], ENCRYPTION_KEY, BLOCKSIZE);
		$facebookid = $facebookidarray->decrypt();
		define('FACEBOOK_CLIENT_ID',$facebookid);
		$facebooksecretarray = new AES($options_values['facebook_client_secret'], ENCRYPTION_KEY, BLOCKSIZE);
		$facebooksecret = $facebooksecretarray->decrypt();
		define('FACEBOOK_CLIENT_SECRET',$facebooksecret);
		define('FACEBOOK_SIGNIN_ENABLED', $options_values['facebook_signin_enabled']);
	}
	/**
	 * For versions 673 and up
	 * For twitter Login
	 */
	if (isset($options_values['twitter_client_id'])) {
		$twitteridarray = new AES($options_values['twitter_client_id'], ENCRYPTION_KEY, BLOCKSIZE);
		$twitterid = $twitteridarray->decrypt();
		define('TWITTER_CLIENT_ID',$twitterid);
		$twittersecretarray = new AES($options_values['twitter_client_secret'], ENCRYPTION_KEY, BLOCKSIZE);
		$twittersecret = $twittersecretarray->decrypt();
		define('TWITTER_CLIENT_SECRET',$twittersecret);
		define('TWITTER_SIGNIN_ENABLED', $options_values['twitter_signin_enabled']);
	}
	/**
	 * For versions 673 and up
	 * For YAHOO Login
	 */
	if (isset($options_values['yahoo_client_id'])) {
		$yahooidarray = new AES($options_values['yahoo_client_id'], ENCRYPTION_KEY, BLOCKSIZE);
		$yahooid = $yahooidarray->decrypt();
		define('YAHOO_CLIENT_ID',$yahooid);
		$yahoosecretarray = new AES($options_values['yahoo_client_secret'], ENCRYPTION_KEY, BLOCKSIZE);
		$yahoosecret = $yahoosecretarray->decrypt();
		define('YAHOO_CLIENT_SECRET',$yahoosecret);
		define('YAHOO_SIGNIN_ENABLED', $options_values['yahoo_signin_enabled']);
	}
	/**
	 * For versions 673 and up
	 * For linkedin Login
	 */
	if (isset($options_values['linkedin_client_id'])) {
		$linkedinidarray = new AES($options_values['linkedin_client_id'], ENCRYPTION_KEY, BLOCKSIZE);
		$linkedinid = $linkedinidarray->decrypt();
		
		define('LINKEDIN_CLIENT_ID',$linkedinid);
		$linkedinsecretarray = new AES($options_values['linkedin_client_secret'], ENCRYPTION_KEY, BLOCKSIZE);
		$linkedinsecret = $linkedinsecretarray->decrypt();
		define('LINKEDIN_CLIENT_SECRET',$linkedinsecret);
		define('LINKEDIN_SIGNIN_ENABLED', $options_values['linkedin_signin_enabled']);
	}	
	if (isset($options_values['windows_client_id'])) {
		$windowsclientidarray = new AES($options_values['windows_client_id'], ENCRYPTION_KEY, BLOCKSIZE);
		$windowsclientid = $windowsclientidarray->decrypt();
		define('WINDOWS_SIGNIN_ENABLED', $options_values['windows_signin_enabled']);
		define('WINDOWS_CLIENT_ID',$windowsclientid);
	}
	if (isset($options_values['ldap_server_url'])) {
		define('LDAP_SERVER',$options_values['ldap_server_url']);
		define('LDAP_PORT',$options_values['ldap_bind_port']);
		define('LDAP_BIND_DN', $options_values['ldap_bind_dn']);
		define('LDAP_BIND_PASS', $options_values['ldap_bind_password']);
		define('LDAP_SIGNIN_ENABLED', $options_values['ldap_signin_enabled']);
	}
	/**
	 * For versions 737 and up
	 * For reCAPTCHA
	 */
	if (isset($options_values['recaptcha_enabled'])) {
		define('RECAPTCHA_ENABLED', $options_values['recaptcha_enabled']);
		define('RECAPTCHA_SITE_KEY', $options_values['recaptcha_site_key']);
		define('RECAPTCHA_SECRET_KEY', $options_values['recaptcha_secret_key']);
		
		if (
				RECAPTCHA_ENABLED == 1 &&
				!empty($options_values['recaptcha_site_key']) &&
				!empty($options_values['recaptcha_secret_key'])
			)
		{
			define('RECAPTCHA_AVAILABLE', true);
		}
	}
	if (isset($options_values['orphan_deletion_settings'])) {
		define('ORPHAN_DELETION_SETTINGS', $options_values['orphan_deletion_settings']);
	}else{
		define('ORPHAN_DELETION_SETTINGS', '0');
	}


	/**
	 * Set the default timezone based on the value of the Timezone select box
	 * of the options page.
	 */
	date_default_timezone_set(TIMEZONE_USE);
} else {
    define('BASE_URI', '/');
}

/**
 * Timthumb
 */
if (defined('BASE_URI')) {
	define('TIMTHUMB_URL',BASE_URI.'includes/timthumb/timthumb.php');
	define('TIMTHUMB_ABS',ROOT_DIR.'/includes/timthumb/timthumb.php');
}

/**
 * Footable
 * Define the amount of items to show
 * TODO: Get this value of a cookie if it exists.
 */
if (!defined('FOOTABLE_PAGING_NUMBER')) {
	define('FOOTABLE_PAGING_NUMBER', '10');
	define('FOOTABLE_PAGING_NUMBER_LOG', '15');
}
?>
