<?php
/**
 * Loads the required css and js files.
 *
 * @package ProjectSend
 */
global $load_css_files;
global $load_js_files;
global $load_compat_js_files;

$load_css_files			= array();
$load_js_files			= array();
$load_compat_js_files	= array();

/** Add the base files that every page will need, regardless of type */

/** JS */
$load_js_files[]	= 'https://www.google.com/recaptcha/api.js';
$load_js_files[]	= ASSETS_JS_URL . '/assets.js';
$load_js_files[]	= ASSETS_JS_URL . '/app.js';

/** CSS */

/** Fonts*/
$load_css_files[]	= 'https://fonts.googleapis.com/css?family=Open+Sans:400,700,300';

/**
 * Optional scripts
 */
/**
 * Load a plupload translation file, if the ProjectSend language
 * on sys.config.php is set to anything other than "en", and the
 * corresponding plupload file exists.
 */
if ( LOADED_LANG != 'en' ) {
    $plupload_lang_file = 'plupload/js/i18n/'.LOADED_LANG.'.js';
    if ( file_exists( ASSETS_LIB_DIR . DS . $plupload_lang_file ) ) {
        $load_js_files[] = ASSETS_LIB_URL . '/' . $plupload_lang_file;
    }
}

// $load_compat_js_files[]	= array(
//     'file'	=> ASSETS_LIB_URL . '/flot/excanvas.js',
//     'cond'	=> 'lt IE 9',
// );

$load_css_files[]	= ASSETS_CSS_URL . '/assets.css';
$load_css_files[]	= ASSETS_CSS_URL . '/main.css';

/**
 * Load a different css file when called from the default template.
 */
if ( isset( $this_template_css ) ) {
	$load_css_files[]	= $this_template_css;
}

/**
 * Custom CSS styles.
 * Possible locations: css/custom.css | assets/custom/custom.css
 */
$custom_css_locations = [ 'css/custom.css', 'assets/custom/custom.css' ];
foreach ( $custom_css_locations as $css_file ) {
	if ( file_exists ( ROOT_DIR . DS . $css_file ) ) {
		$load_css_files[]	= BASE_URI . $css_file;
	}
}


/**
 * Used on header to print the CSS files
 */
function load_css_files() {
	global $load_css_files;

	if ( !empty( $load_css_files ) ) {
		foreach ( $load_css_files as $file ) {
?>
			<link rel="stylesheet" media="all" type="text/css" href="<?php echo $file; ?>" />
<?php
		}
	}
}

/**
 * Custom JS files.
 * Possible locations: includes/js/custom.js | assets/custom/custom.js
 */
$custom_js_locations = [ 'includes/js/custom.js', 'assets/custom/custom.js' ];
foreach ( $custom_js_locations as $js_file ) {
	if ( file_exists ( ROOT_DIR . DS . $js_file ) ) {
		$load_js_files[]	= BASE_URI . $js_file;
	}
}

/**
 * Used before the </body> tag to print the JS files
 */
function load_js_files() {
	global $load_compat_js_files;
	global $load_js_files;

	if ( !empty( $load_compat_js_files ) ) {
		foreach ( $load_compat_js_files as $index => $info ) {
?>
			<!--[if <?php echo $info['cond']; ?>]><script language="javascript" type="text/javascript" src="<?php echo $info['file']; ?>"></script><![endif]-->
<?php
		}
	}

	if ( !empty( $load_js_files ) ) {
		foreach ( $load_js_files as $file ) {
?>
			<script src="<?php echo $file; ?>"></script>
<?php
		}
	}
}


function load_js_header_files() {
?>
    <script type="text/javascript" src="<?php echo ASSETS_LIB_URL; ?>/jquery/jquery.min.js"></script>
    <script type="text/javascript" src="<?php echo ASSETS_LIB_URL; ?>/jquery-migrate/jquery-migrate.min.js"></script>
    <script type="text/javascript" src="<?php echo BASE_URI; ?>/node_modules/@ckeditor/ckeditor5-build-classic/build/ckeditor.js"></script>

    <!--[if lt IE 9]>
        <script src="<?php echo ASSETS_LIB_URL; ?>/html5shiv.min.js"></script>
        <script src="<?php echo ASSETS_LIB_URL; ?>/respond.min.js"></script>
    <![endif]-->
<?php
}