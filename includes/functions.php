<?php
/**
 * Define the common functions that can be accessed from anywhere.
 *
 * @package		ProjectSend
 * @subpackage	Functions
 */

/**
 * Check if ProjectSend is installed by looping over the main tables.
 * All tables must exist to verify the installation.
 * If any table is missing, the installation is considered corrupt.
 */

function is_projectsend_installed() {
	global $current_tables;

	$tables_missing = 0;
	/**
	 * This table list is defined on sys.vars.php
	 */
	foreach ($current_tables as $table) {
		if ( !tableExists( $table ) ) {
			$tables_missing++;
		}
	}
	if ($tables_missing > 0) {
		return false;
	}
	else {
		return true;
	}
}

function generate_password() {
	/**
	 * Random compat library, a polyfill for PHP 7's random_bytes();
	 * @link: https://github.com/paragonie/random_compat
	 */
	require_once(ROOT_DIR . '/includes/random_compat/random_compat.phar' );
	$error_unexpected	= __('An unexpected error has occurred', 'cftp_admin');
	$error_os_fail		= __('Could not generate a random password', 'cftp_admin');

	try {
		$password = random_bytes(12);
	} catch (TypeError $e) {
		die($error_unexpected); 
	} catch (Error $e) {
		die($error_unexpected); 
	} catch (Exception $e) {
		die($error_os_fail); 
	}
	
	return bin2hex($password);
}


/**
 * Check if a table exists in the current database.
 *
 * @param string $table Table to search for.
 * @return bool TRUE if table exists, FALSE if no table found.
 * by esbite on http://stackoverflow.com/questions/1717495/check-if-a-database-table-exists-using-php-pdo
 */
function tableExists($table) {
	global $dbh;

    try {
        $result = $dbh->prepare("SELECT 1 FROM $table LIMIT 1");
		$result->execute();
    } catch (Exception $e) {
        return false;
    }

    // Result is either boolean FALSE (no table found) or PDOStatement Object (table found)
    return $result !== false;
}

/**
 * Check if a client id exists on the database.
 * Used on the Edit client page.
 *
 * @return bool
 */
function client_exists_id($id)
{
	global $dbh;
	$statement = $dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE id=:id");
	$statement->bindParam(':id', $id, PDO::PARAM_INT);
	$statement->execute();
	if ( $statement->rowCount() > 0 ) {
		return true;
	}
	else {
		return false;
	}
}

/**
 * Check if a user id exists on the database.
 * Used on the Edit user page.
 *
 * @return bool
 */
function user_exists_id($id)
{
	global $dbh;
	$statement = $dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE id=:id");
	$statement->bindParam(':id', $id, PDO::PARAM_INT);
	$statement->execute();
	if ( $statement->rowCount() > 0 ) {
		return true;
	}
	else {
		return false;
	}
}

/**
 * Check if a group id exists on the database.
 * Used on the Edit group page.
 *
 * @return bool
 */
function group_exists_id($id)
{
	global $dbh;
	$statement = $dbh->prepare("SELECT * FROM " . TABLE_GROUPS . " WHERE id=:id");
	$statement->bindParam(':id', $id, PDO::PARAM_INT);
	$statement->execute();
	if ( $statement->rowCount() > 0 ) {
		return true;
	}
	else {
		return false;
	}
}

/**
 * Get all the client information knowing only the id
 * Used on the Manage files page.
 *
 * @return array
 */
function get_client_by_id($client)
{
	global $dbh;
	$statement = $dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE id=:id");
	$statement->bindParam(':id', $client, PDO::PARAM_INT);
	$statement->execute();
	$statement->setFetchMode(PDO::FETCH_ASSOC);

	while ( $row = $statement->fetch() ) {
		$information = array(
							'id'			=> $row['id'],
							'name'			=> $row['name'],
							'username'		=> $row['user'],
							'address'		=> $row['address'],
							'phone'			=> $row['phone'],
							'email'			=> $row['email'],
							'notify'		=> $row['notify'],
							'level'			=> $row['level'],
							'active'		=> $row['active'],
							'contact'		=> $row['contact'],
							'created_date'	=> $row['timestamp'],
							'created_by'	=> $row['created_by']
						);
		if ( !empty( $information ) ) {
			return $information;
		}
		else {
			return false;
		}
	}
}


/**
 * Get all the client information knowing only the log in username
 *
 * @return array
 */
function get_client_by_username($client)
{
	global $dbh;
	$statement = $dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE user=:username");
	$statement->bindParam(':username', $client);
	$statement->execute();
	$statement->setFetchMode(PDO::FETCH_ASSOC);

	while ( $row = $statement->fetch() ) {
		$information = array(
							'id'			=> $row['id'],
							'name'			=> $row['name'],
							'username'		=> $row['user'],
							'address'		=> $row['address'],
							'phone'			=> $row['phone'],
							'email'			=> $row['email'],
							'notify'		=> $row['notify'],
							'level'			=> $row['level'],
							'active'		=> $row['active'],
							'contact'		=> $row['contact'],
							'created_date'	=> $row['timestamp'],
							'created_by'	=> $row['created_by']
						);
		if ( !empty( $information ) ) {
			return $information;
		}
		else {
			return false;
		}
	}
}

/**
 * Get all the client information knowing only the log in username
 *
 * @return array
 */
function get_logged_account_id($username)
{
	global $dbh;
	$statement = $dbh->prepare("SELECT id FROM " . TABLE_USERS . " WHERE user=:user");
	$statement->execute(
						array(
							':user'	=> $username
						)
					);
	$statement->setFetchMode(PDO::FETCH_ASSOC);

	while ( $row = $statement->fetch() ) {
		$return_id = $row['id'];
		if ( !empty( $return_id ) ) {
			return $return_id;
		}
		else {
			return false;
		}
	}
}


/**
 * Used on the file uploading process to determine if the client
 * needs to be notified by e-mail.
 */
function check_if_notify_client($client)
{
	global $dbh;
	$statement = $dbh->prepare("SELECT notify, email FROM " . TABLE_USERS . " WHERE user=:user");
	$statement->execute(
						array(
							':user'	=> $client
						)
					);
	$statement->setFetchMode(PDO::FETCH_ASSOC);

	while ( $row = $statement->fetch() ) {
		if ( $row['notify'] == '1' ) {
			return $row['email'];
		}
		else {
			return false;
		}
	}
}


/**
 * Get all the user information knowing only the log in username
 *
 * @return array
 */
function get_user_by_username($user)
{
	global $dbh;
	$statement = $dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE user=:user");
	$statement->execute(
						array(
							':user'	=> $user
						)
					);
	$statement->setFetchMode(PDO::FETCH_ASSOC);

	if ( $statement->rowCount() > 0 ) {
		while ( $row = $statement->fetch() ) {
			$information = array(
								'id'			=> $row['id'],
								'username'		=> $row['user'],
								'name'			=> $row['name'],
								'email'			=> $row['email'],
								'level'			=> $row['level'],
								'active'		=> $row['active'],
								'created_date'	=> $row['timestamp']
							);
			if ( !empty( $information ) ) {
				return $information;
			}
			else {
				return false;
			}
		}
	}
}

/**
 * Get all the user information knowing only the log in username
 *
 * @return array
 */
function get_user_by_id($id)
{
	global $dbh;
	$statement = $dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE id=:id");
	$statement->bindParam(':id', $id, PDO::PARAM_INT);
	$statement->execute();
	$statement->setFetchMode(PDO::FETCH_ASSOC);

	while ( $row = $statement->fetch() ) {
		$information = array(
							'id'			=> $row['id'],
							'username'		=> $row['user'],
							'name'			=> $row['name'],
							'email'			=> $row['email'],
							'level'			=> $row['level'],
							'created_date'	=> $row['timestamp']
						);
		if ( !empty( $information ) ) {
			return $information;
		}
		else {
			return false;
		}
	}
}


/**
 * Standard footer mark up and information generated on this function to
 * prevent code repetition.
 * Used on the default template, log in page, install page and the back-end
 * footer file.
 */
function default_footer_info($logged = true)
{
?>
	<footer>
		<div id="footer">
			<?php _e('Provided by', 'cftp_admin'); ?> <a href="<?php echo SYSTEM_URI; ?>" target="_blank"><?php echo SYSTEM_NAME; ?></a> <?php if ($logged == true) { _e('version', 'cftp_admin'); echo ' ' . CURRENT_VERSION; } ?> - <?php _e('Free software', 'cftp_admin'); ?>
		</div>
	</footer>
<?php
}


/**
 * Standard "There are no clients" message mark up and information
 * generated on this function to prevent code repetition.
 *
 * Used on the upload pages and the clients list.
 */
function message_no_clients()
{
?>
	<div class="whitebox whiteform whitebox_text">
		<p><?php _e('There are no clients at the moment', 'cftp_admin'); ?></p>
		<p><a href="clients-add.php" target="_self"><?php _e('Create a new one', 'cftp_admin'); ?></a> <?php _e('to be able to upload files for that account.', 'cftp_admin'); ?></p>
	</div>
<?php
}


/**
 * Generate a system text message.
 *
 * Current CSS available message classes:
 * - message_ok
 * - message_error
 * - message_info
 *
 */	
function system_message($type,$message,$div_id = '')
{
	$close = false;

	switch ($type) {
		case 'ok':
			$class = 'success';
			$close = true;
			break;
		case 'error':
			$class = 'danger';
			$close = true;
			break;
		case 'info':
			$class = 'info';
			break;
		case 'warning':
			$class = 'warning';
			break;
	}

	//$return = '<div class="message message_'.$type.'"';
	$return = '<div class="alert alert-'.$class.'"';
	if (isset($div_id) && $div_id != '') {
		$return .= ' id="'.$div_id.'"';
	}

	$return .= '>';

	if ($close == true) {
		$return .= '<a href="#" class="close" data-dismiss="alert">&times;</a>';
	}

	$return .= $message;

	$return .= '</div>';
	return $return;
}


/**
 * Function used accross the system to determine if the current logged in
 * account has permission to do something.
 * 
 */
function in_session_or_cookies($levels)
{
	if (isset($_SESSION['userlevel']) && (in_array($_SESSION['userlevel'],$levels))) {
		return true;
	}
	/**
	 * Cookies are no longer used this way.
	 * userlevel_check.php has the answer.
	 */
	/*
	else if (isset($_COOKIE['userlevel']) && (in_array($_COOKIE['userlevel'],$levels))) {
		return true;
	}
	*/
	else {
		return false;
	}
}


/**
 * Returns the current logged in account level either from the active
 * session or the cookies.
 *
 * @todo Validate the returned value against the one stored on the database
 */
function get_current_user_level()
{
	$level = 0;
	if (isset($_SESSION['userlevel'])) {
		$level = $_SESSION['userlevel'];
	}
	/*
	elseif (isset($_COOKIE['userlevel'])) {
		$level = $_COOKIE['userlevel'];
	}
	*/
	return $level;
}


/**
 * Returns the current logged in account username either from the active
 * session or the cookies.
 *
 * @todo Validate the returned value against the one stored on the database
 */
function get_current_user_username()
{
	$user = '';
	/*
	if (isset($_COOKIE['loggedin'])) {
		$user = $_COOKIE['loggedin'];
	}
	*/
	/*else*/
	if (isset($_SESSION['loggedin'])) {
		$user = $_SESSION['loggedin'];
	}
	return $user;
}

/**
 * Wrapper for html_entities with default options
 * 
 */
function html_output($str, $flags = ENT_QUOTES, $encoding = 'UTF-8', $double_encode = false)
{

   return htmlentities($str, $flags, $encoding, $double_encode);

}


/**
 * Solution by Philippe Flipflip. Fixes an error that would not convert special
 * characters when saving to the database.
 */
function encode_html($str) {
	$str = htmlentities($str, ENT_QUOTES, $encoding='utf-8');
	//$str = mysql_real_escape_string($str);
	$str = nl2br($str);
	return $str;
}


/**
 * Based on a script found on webcheatsheet. Fixed an issue from the original code.
 * Used on the installation form to fill the URI field automatically.
 *
 * @author		http://webcheatsheet.com
 * @link		http://www.webcheatsheet.com/php/get_current_page_url.php
 */
function get_current_url()
{
	$pageURL = 'http';
	if (!empty($_SERVER['HTTPS'])) {
		if($_SERVER['HTTPS'] == 'on'){
			$pageURL .= "s";
		}
	}
	$pageURL .= "://";
	if ($_SERVER["SERVER_PORT"] != "80") {
		$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
	} else {
		$pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	}

	/**
	 * Check if we are accesing the install folder or the index.php file directly
	 */
	$extension = substr($pageURL,-4);
	if ($extension=='.php') {
		$pageURL = substr($pageURL,0,-17);
		return $pageURL;
	}
	else {
		$pageURL = substr($pageURL,0,-8);
		return $pageURL;
	}
}

/**
 * Receives the size of a file in bytes, and formats it for readability.
 * Used on files listings (templates and the files manager).
 */
function format_file_size($file)
{
	if ($file < 1024) {
		 /** No digits so put a ? much better than just seeing Byte */
		echo (ctype_digit($file))? $file . ' Byte' :  ' ? ' ;
	} elseif ($file < 1048576) {
		echo round($file / 1024, 2) . ' KB';
	} elseif ($file < 1073741824) {
		echo round($file / 1048576, 2) . ' MB';
	} elseif ($file < 1099511627776) {
		echo round($file / 1073741824, 2) . ' GB';
	} elseif ($file < 1125899906842624) {
		echo round($file / 1099511627776, 2) . ' TB';
	} elseif ($file < 1152921504606846976) {
		echo round($file / 1125899906842624, 2) . ' PB';
	} elseif ($file < 1180591620717411303424) {
		echo round($file / 1152921504606846976, 2) . ' EB';
	} elseif ($file < 1208925819614629174706176) {
		echo round($file / 1180591620717411303424, 2) . ' ZB';
	} else {
		echo round($file / 1208925819614629174706176, 2) . ' YB';
	}
}


/**
 * Since filesize() was giving trouble with files larger
 * than 2gb, I looked for a solution and found this great
 * function by Alessandro Marinuzzi from www.alecos.it on
 * http://stackoverflow.com/questions/5501451/php-x86-how-
 * to-get-filesize-of-2gb-file-without-external-program
 *
 * I changed the name of the function and split it in 2,
 * because I do not want to display it directly.
 */
function get_real_size($file)
{
	clearstatcache();
    if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
		if (class_exists("COM")) {
			$fsobj = new COM('Scripting.FileSystemObject');
			$f = $fsobj->GetFile(realpath($file));
			$ff = $f->Size;
		}
		else {
	        $ff = trim(exec("for %F in (\"" . $file . "\") do @echo %~zF"));
		}
    }
	elseif (PHP_OS == 'Darwin') {
		$ff = trim(shell_exec("stat -f %z " . escapeshellarg($file)));
    }
	elseif ((PHP_OS == 'Linux') || (PHP_OS == 'FreeBSD') || (PHP_OS == 'Unix') || (PHP_OS == 'SunOS')) {
		$ff = trim(shell_exec("stat -c%s " . escapeshellarg($file)));
    }
	else {
		$ff = filesize($file);
	}

	/** Fix for 0kb downloads by AlanReiblein */
	if (!ctype_digit($ff)) {
		 /* returned value not a number so try filesize() */
		$ff=filesize($file);
	}

	return $ff;
}

/**
 * Delete just one file.
 * Used on the files managment page.
 */
function delete_file_from_disk($filename)
{
	chmod($filename, 0777);
	unlink($filename);
}

/**
 * Deletes all files and sub-folders of the selected directory.
 * Used when deleting a client.
 */
function delete_recursive($dir)
{
	if (is_dir($dir)) {
		if ($dh = opendir($dir)) {
			while (($file = readdir($dh)) !== false ) {
				if( $file != "." && $file != ".." ) {
					if( is_dir( $dir . $file ) ) {
						delete_recursive( $dir . $file . "/" );
						rmdir( $dir . $file );
					}
					else {
						chmod($dir.$file, 0777);
						unlink($dir.$file);
					}
				}
		   }
		   closedir($dh);
		   rmdir($dir);
	   }
	}
}

/**
 * Takes a text string and makes an excerpt.
 */
function make_excerpt($string, $length, $break = "...")
{
	if (strlen($string) > $length) {
		$pos = strpos($string, " ", $length);
		return substr($string, 0, $pos) . $break;
	}
	return $string;
}

/**
 * Generates a random string to be used on the automatically
 * created zip files and tokens.
 */
function generateRandomString($length = 10)
{
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $rnd_result = '';
    for ($i = 0; $i < $length; $i++) {
        $rnd_result .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $rnd_result;
}


/**
 * Prepare the logo file using the database options
 * for the file name and the thumbnails path value.
 */
function generate_logo_url()
{
	$logo_file = array();
	$logo_file['exists'] = false;

	$logo_file['url'] = '/img/custom/logo/'.LOGO_FILENAME;
	if (file_exists(ROOT_DIR.$logo_file['url'])) {
		$logo_file['exists'] = true;
		if (THUMBS_USE_ABSOLUTE == '1') {
			$logo_file['url'] = BASE_URI.$logo_file['url'];
		}
	}
	return $logo_file;
}


/**
 * This function is called when a file is loaded
 * directly, but it shouldn't.
 */
function prevent_direct_access()
{
	if(!defined('CAN_INCLUDE_FILES')){
		ob_end_flush();
		exit;
	}
}

/**
 * If password rules are set, show a message
 */
function password_notes()
{
	$pass_notes_output = '';

	global $validation_req_upper;
	global $validation_req_lower;
	global $validation_req_number;
	global $validation_req_special;

	$rules_active	= array();
	$rules			= array(
							'lower'		=> array(
												'value'	=> PASS_REQ_UPPER,
												'text'	=> $validation_req_upper,
											),
							'upper'		=> array(
												'value'	=> PASS_REQ_LOWER,
												'text'	=> $validation_req_lower,
											),
							'number'	=> array(
												'value'	=> PASS_REQ_NUMBER,
												'text'	=> $validation_req_number,
											),
							'special'	=> array(
												'value'	=> PASS_REQ_SPECIAL,
												'text'	=> $validation_req_special,
											),
						);

	foreach ( $rules as $rule => $data ) {
		if ( $data['value'] == '1' ) {
			$rules_active[$rule] = $data['text'];
		}
	}
	
	if ( count( $rules_active ) > 0 ) {
		$pass_notes_output = '<p class="field_note">' . __('The password must contain, at least:','cftp_admin') . '</strong><br />';
			foreach ( $rules_active as $rule => $text ) {
				$pass_notes_output .= '- ' . $text . '<br>';
			}
		$pass_notes_output .= '</p>';
	}
	
	return $pass_notes_output;
}


/**
 * Creates a standarized download link. Used on
 * each template.
 */
function make_download_link($file_info)
{
	global $client_info;
	$download_link = BASE_URI.
						'process.php?do=download
						&amp;client='.CURRENT_USER_USERNAME.'
						&amp;client_id='.$client_info['id'].'
						&amp;id='.$file_info['id'];
	/*
						&amp;origin='.$file_info['origin'];
	if (!empty($file_info['group_id'])) {
		$download_link .= '&amp;group_id='.$file_info['group_id'];
	}
	*/
	return $download_link;
}


/**
 * Renders an action recorded on the log.
 */
function render_log_action($params)
{
	$action = $params['action'];
	$timestamp = $params['timestamp'];
	$owner_id = $params['owner_id'];
	$owner_user = $params['owner_user'];
	$affected_file = $params['affected_file'];
	$affected_file_name = $params['affected_file_name'];
	$affected_account = $params['affected_account'];
	$affected_account_name = $params['affected_account_name'];
	
	switch ($action) {
		case 0:
			$action_ico = 'install';
			$action_text = __('ProjectSend was installed','cftp_admin');
			break;
		case 1:
			$action_ico = 'login';
			$part1 = $owner_user;
			$action_text = __('logged in to the system.','cftp_admin');
			break;
		case 2:
			$action_ico = 'user-add';
			$part1 = $owner_user;
			$action_text = __('created the user account','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 3:
			$action_ico = 'client-add';
			$part1 = $owner_user;
			$action_text = __('created the client account ','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 4:
			$action_ico = 'client-add';
			$part1 = $affected_account_name;
			$action_text = __('created a client account for themself.','cftp_admin');
			break;
		case 5:
			$action_ico = 'file-add';
			$part1 = $owner_user;
			$action_text = __('(user) uploaded the file','cftp_admin');
			$part2 = $affected_file_name;
			break;
		case 6:
			$action_ico = 'file-add';
			$part1 = $owner_user;
			$action_text = __('(client) uploaded the file','cftp_admin');
			$part2 = $affected_file_name;
			break;
		case 7:
			$action_ico = 'file-download';
			$part1 = $owner_user;
			$action_text = __('(user) downloaded the file','cftp_admin');
			$part2 = $affected_file_name;
			$part3 = __('assigned to:','cftp_admin');
			$part4 = $affected_account_name;
			break;
		case 8:
			$action_ico = 'file-download';
			$part1 = $owner_user;
			$action_text = __('(client) downloaded the file','cftp_admin');
			$part2 = $affected_file_name;
			break;
		case 9:
			$action_ico = 'download-zip';
			$part1 = $owner_user;
			$action_text = __('generated a zip file','cftp_admin');
			break;
		case 10:
			$action_ico = 'file-unassign';
			$part1 = $owner_user;
			$action_text = __('unassigned the file','cftp_admin');
			$part2 = $affected_file_name;
			$part3 = __('from the client:','cftp_admin');
			$part4 = $affected_account_name;
			break;
		case 11:
			$action_ico = 'file-unassign';
			$part1 = $owner_user;
			$action_text = __('unassigned the file','cftp_admin');
			$part2 = $affected_file_name;
			$part3 = __('from the group:','cftp_admin');
			$part4 = $affected_account_name;
			break;
		case 12:
			$action_ico = 'file-delete';
			$part1 = $owner_user;
			$action_text = __('deleted the file','cftp_admin');
			$part2 = $affected_file_name;
			break;
		case 13:
			$action_ico = 'user-edit';
			$part1 = $owner_user;
			$action_text = __('edited the user','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 14:
			$action_ico = 'client-edit';
			$part1 = $owner_user;
			$action_text = __('edited the client','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 15:
			$action_ico = 'group-edit';
			$part1 = $owner_user;
			$action_text = __('edited the group','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 16:
			$action_ico = 'user-delete';
			$part1 = $owner_user;
			$action_text = __('deleted the user','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 17:
			$action_ico = 'client-delete';
			$part1 = $owner_user;
			$action_text = __('deleted the client','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 18:
			$action_ico = 'group-delete';
			$part1 = $owner_user;
			$action_text = __('deleted the group','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 19:
			$action_ico = 'client-activate';
			$part1 = $owner_user;
			$action_text = __('activated the client','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 20:
			$action_ico = 'client-deactivate';
			$part1 = $owner_user;
			$action_text = __('deactivated the client','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 21:
			$action_ico = 'file-hidden';
			$part1 = $owner_user;
			$action_text = __('marked as hidden the file','cftp_admin');
			$part2 = $affected_file_name;
			$part3 = __('to:','cftp_admin');
			$part4 = $affected_account_name;
			break;
		case 22:
			$action_ico = 'file-visible';
			$part1 = $owner_user;
			$action_text = __('marked as visible the file','cftp_admin');
			$part2 = $affected_file_name;
			$part3 = __('to:','cftp_admin');
			$part4 = $affected_account_name;
			break;
		case 23:
			$action_ico = 'group-add';
			$part1 = $owner_user;
			$action_text = __('created the group','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 24:
			$action_ico = 'login';
			$part1 = $owner_user;
			$action_text = __('logged in to the system.','cftp_admin');
			break;
		case 25:
			$action_ico = 'file-assign';
			$part1 = $owner_user;
			$action_text = __('assigned the file','cftp_admin');
			$part2 = $affected_file_name;
			$part3 = __('to the client:','cftp_admin');
			$part4 = $affected_account_name;
			break;
		case 26:
			$action_ico = 'file-assign';
			$part1 = $owner_user;
			$action_text = __('assigned the file','cftp_admin');
			$part2 = $affected_file_name;
			$part3 = __('to the group:','cftp_admin');
			$part4 = $affected_account_name;
			break;
		case 27:
			$action_ico = 'user-activate';
			$part1 = $owner_user;
			$action_text = __('activated the user','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 28:
			$action_ico = 'user-deactivate';
			$part1 = $owner_user;
			$action_text = __('deactivated the user','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 29:
			$action_ico = 'branding-change';
			$part1 = $owner_user;
			$action_text = __('uploaded a new logo on "Branding"','cftp_admin');
			break;
		case 30:
			$action_ico = 'update';
			$part1 = $owner_user;
			$action_text = __('updated ProjectSend to version','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 31:
			$action_ico = 'logout';
			$part1 = $owner_user;
			$action_text = __('logged out of the system.','cftp_admin');
			break;
		case 32:
			$action_ico = 'file-edit';
			$part1 = $owner_user;
			$action_text = __('(user) edited the file','cftp_admin');
			$part2 = $affected_file_name;
			break;
		case 33:
			$action_ico = 'file-edit';
			$part1 = $owner_user;
			$action_text = __('(client) edited the file','cftp_admin');
			$part2 = $affected_file_name;
			break;
	}
	
	$date = date(TIMEFORMAT_USE,strtotime($timestamp));

	if (!empty($part1)) { $log['1'] = $part1; }
	if (!empty($part2)) { $log['2'] = $part2; }
	if (!empty($part3)) { $log['3'] = $part3; }
	if (!empty($part4)) { $log['4'] = $part4; }
	$log['icon'] = $action_ico;
	$log['timestamp'] = $date;
	$log['text'] = $action_text;
	
	return $log;
}
?>