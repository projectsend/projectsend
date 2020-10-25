<?php
/**
 * Define the common functions that can be accessed from anywhere.
 *
 * @package		ProjectSend
 * @subpackage	Functions
 */

use enshrined\svgSanitize\Sanitizer;

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
	 * This table list is defined on app.php
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

function generateUsername($string, $i = 1) {
    $string = preg_replace('/[^A-Za-z0-9]/', "", $string);
    $username = $string;
    while(isUniqueUsername($username)) {
        $username = $string . $i;
        $i++;
    }
    return $username;
}

function isUniqueUsername($string) {
    global $dbh;
    $statement = $dbh->prepare( "SELECT * FROM " . TABLE_USERS . " WHERE user = :user" );
    $statement->execute(array(':user'	=> $string));
    if ($statement->rowCount() > 0) {
        return false;
    }

    return true;
}

function return_account_type()
{
    if (!defined('CURRENT_USER_LEVEL')) {
        return 'client';
    }
    
    $type = (CURRENT_USER_LEVEL == 0) ? 'client' : 'user';
    return $type;
}


/** Gets a Json file from and url and caches the result */
function getJson($url, $cache_time) {
    $cache_dir = JSON_CACHE_DIR;
    $cacheFile = $cache_dir . DS . md5($url);
    
    if (file_exists($cacheFile)) {
        $fh = fopen($cacheFile, 'r');
        $cacheTime = trim(fgets($fh));

        // if data was cached recently, return cached data
        if ($cacheTime > strtotime($cache_time)) {
            return fread($fh, filesize($cacheFile));
        }

        // else delete cache file
        fclose($fh);
        unlink($cacheFile);
    }

    $json = file_get_contents($url);

    $fh = fopen($cacheFile, 'w');
    fwrite($fh, time() . "\n");
    fwrite($fh, $json);
    fclose($fh);

    return $json;
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
 * Reads the lang folder and scans for .mo files.
 * Returns an array of avaiable languages.
 */
function get_available_languages()
{
    /** Load the language and locales names list */
    require_once ROOT_DIR . '/includes/language.locales.names.php';

	$langs = array();

	$mo_files = glob(ROOT_DIR.'/lang/*.mo');
	foreach ($mo_files as $file) {
		$lang_file	= pathinfo($file, PATHINFO_FILENAME);
        $extension	= pathinfo($file, PATHINFO_EXTENSION);

        if ( array_key_exists( $lang_file, $locales_names ) ) {
            $lang_name = $locales_names[$lang_file];
        }
        else {
            $lang_name = $lang_file;
        }

        $langs[$lang_file] = $lang_name;
	}

	/** Sort alphabetically */
	asort($langs, SORT_STRING);

	return $langs;
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

    if ( $statement->rowCount() > 0 ) {
        while ( $row = $statement->fetch() ) {
            $information = array(
                                'id'				=> html_output($row['id']),
                                'username'			=> html_output($row['user']),
                                'name'				=> html_output($row['name']),
                                'address'			=> html_output($row['address']),
                                'phone'				=> html_output($row['phone']),
                                'email'				=> html_output($row['email']),
                                'notify_upload'		=> html_output($row['notify']),
                                'level'				=> html_output($row['level']),
                                'active'			=> html_output($row['active']),
                                'max_file_size' 	=> html_output($row['max_file_size']),
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
    else {
        return false;
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

    $statement = $dbh->prepare("SELECT id FROM " . TABLE_USERS . " WHERE user=:username");
    $statement->bindParam(':username', $client);
    $statement->execute();
    $statement->setFetchMode(PDO::FETCH_ASSOC);

    while ( $row = $statement->fetch() ) {
        $found_id = html_output($row['id']);
        if ( !empty( $found_id ) ) {
            $information = get_client_by_id($found_id);
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
* Get a user using any of the accepted field names
* 
* @uses get_user_by_id
* @return array
*/
function get_user_by($user_type, $field, $value)
{
    global $dbh;

    $field = (string)$field;
    $field = trim( strip_Tags( htmlentities( strtolower( $field ) ) ) );
    $acceptable_fields = [
        'username',
        'name',
        'email',
    ];

    if ( in_array( $field, $acceptable_fields ) ) {
        $statement = $dbh->prepare("SELECT id FROM " . TABLE_USERS . " WHERE `$field`=:value");
        $statement->bindParam(':value', $value);
        $statement->execute();
        
        $result = $statement->fetchColumn();
        if ( $result ) {
            switch ( $user_type ) {
                case 'user':
                    $user_data = get_user_by_id($result);
                    break;
                case 'client':
                    $user_data = get_client_by_id($result);
            }

            return $user_data;
        }
        else {
            return false;
        }
    }
    else {
        return false;
    }
}

/**
* Get all the user information knowing only the id
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
                            'id'			=> html_output($row['id']),
                            'username'		=> html_output($row['user']),
                            'name'			=> html_output($row['name']),
                            'email'			=> html_output($row['email']),
                            'level'			=> html_output($row['level']),
                            'active'		=> html_output($row['active']),
                            'max_file_size'	=> html_output($row['max_file_size']),
                            'created_date'	=> html_output($row['timestamp']),
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
* Get all the user information knowing only the log in username
*
* @return array
* @uses get_user_by_id
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
            $found_id = html_output($row['id']);
            if ( !empty( $found_id ) ) {
                $information = get_user_by_id($found_id);
                return $information;
            }
            else {
                return false;
            }
        }
    }
    else {
        return false;
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
            'id' => html_output($row['id']),
            'user_id' => html_output($row['user_id']),
            'title'=> html_output($row['filename']),
            'original_url' => html_output($row['original_url']),
            'url' => html_output($row['url']),
            'description' => html_output($row['description']),
            'uploaded_date' => html_output($row['timestamp']),
            'uploaded_by' => html_output($row['uploader']),
            'expires' => html_output($row['expires']),
            'expiry_date' => html_output($row['expiry_date']),
            'public' => html_output($row['public_allow']),
            'public_token' => html_output($row['public_token']),
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
function get_file_by_filename($filename)
{
	global $dbh;
	$statement = $dbh->prepare("SELECT * FROM " . TABLE_FILES . " WHERE url=:filename");
    $statement->execute(
        array(
            ':filename'	=> $filename
        )
    );

    if ( $statement->rowCount() > 0 ) {
        while ( $row = $statement->fetch() ) {
            $found_id = $row['id'];
            if ( !empty( $found_id ) ) {
                $information = get_file_by_id($found_id);
                return $information;
            }
            else {
                return false;
            }
        }
    }

    return false;
}

function get_file_assignations($file_id)
{
    if (empty($file_id)) {
        return false;
    }

    if (!is_numeric($file_id)) {
        return false;
    }

    global $dbh;

    $statement = $dbh->prepare("SELECT * FROM " . TABLE_FILES_RELATIONS . " WHERE file_id = :file_id");
    $statement->bindParam(':file_id', $file_id, PDO::PARAM_INT);
    $statement->execute();
    $statement->setFetchMode(PDO::FETCH_ASSOC);

    $count = $statement->rowCount();

    $return = [
        'clients' => [],
        'groups' => [],
    ];

    if ($count > 0) {
        while ($row = $statement->fetch()) {
            if (!empty($row['client_id'])) {
                $return['clients'][$row['client_id']] = [
                    'hidden' => $row['hidden'],
                ];
            }

            if (!empty($row['group_id'])) {
                $return['groups'][$row['group_id']] = [
                    'hidden' => $row['hidden'],
                ];
            }
        }

        return $return;
    }

    return false;    
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
 * Generate a system text message.
 *
 * Current CSS available message classes:
 * - message_ok
 * - message_error
 * - message_info
 *
 */
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
            break;
		case 'danger':
			break;
		case 'info':
            break;
        case 'warning':
            break;
	}

	$return = '<div class="alert alert-'.$type.'"';
	if ( isset( $div_id ) && $div_id != '' ) {
		$return .= ' id="' . $div_id . '"';
	}

	$return .= '>';

	if (isset($close) && $close == true) {
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
function current_role_in($levels)
{
    if (!is_array($levels)) {
        $levels = array($levels);
    }
    
	if (isset($_SESSION['userlevel']) && (in_array($_SESSION['userlevel'],$levels))) {
		return true;
	}
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

    return $level;
}

/**
 * Wrap print_r with pre tags
 */
function print_array($array)
{
    echo '<pre>';
        print_r($array);
    echo '</pre>';
}

/**
 * Alias for previous function
 */
function pa($array)
{
    print_array($array);
}

/**
 * Prints array and ends execution
 */
function pax($array)
{
    print_array($array);
    exit;
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
 * Allow some html tags for file and group descriptions on htmlentities
 *
 */
function htmlentities_allowed($str, $quoteStyle = ENT_COMPAT, $charset = CHARSET, $doubleEncode = false)
{
    //$description = htmlspecialchars($str, $quoteStyle, $charset, $doubleEncode);
    $description = htmlspecialchars_decode($str, $quoteStyle);
	$allowed_tags = array('i','b','strong','em','p','br','ul','ol','li','u','sup','sub','s');

	$find = array();
	$replace = array();

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

function getFileTypeByMime($full_path)
{
    if (!file_exists($full_path)) {
        return null;
    }

    $mimeType = mime_content_type($full_path);
    return explode('/', $mimeType)[0];
}

function isImage($full_path)
{
    if (getFileTypeByMime($full_path) == 'image') {
        return true;
    }

    return false;
}

function file_is_image($file)
{
    return isImage($file);
}

/**
 * Try to recognize if a file is a valid svg
 */
function file_is_svg( $file )
{
	if ( file_exists( $file ) ) {
        $svg_sanitizer = new Sanitizer();
        $source_file = file_get_contents($file);
        $sanitized_file = $svg_sanitizer->sanitize($source_file);
    }
    else {
        return false;
    }

	return $sanitized_file;
}

/**
 * Make a thumbnail with SimpleImage
 */
function make_thumbnail( $file, $type = 'thumbnail', $width = THUMBS_MAX_WIDTH, $height = THUMBS_MAX_HEIGHT, $quality = THUMBS_QUALITY )
{
    $thumbnail = array();
    
    if ( !file_exists($file) ) {
        $thumbnail_file = 'thumb_unavailable_' . $width . 'x' . $height . '.png';

        $thumbnail['original']['url'] = ASSETS_IMG_URL . '/thumbnail-unavailable.png';
		$thumbnail['thumbnail']['location'] = THUMBNAILS_FILES_DIR . DS . $thumbnail_file;
        $thumbnail['thumbnail']['url'] = THUMBNAILS_FILES_URL . '/' . $thumbnail_file;
        
        $file = ASSETS_IMG_DIR . DS . '/thumbnail-unavailable.png'; // Reset to make thumbnail
    }
    else {
        if ( file_is_image( $file ) ) {
            if (file_is_svg($file)) {
                if (get_option('svg_show_as_thumbnail') == '1') {
                    $file = str_replace(ROOT_DIR, BASE_URI, $file);
                    $thumbnail['original']['url'] = $file;
                    $thumbnail['thumbnail']['location'] = $file;
                    $thumbnail['thumbnail']['url'] = $file;
                }

                return $thumbnail;
            }
            /** Original extension */
            $pathinfo	= pathinfo( $file );
            $filename	= md5( $pathinfo['basename'] );
            $extension	= strtolower( $pathinfo['extension'] );
            $mime_type	= mime_content_type($file);

            $thumbnail_file = 'thumb_' . $filename . '_' . $width . 'x' . $height . '.' . $extension;

            $thumbnail['original']['url'] = $file;
            $thumbnail['thumbnail']['location'] = THUMBNAILS_FILES_DIR . DS . $thumbnail_file;
            $thumbnail['thumbnail']['url'] = THUMBNAILS_FILES_URL . '/' . $thumbnail_file;
        }
    }

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

	return $thumbnail;
}

/**
 * Prepare the branding image file using the database options
 * for the file name and the thumbnails path value.
 */
function generate_logo_url()
{
	$branding = array();
	$branding['exists'] = false;
    // LOGO_FILENAME: filename gotten from the database
    if ( empty( LOGO_FILENAME ) ) {
        $branding['dir'] = ASSETS_IMG_DIR . DS . DEFAULT_LOGO_FILENAME;
        $branding['url'] = ASSETS_IMG_URL . '/' . DEFAULT_LOGO_FILENAME;
    }
    else {
        $branding['dir'] = ADMIN_UPLOADS_DIR . DS . LOGO_FILENAME;
        $branding['url'] = ADMIN_UPLOADS_URI . LOGO_FILENAME;
    }

	if (file_exists( $branding['dir'] )) {
        $branding['exists'] = true;
        
        /* Make thumbnails for raster files */
        if ( file_is_image($branding['dir']) ) {
            $thumbnail = make_thumbnail($branding['dir'], 'proportional', LOGO_MAX_WIDTH, LOGO_MAX_HEIGHT);
		    $branding['thumbnail'] = ( !empty( $thumbnail['thumbnail']['url'] ) ) ? $thumbnail['thumbnail']['url'] : $branding['url'];
            $branding['thumbnail_info'] = $thumbnail;
            $branding['type'] = 'raster';
        }
        elseif ( file_is_svg($branding['dir']) ) {
            $branding['type'] = 'vector';
            $branding['thumbnail'] = $branding['dir']; // no thumbnail, just return the original file
        }

        $branding['ext'] = pathinfo($branding['dir'], PATHINFO_EXTENSION);
    }

	return $branding;
}

/**
 * Returns the corresponding layout to show an image tag or the svg contents
 * of the current uploaded logo file.
 */
function get_branding_layout($return_thumbnail = false)
{
    $layout = '<div class="row">
                <div class="col-xs-12 branding_unlogged">
                    %LOGO%
                </div>
                </div>';

    $branding = generate_logo_url();

	if ($branding['exists'] === true) {
        $branding_image = ( $return_thumbnail === true ) ? $branding['thumbnail'] : $branding['url'];
	}
	else {
		$branding_image = ASSETS_IMG_URL . DEFAULT_LOGO_FILENAME;
    }
    
    if ($branding['type'] == 'raster') {
        $replace = '<img src="' . $branding_image . '" alt="' . html_output(THIS_INSTALL_TITLE) . '" />';
    }
    elseif ($branding['type'] == 'vector') {
        $replace = file_is_svg($branding['dir']);
    }

    $layout = str_replace('%LOGO%', $replace, $layout);

	return $layout;
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
	$favicon_location = BASE_URI . 'assets/img/favicon/';
	echo '<link rel="shortcut icon" type="image/x-icon" href="' . BASE_URI . 'favicon.ico" />' . "\n";
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

		$logged_type = CURRENT_USER_LEVEL == '0' ? 'client' : 'admin';

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

function add_page_id($id)
{
    $return = '';

    if (!empty($id)) {
        $return .= 'data-page-id="'.$id.'"';
    }

    return $return;
}

/**
 * Creates a standarized download link. Used on
 * each template.
 */
function make_download_link($file_info)
{
	$download_link = BASE_URI.'process.php?do=download&amp;id='.$file_info['id'];

    return $download_link;
}

/**
 * Convert to array only if it's not one already
 */
function to_array_if_not($data)
{
    if (!is_array($data)) {
        $value = array($data);
    }
    else {
        $value = $data;
    }

    return $value;
}

function generateSafeFilename($filename)
{
    $original_filename = pathinfo(trim($filename));
    $filename = $original_filename['filename'];
    $extension = $original_filename['extension'];

    // Replace accent characters, forien languages
    $search = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ'); 
    $replace = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o'); 
    $filename = str_replace($search, $replace, $filename);

    // Replace common characters
    $search = array('&', '£', '$'); 
    $replace = array('and', 'pounds', 'dollars'); 
    $filename= str_replace($search, $replace, $filename);

    // Remove - for spaces and union characters
    $search = array(' ', '&', '\r\n', '\n', '+', ',', '//');
    $replace = '-';
    $filename = str_replace($search, $replace, $filename);

    // Delete and replace rest of special chars
    $search = array('/[^a-z0-9\-<>_]/', '/[\-]+/', '/<[^>]*>/', '/[  *]/');
    $replace = array('', '-', '', '-');
    $filename = preg_replace($search, $replace, $filename);

    return strtolower($filename.'.'.$extension);
}

/**
 * Simple file upload. Used on normal file fields, eg: logo on branding page
 */
function option_file_upload( $file, $validate_ext = '', $option = '', $action = '' )
{
	global $dbh;
	$return = array();
	$continue = true;

	/** Validate file extensions */
	if ( !empty( $validate_ext ) ) {
		switch ( $validate_ext ) {
			case 'image':
				$validate_types = "/^\.(jpg|jpeg|gif|png){1}$/i";
				break;
			default:
				break;
		}
	}

	if ( is_uploaded_file( $file['tmp_name'] ) ) {

        $safe_filename = generateSafeFilename($file['name']);
		/**
		 * Check the file type for allowed extensions.
		 */
		if ( !empty( $validate_types) && !preg_match( $validate_types, strrchr( $safe_filename, '.' ) ) ) {
			$continue = false;
        }

		if ( $continue ) {
			/**
			 * Move the file to the destination defined on app.php. If ok, add the
			 * new file name to the database.
			 */
			if ( move_uploaded_file( $file['tmp_name'], ADMIN_UPLOADS_DIR . DS . $safe_filename ) ) {
				if ( !empty( $option ) ) {
					$query = "UPDATE " . TABLE_OPTIONS . " SET value=:value WHERE name='" . $option . "'";
					$sql = $dbh->prepare( $query );
					$sql->execute(
								array(
									':value'	=> $safe_filename
								)
							);
				}

				$return['status'] = '1';

				/** Record the action log */
				if ( !empty( $action ) ) {
					$logger = new \ProjectSend\Classes\ActionsLog();
					$log_action_args = array(
											'action' => $action,
											'owner_id' => CURRENT_USER_ID
										);
					$new_record_action = $logger->addEntry($log_action_args);
				}
			}
			else {
				$return['status'] = '2';
			}
		}
		else {
			$return['status'] = '3';
		}
	}
	else {
		$return['status'] = '4';
	}

	return $return;
}

function format_date($date)
{
    if (!$date) {
        return false;
    }

    $formatted = date(get_option('timeformat'), strtotime($date));

    return $formatted;
}

function format_time($date)
{
    if (!$date) {
        return false;
    }

    $formatted = date('h:i:s', strtotime($date));

    return $formatted;
}

function extensionIsAllowed($extension) {
    $allowed_extensions = explode(',', get_option('allowed_file_types') );
    if (in_array($extension, $allowed_extensions)) {
        return true;
    }

    return false;
}

function fileEditorGetAllClients()
{
	global $dbh;

    /** Fill the users array that will be used on the notifications process */
    //$users = [];
    $clients = [];

    $statement = $dbh->prepare("SELECT id, name, level FROM " . TABLE_USERS . " ORDER BY name ASC");
    $statement->execute();
    $statement->setFetchMode(PDO::FETCH_ASSOC);
    while( $row = $statement->fetch() ) {
        //$users[$row["id"]] = $row["name"];
        if ($row["level"] == '0') {
            $clients[$row["id"]] = $row["name"];
        }
    }

    return $clients;
}

function fileEditorGetAllGroups()
{
	global $dbh;

    /** Fill the groups array that will be used on the form */
    $groups = [];
    $statement = $dbh->prepare("SELECT id, name FROM " . TABLE_GROUPS . " ORDER BY name ASC");
    $statement->execute();
    $statement->setFetchMode(PDO::FETCH_ASSOC);
    while( $row = $statement->fetch() ) {
        $groups[$row["id"]] = $row["name"];
    }

    return $groups;
}

function userCanEditFile($user_id = null, $file_id = null)
{
    if (empty($user_id) or empty($file_id)) {
        return false;
    }

    $user = get_user_by_id($user_id);

    if ($user['level'] != 0) {
        return true;
    } else {
        $file = get_file_by_id($file_id);

        // Pre-update when column didn't exist
        if ($file['user_id'] == null) {
            if ($user['username'] == $file['uploaded_by']) {
                return true;
            }    
        }
        if ($user['id'] == $file['user_id']) {
            return true;
        }
    }

    return false;
}

function recordNewDownload($user_id = CURRENT_USER_ID, $file_id = null)
{
    global $dbh;
    if (empty($file_id)) {
        return false;
    }

    if (!is_numeric($user_id) || !is_numeric($file_id)) {
        return false;
    }

    // Anonymous download
    if ($user_id == 0) {
        $statement = $dbh->prepare("INSERT INTO " . TABLE_DOWNLOADS . " (file_id, remote_ip, remote_host, anonymous) VALUES (:file_id, :remote_ip, :remote_host, :anonymous)");
        $statement->bindParam(':file_id', $file_id, PDO::PARAM_INT);
        $statement->bindParam(':remote_ip', get_client_ip());
        $statement->bindParam(':remote_host', $_SERVER['REMOTE_HOST']);
        $statement->bindValue(':anonymous', 1, PDO::PARAM_INT);
        $statement->execute();
    } else {
        $statement = $dbh->prepare("INSERT INTO " . TABLE_DOWNLOADS . " (user_id , file_id, remote_ip, remote_host) VALUES (:user_id, :file_id, :remote_ip, :remote_host)");
        $statement->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $statement->bindParam(':file_id', $file_id, PDO::PARAM_INT);
        $statement->bindParam(':remote_ip', get_client_ip());
        $statement->bindParam(':remote_host', $_SERVER['REMOTE_HOST']);
        $statement->execute();
    }

    if (!empty($statement)) {
        return true;
    }

    return false;
}

function userCanDownloadFile($user_id = CURRENT_USER_ID, $file_id = null)
{
    global $dbh;
    if (empty($file_id)) {
        return false;
    }

    if (!is_numeric($user_id) || !is_numeric($file_id)) {
        return false;
    }


    if (CURRENT_USER_LEVEL != 0) {
        return true;
    }

    // Get the file
    $file = new \ProjectSend\Classes\Files();
    $file->get($file_id);

    // Get groups
    $get_groups = new \ProjectSend\Classes\MembersActions();
    $found_groups = $get_groups->client_get_groups([
        'client_id' => $user_id,
        'return' => 'list',
    ]);

    if ($file->user_id == $user_id) {
        return true;
    }
    else {
        if ($file->expires == '0' || $file->expired == false) {
            $statement = $dbh->prepare("SELECT * FROM " . TABLE_FILES_RELATIONS . " WHERE (client_id = :client_id OR FIND_IN_SET(group_id, :groups)) AND file_id = :file_id AND hidden = '0'");
            $statement->bindValue(':client_id', $user_id, PDO::PARAM_INT);
            $statement->bindParam(':groups', $found_groups);
            $statement->bindParam(':file_id', $file->id, PDO::PARAM_INT);
            $statement->execute();
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            $row = $statement->fetch();

            if ($row) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Renders an action recorded on the log.
 */
function render_log_action($params)
{
	$action = $params['action'];
	$timestamp = $params['timestamp'];
	$owner_id = $params['owner_id'];
	$owner_user = html_output($params['owner_user']);
	$affected_file = $params['affected_file'];
	$affected_file_name = $params['affected_file_name'];
	$affected_account = $params['affected_account'];
	$affected_account_name = html_output($params['affected_account_name']);

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
        case 40:
			$action_ico = 'file-hidden';
			$part1 = $owner_user;
			$action_text = __('marked as hidden for everyone the file','cftp_admin');
			$part2 = $affected_file_name;
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
		case 34:
			$action_ico = 'category-add';
			$part1 = $owner_user;
			$action_text = __('created the category','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 35:
			$action_ico = 'category-edit';
			$part1 = $owner_user;
			$action_text = __('edited the category','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 36:
			$action_ico = 'category-delete';
			$part1 = $owner_user;
			$action_text = __('deleted the category','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 37:
			$action_ico = 'download-anonymous';
			$part1 = __('An anonymous user','cftp_admin');
			$action_text = __('downloaded the file','cftp_admin');
			$part2 = $affected_file_name;
			break;
		case 38:
			$action_ico = 'client-request-processed';
			$part1 = $owner_user;
			$action_text = __('processed an account request for','cftp_admin');
			$part2 = $affected_account_name;
			break;
		case 39:
			$action_ico = 'client-request-processed';
			$part1 = $owner_user;
			$action_text = __('processed group memberships requests for','cftp_admin');
			$part2 = $affected_account_name;
			break;
	}

    $date = format_date($timestamp);

	if (!empty($part1)) { $log['1'] = $part1; }
	if (!empty($part2)) { $log['2'] = $part2; }
	if (!empty($part3)) { $log['3'] = $part3; }
	if (!empty($part4)) { $log['4'] = $part4; }
	$log['icon'] = $action_ico;
	$log['timestamp'] = $date;
	$log['text'] = $action_text;

	return $log;
}
// Function to get the client ip address
function get_client_ip() {
    $ipaddress = '';
    if ($_SERVER['HTTP_CLIENT_IP'])
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if($_SERVER['HTTP_X_FORWARDED_FOR'])
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if($_SERVER['HTTP_X_FORWARDED'])
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if($_SERVER['HTTP_FORWARDED_FOR'])
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if($_SERVER['HTTP_FORWARDED'])
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if($_SERVER['REMOTE_ADDR'])
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';

    return $ipaddress;
}
