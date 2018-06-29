<?php
/**
 * Define the common functions that can be accessed from anywhere.
 *
 * @package		ProjectSend
 * @subpackage	Functions
 */

/**
 * Check if ProjectSend is installed by trying to find the main users table.
 * If it is missing, the installation is invalid.
 */
function is_projectsend_installed()
{
	$tables_need = array(
						TABLE_USERS
					);

	$tables_missing = 0;
	/**
	 * This table list is defined on sys.vars.php
	 */
	foreach ($tables_need as $table) {
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

/**
 * To successfully add the orderby and order parameters to a query,
 * check if the column exists on the table and validate that order
 * is either ASC or DESC.
 * Defaults to ORDER BY: id, ORDER: DESC
 */
function sql_add_order( $table, $column = 'id', $initial_order = 'ASC' )
{
	global $dbh;
	$allowed_custom_sort_columns = array( 'download_count' );

	$columns_query	= $dbh->query('SELECT * FROM ' . $table . ' LIMIT 1');
	if ( $columns_query->rowCount() > 0 ) {
		$columns_keys	= array_keys($columns_query->fetch(PDO::FETCH_ASSOC));
		$columns_keys	= array_merge( $columns_keys, $allowed_custom_sort_columns );
		$orderby		= ( isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], $columns_keys ) ) ? $_GET['orderby'] : $column;

		$order		= ( isset( $_GET['order'] ) ) ? strtoupper($_GET['order']) : $initial_order;
		$order      = (preg_match("/^(DESC|ASC)$/",$order)) ? $order : $initial_order;

		return " ORDER BY $orderby $order";
	}
	else {
		return false;
	}
}

function generate_password()
{
	/**
	 * @uses random_compat library, a polyfill for PHP 7's random_bytes();
	 * @link: https://github.com/paragonie/random_compat
	 */
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
 * Get the total count of downloads grouped by file
 * Data returned:
 * - Count anonymous downloads (Public downloads)
 * - Unique logged in clients downloads
 * - Total count
 */
function generate_downloads_count( $id = null )
{
	global $dbh;

	$data = array();

	$sql = "SELECT file_id, COUNT(*) as downloads, SUM( ISNULL(user_id) ) AS anonymous_users, COUNT(DISTINCT user_id) as unique_clients FROM " . TABLE_DOWNLOADS;
	if ( !empty( $id ) ) {
		$sql .= ' WHERE file_id = :id';
	}

	$sql .=  " GROUP BY file_id";

	$statement	= $dbh->prepare( $sql );

	if ( !empty( $id ) ) {
		$statement->bindValue(':id', $id, PDO::PARAM_INT);
	}

	$statement->execute();

	$statement->setFetchMode(PDO::FETCH_ASSOC);

	while ( $row = $statement->fetch() ) {
		$data[$row['file_id']] = array(
									'file_id'			=> html_output($row['file_id']),
									'total'				=> html_output($row['downloads']),
									'unique_clients'	=> html_output($row['unique_clients']),
									'anonymous_users'	=> html_output($row['anonymous_users']),
								);
	}

	return $data;
}

/**
 * Check if a table exists in the current database.
 *
 * @param string $table Table to search for.
 * @return bool TRUE if table exists, FALSE if no table found.
 * by esbite on http://stackoverflow.com/questions/1717495/check-if-a-database-table-exists-using-php-pdo
 */
function tableExists($table)
{
	global $dbh;

	if ( !empty ( $dbh ) ) {
	    try {
	        $result = $dbh->prepare("SELECT 1 FROM $table LIMIT 1");
			$result->execute();
	    } catch (Exception $e) {
	        return false;
	    }
	}

   // Result is either boolean FALSE (no table found) or PDOStatement Object (table found)
   return $result !== false;
}

/**
 * Check if a file id exists on the database.
 * Used on the download information page.
 *
 * @return bool
 */
function download_information_exists($id)
{
	global $dbh;
	$statement = $dbh->prepare("SELECT id FROM " . TABLE_DOWNLOADS . " WHERE file_id = :id");
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
							'id'					=> html_output($row['id']),
							'username'			=> html_output($row['user']),
							'name'				=> html_output($row['name']),
							'address'			=> html_output($row['address']),
							'phone'				=> html_output($row['phone']),
							'email'				=> html_output($row['email']),
							'notify'				=> html_output($row['notify']),
							'level'				=> html_output($row['level']),
							'active'				=> html_output($row['active']),
							'max_file_size'	=> html_output($row['max_file_size']),
							'contact'			=> html_output($row['contact']),
							'created_date'		=> html_output($row['timestamp']),
							'created_by'		=> html_output($row['created_by'])
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
							'id'					=> html_output($row['id']),
							'name'				=> html_output($row['name']),
							'username'			=> html_output($row['user']),
							'address'			=> html_output($row['address']),
							'phone'				=> html_output($row['phone']),
							'email'				=> html_output($row['email']),
							'notify'				=> html_output($row['notify']),
							'level'				=> html_output($row['level']),
							'active'				=> html_output($row['active']),
							'max_file_size'	=> html_output($row['max_file_size']),
							'contact'			=> html_output($row['contact']),
							'created_date'		=> html_output($row['timestamp']),
							'created_by'		=> html_output($row['created_by'])
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
		$return_id = html_output($row['id']);
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
			return html_output($row['email']);
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
								'id'					=> html_output($row['id']),
								'username'			=> html_output($row['user']),
								'name'				=> html_output($row['name']),
								'email'				=> html_output($row['email']),
								'level'				=> html_output($row['level']),
								'active'				=> html_output($row['active']),
								'max_file_size'	=> html_output($row['max_file_size']),
								'created_date'		=> html_output($row['timestamp'])
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
							'id'					=> html_output($row['id']),
							'username'			=> html_output($row['user']),
							'name'				=> html_output($row['name']),
							'email'				=> html_output($row['email']),
							'level'				=> html_output($row['level']),
							'max_file_size'	=> html_output($row['max_file_size']),
							'created_date'		=> html_output($row['timestamp']),
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
 * Get all the file information knowing only the id
 * Used on the Download information page.
 *
 * @return array
 */
function get_file_by_id($id)
{
	global $dbh;
	$statement = $dbh->prepare("SELECT * FROM " . TABLE_FILES . " WHERE id=:id");
	$statement->bindParam(':id', $id, PDO::PARAM_INT);
	$statement->execute();
	$statement->setFetchMode(PDO::FETCH_ASSOC);

	while ( $row = $statement->fetch() ) {
		$information = array(
							'id'				=> html_output($row['id']),
							'title'			=> html_output($row['filename']),
							'original_url'	=> html_output($row['original_url']),
							'url'				=> html_output($row['url']),
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
 * Get all the group information knowing only the id
 *
 * @return array
 */
function get_group_by_id($id)
{
	global $dbh;
	$statement = $dbh->prepare("SELECT * FROM " . TABLE_GROUPS . " WHERE id=:id");
	$statement->bindParam(':id', $id, PDO::PARAM_INT);
	$statement->execute();
	$statement->setFetchMode(PDO::FETCH_ASSOC);

	while ( $row = $statement->fetch() ) {
		$information = array(
							'id'				=> html_output($row['id']),
							'created_by'	=> html_output($row['created_by']),
							'created_date'	=> html_output($row['timestamp']),
							'name'			=> html_output($row['name']),
							'description'	=> html_output($row['description']),
							'public'			=> html_output($row['public']),
							'public_token'	=> html_output($row['public_token']),
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
			<div class="footer_links">
				<?php
					global $footer_links;
					$footer_links = array();

					/** Privacy policy */
					if ( defined('PAGE_POLICY_ENABLE') && PAGE_POLICY_ENABLE == '1' ) {
						$footer_links[] = array(
							'href'	=> BASE_URI . 'policy.php',
							'text'	=> PAGE_POLICY_TITLE ? PAGE_POLICY_TITLE : __('Privacy policy', 'cftp_admin'),
							'target'	=> '_self',
						);
					}

					/** Get links added from the custom functions file */
					global $hooks;
					$hooks->do_action('footer_links');

					/** Render the link */
					if ( !empty( $footer_links ) ) {
				?>
						<ul>
							<?php foreach ( $footer_links as $link ) { ?>
								<li>
									<a href="<?php echo $link['href']; ?>" target="<?php echo $link['target']; ?>"><?php echo $link['text']; ?></a>
								</li>
							<?php } ?>
						</ul>
				<?php
					}
				?>
			</div>
			<div class="clear clearfix"></div>

			<?php
				if ( defined('FOOTER_CUSTOM_ENABLE') && FOOTER_CUSTOM_ENABLE == '1' ) {
					echo strip_tags(FOOTER_CUSTOM_CONTENT, '<br><span><a><strong><em><b><i><u><s>');
				}
				else {
					_e('Provided by', 'cftp_admin'); ?> <a href="<?php echo SYSTEM_URI; ?>" target="_blank"><?php echo SYSTEM_NAME; ?></a> <?php if ($logged == true) { _e('version', 'cftp_admin'); echo ' ' . CURRENT_VERSION; } ?> - <?php _e('Free software', 'cftp_admin');
				}
			?>
		</div>
	</footer>
<?php
}

/**
 * function render_json_variables
 * 
 * Adds a CDATA block with variables that are used on the main JS file
 * URLs. text strings, etc.
 */
function render_json_variables()
{
	global $json_strings;
    $output = json_encode( $json_strings );
?>
<script type="text/javascript">
    /*<![CDATA[*/
        var json_strings = <?php echo $output; ?>;
    /*]]>*/
</script>
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
	$msg = '<strong>' . __('Important:','cftp_admin') . '</strong> ' . __('There are no clients or groups at the moment. You can still upload files and assign them later.','cftp_admin');
	echo system_message('warning', $msg);
}


/**
 * Generate a system text message using Bootstrap's alert box.
 */
function system_message( $type, $message, $div_id = '' )
{
    if ( empty( $type ) ) {
        $type = 'success';
    }

	switch ($type) {
		case 'success':
		case 'danger':
			$close = true;
			break;
		case 'info':
        case 'warning':
            $close = false;
            break;
	}

	$return = '<div class="alert alert-'.$type.'"';
	if ( isset( $div_id ) && $div_id != '' ) {
		$return .= ' id="' . $div_id . '"';
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
 * Wrapper for htmlentities with default options
 *
 */
function html_output($str, $flags = ENT_QUOTES, $encoding = CHARSET, $double_encode = false)
{
	return htmlentities($str, $flags, $encoding, $double_encode);
}

/**
 * Allow some html tags for file descriptions on htmlentities
 *
 */
function htmlentities_allowed($str, $quoteStyle = ENT_COMPAT, $charset = CHARSET, $doubleEncode = false)
{
	$description = htmlentities($str, $quoteStyle, $charset, $doubleEncode);
	$allowed_tags = array('i','b','strong','em','p','br','ul','ol','li','u','sup','sub','s');

	$find = array();
	$replace = array();

	$description = str_replace('&amp;', '&', $description);

	foreach ( $allowed_tags as $tag ) {
		/** Opening tags */
		$find[] = '&lt;' . $tag . '&gt;';
		$replace[] = '<' . $tag . '>';
		/** Closing tags */
		$find[] = '&lt;/' . $tag . '&gt;';
		$replace[] = '</' . $tag . '>';
	}

	$description = str_replace($find, $replace, $description);
	return $description;
}


/**
 * Solution by Philippe Flipflip. Fixes an error that would not convert special
 * characters when saving to the database.
 */
function encode_html($str) {
	$str = htmlentities($str, ENT_QUOTES, $encoding=CHARSET);
	$str = nl2br($str);
	//$str = addslashes($str);
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
	/*
	** Using $_SERVER["HTTP_HOST"] now.
	** Fixing problems wth the old solution: $_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"] when using a reverse proxy.
	** HTTP_HOST already includes port number (if non-standard), no specific handling of port number necessary.
	*/
	$pageURL .= $_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];

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
		$formatted = (ctype_digit($file))? $file . ' Byte' :  ' ? ' ;
	} elseif ($file < 1048576) {
		$formatted = round($file / 1024, 2) . ' KB';
	} elseif ($file < 1073741824) {
		$formatted = round($file / 1048576, 2) . ' MB';
	} elseif ($file < 1099511627776) {
		$formatted = round($file / 1073741824, 2) . ' GB';
	} elseif ($file < 1125899906842624) {
		$formatted = round($file / 1099511627776, 2) . ' TB';
	} elseif ($file < 1152921504606846976) {
		$formatted = round($file / 1125899906842624, 2) . ' PB';
	} elseif ($file < 1180591620717411303424) {
		$formatted = round($file / 1152921504606846976, 2) . ' EB';
	} elseif ($file < 1208925819614629174706176) {
		$formatted = round($file / 1180591620717411303424, 2) . ' ZB';
	} else {
		$formatted = round($file / 1208925819614629174706176, 2) . ' YB';
	}

	return $formatted;
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
	        $ff = trim(exec("for %F in (\"" . escapeshellarg($file) . "\") do @echo %~zF"));
		}
    }
	elseif (PHP_OS == 'Darwin') {
		$ff = trim(shell_exec("stat -L -f %z " . escapeshellarg($file)));
    }
	elseif ((PHP_OS == 'Linux') || (PHP_OS == 'FreeBSD') || (PHP_OS == 'Unix') || (PHP_OS == 'SunOS')) {
		$ff = trim(shell_exec("stat -L -c%s " . escapeshellarg($file)));
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
	if ( file_exists( $filename ) ) {
		chmod($filename, 0777);
		unlink($filename);
	}
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
 * Prepare the branding image file using the database options
 * for the file name and the thumbnails path value.
 */
function generate_logo_url()
{
	$branding = array();
	$branding['exists'] = false;

	$logo_filename = LOGO_FILENAME;
	if ( empty( $logo_filename ) ) {
		$branding['filename'] = 'assets/img/projectsend-logo.png';
	}
	else {
		$branding['filename'] = 'assets/img/custom/logo/'.LOGO_FILENAME;
	}

	$result_dir = ROOT_DIR . '/' . $branding['filename'];

	if (file_exists( $result_dir )) {
		$branding['exists'] = true;
		$branding['url'] = BASE_URI.$branding['filename'];
		$branding['dir'] = $result_dir;

		$thumbnail = make_thumbnail($result_dir, 'proportional', LOGO_MAX_WIDTH, LOGO_MAX_HEIGHT);
		$branding['thumbnail'] = ( !empty( $thumbnail['thumbnail']['url'] ) ) ? $thumbnail['thumbnail']['url'] : $branding['url'];
		$branding['thumbnail_info'] = $thumbnail;
	}

	return $branding;
}


/**
 * Returns the full layout with the branding image.
 * Used on the unlogged header file.
 */
function generate_branding_layout()
{
	$branding	= generate_logo_url();
	$layout		= '';

	if ($branding['exists'] === true) {
		$branding_image = $branding['url'];
	}
	else {
		$branding_image = ASSETS_IMG_URI . 'projectsend-logo.png';
	}

	$layout = '<div class="row">
					<div class="col-xs-12 branding_unlogged">
						<img src="' . $branding_image . '" alt="' . html_output(THIS_INSTALL_TITLE) . '" />
					</div>
				</div>';

	return $layout;
}

/**
 * Try to recognize if a file is an image
 *
 * @todo Check the mime type also
 */
function file_is_image( $file )
{
	$is_image = false;
	$pathinfo = pathinfo( $file );
	$extension = strtolower( $pathinfo['extension'] );

	if ( file_exists( $file ) ) {
		/** Check the extension */
		$image_extensions = array('jpg', 'jpeg', 'jpe', 'png', 'gif');
		if ( in_array( $extension, $image_extensions ) ) {
			$is_image = true;
		}
	}

	return $is_image;
}

/**
 * Make a thumbnail with SimpleImage
 */
function make_thumbnail( $file, $type = 'thumbnail', $width = THUMBS_MAX_WIDTH, $height = THUMBS_MAX_HEIGHT, $quality = THUMBS_QUALITY )
{
	$thumbnail = array();

	if ( file_is_image( $file ) ) {
		/** Original extension */
		$pathinfo	= pathinfo( $file );
		$filename	= md5( $pathinfo['basename'] );
		$extension	= strtolower( $pathinfo['extension'] );
		$mime_type	= mime_content_type($file);

		$thumbnail_file = 'thumb_' . $filename . '_' . $width . 'x' . $height . '.' . $extension;

		$thumbnail['original']['url'] = $file;
		$thumbnail['thumbnail']['location'] = THUMBNAILS_FILES_DIR . '/' . $thumbnail_file;
		$thumbnail['thumbnail']['url'] = THUMBNAILS_FILES_URL . '/' . $thumbnail_file;

		if ( !file_exists( $thumbnail['thumbnail']['location'] ) ) {
			try {
				$image = new \claviska\SimpleImage();
				$image
					->fromFile($file)
					->autoOrient();

				switch ( $type ) {
					case 'proportional':
						$method = 'bestFit';
						break;
					case 'thumbnail':
					default:
						$method = 'thumbnail';
						break;
				}

				$image->$method($width, $height);

				$image
					->toFile($thumbnail['thumbnail']['location'], $mime_type, $quality);

			} catch(Exception $err) {
				$thumbnail['error'] = $err->getMessage();
			}
		}
	}

	return $thumbnail;
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
 * Add a noindex to the header
 */
function meta_noindex()
{
	if ( defined('PRIVACY_NOINDEX_SITE') ) {
		if ( PRIVACY_NOINDEX_SITE == 1 ) {
			echo '<meta name="robots" content="noindex">';
		}
	}
}

/**
 * Favicon meta tags
 */
function meta_favicon()
{
	$favicon_location = ASSETS_IMG_URI . 'favicon/';
	echo '<link rel="shortcut icon" type="image/x-icon" href="' . $favicon_location . 'favicon.ico" />' . "\n";
	echo '<link rel="icon" type="image/png" href="' . $favicon_location . 'favicon-32.png" sizes="32x32">' . "\n";
	echo '<link rel="apple-touch-icon" href="' . $favicon_location . 'favicon-152.png" sizes="152x152">' . "\n";
}


/**
 * If password rules are set, show a message
 */
function password_notes()
{
    $pass_notes_output = '';
    global $json_strings;

	$rules_active	= array();
	$rules			= array(
							'lower'		=> array(
												'value'	=> PASS_REQUIRE_UPPER,
												'text'	=> $json_strings['validation']['req_upper'],
											),
							'upper'		=> array(
												'value'	=> PASS_REQUIRE_LOWER,
												'text'	=> $json_strings['validation']['req_lower'],
											),
							'number'	=> array(
												'value'	=> PASS_REQUIRE_NUMBER,
												'text'	=> $json_strings['validation']['req_number'],
											),
							'special'	=> array(
												'value'	=> PASS_REQUIRE_SPECIAL,
												'text'	=> $json_strings['validation']['req_special'],
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
 * Adds default and custom css classes to the body.
 */
function add_body_class( $custom = '' )
{
	/** Remove query string */
	$current_url = strtok( $_SERVER['REQUEST_URI'], '?' );
	$classes = array('body');

	$pathinfo = pathinfo( $current_url );

	if ( !empty( $pathinfo['extension'] ) ) {
		$classes = array(
						strpos( $pathinfo['filename'], "?" ),
						str_replace('.', '-', $pathinfo['filename'] ),
					);
	}

	if ( check_for_session( false ) ) {
		$classes[] = 'logged-in';

		global $client_info;
		$logged_type = $client_info['level'] == '0' ? 'client' : 'admin';

		$classes[] = 'logged-as-' . $logged_type;
	}

	if ( !empty( $custom ) && is_array( $custom ) ) {
		$classes = array_merge( $classes, $custom );
	}

	if ( !in_array('template-default', $classes ) ) {
		$classes[] = 'backend';
	}

	$classes = array_filter( array_unique( $classes ) );

	$render = 'class="' . implode(' ', $classes) . '"';
	return $render;
}


/**
 * Creates a standarized download link. Used on
 * each template.
 */
function make_download_link($file_info)
{
	global $client_info;
	$download_link = BASE_URI.'process.php?do=download&amp;id='.$file_info['id'];

	return $download_link;
}

/**
 * print_r a variable with a more human readable format
 */
function print_array( $data = array() )
{
	echo '<pre>';
	print_r($data);
	echo '</pre>';
}