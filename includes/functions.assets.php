<?php
/**
 * Contains the functions used to load css and js assets
 *
 * @package		ProjectSend
 */

/**
 * CSS files
 * Used on header to print the CSS files
 */
function load_css_files() {
	global $load_css_files;
	global $hooks;
	$hooks->do_action('render_css_files');

	if ( !empty( $load_css_files ) ) {
		foreach ( $load_css_files as $file ) {
?>
			<link rel="stylesheet" media="all" type="text/css" href="<?php echo $file; ?>" />
<?php
		}
	}
}

/**
 * JS files
 * Used before the </body> tag to print the JS files
 */
function load_js_files() {
	global $load_compat_js_files;
	global $load_js_files;
	global $hooks;
	$hooks->do_action('render_js_files');

	if ( !empty( $load_js_files ) ) {
		foreach ( $load_js_files as $file ) {
?>
			<script src="<?php echo $file; ?>"></script>
<?php
		}
	}

	if ( !empty( $load_compat_js_files ) ) {
		foreach ( $load_compat_js_files as $index => $info ) {
?>
			<!--[if <?php echo $info['cond']; ?>]><script language="javascript" type="text/javascript" src="<?php echo $info['file']; ?>"></script><![endif]-->
<?php
		}
	}
}

	/**
	 * Include jQuery in <head>
	 */
	function require_jquery() {
		global $jquery_location, $migrate_location;
?>
		<script src="<?php echo $jquery_location; ?>"></script>
		<script src="<?php echo $migrate_location; ?>"></script>
<?php
	}