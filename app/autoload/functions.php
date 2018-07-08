<?php
/**
 * Define the common functions that can be accessed from anywhere.
 *
 * @package		ProjectSend
 * @subpackage	Functions
 */

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

    // LOGO_FILENAME: filename gotten from the database
    if ( empty( LOGO_FILENAME ) ) {
        $branding['dir'] = ASSETS_IMG_DIR . DS . DEFAULT_LOGO_FILENAME;
        $branding['url'] = ASSETS_IMG_URI . DEFAULT_LOGO_FILENAME;
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
    $layout = '';
    $branding = generate_logo_url();

	if ($branding['exists'] === true) {
        $branding_image = ( $return_thumbnail === true ) ? $branding['thumbnail'] : $branding['url'];
	}
	else {
		$branding_image = ASSETS_IMG_URI . DEFAULT_LOGO_FILENAME;
    }
    
    if ($branding['type'] == 'raster') {
        $layout = '<img src="' . $branding_image . '" alt="' . html_output(THIS_INSTALL_TITLE) . '" />';
    }
    elseif ($branding['type'] == 'vector') {
        $layout = file_is_svg($branding['dir']);
    }

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
 * print_r a variable with a more human readable format
 */
function print_array( $data = array() )
{
	echo '<pre>';
	print_r($data);
	echo '</pre>';
}