<?php
/**
 * Generates the list of CSS and JS files to load
 * base on the $load_scripts array defined on each
 * page.
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
$load_js_files[]	= BASE_URI . 'assets/bootstrap/js/bootstrap.min.js';
$load_js_files[]	= BASE_URI . 'includes/js/jquery.validations.js';
$load_js_files[]	= BASE_URI . 'includes/js/jquery.psendmodal.js';
$load_js_files[]	= BASE_URI . 'includes/js/jen/jen.js';
$load_js_files[]	= BASE_URI . 'includes/js/js.cookie.js';
$load_js_files[]	= BASE_URI . 'includes/js/main.js';

/** CSS */

/** Fonts*/
$load_css_files[]	= 'https://fonts.googleapis.com/css?family=Open+Sans:400,700,300';
$load_css_files[]	= 'https://fonts.googleapis.com/css?family=Abel';
$load_css_files[]	= BASE_URI . 'assets/font-awesome/css/font-awesome.min.css';

/**
 * Optional scripts
 */
if ( !empty( $load_scripts ) ) {
	foreach ( $load_scripts as $script ) {
		switch ( $script ) {
			case 'recaptcha':
				$load_js_files[]		= 'https://www.google.com/recaptcha/api.js';
				break;
			case 'social_login':
				$load_css_files[]		= BASE_URI . 'css/social-login.css';
				break;
			case 'datepicker':
				$load_css_files[]		= BASE_URI . 'includes/js/bootstrap-datepicker/css/datepicker.css';
				$load_js_files[]		= BASE_URI . 'includes/js/bootstrap-datepicker/js/bootstrap-datepicker.js';
				break;
			case 'spinedit':
				$load_css_files[]		= BASE_URI . 'includes/js/bootstrap-spinedit/bootstrap-spinedit.css';
				$load_js_files[]		= BASE_URI . 'includes/js/bootstrap-spinedit/bootstrap-spinedit.js';
				break;
			case 'footable':
				$footable_js_file		= ( !empty( $footable_min ) ) ? 'footable.min.js' : 'footable.all.min.js';
				$load_css_files[]		= BASE_URI . 'includes/js/footable/css/footable.core.css';
				$load_css_files[]		= BASE_URI . 'css/footable.css';
				$load_js_files[]		= BASE_URI . 'includes/js/footable/' . $footable_js_file;
				break;
			case 'jquery_tags_input':
				$load_css_files[]		= BASE_URI . 'includes/js/jquery-tags-input/jquery.tagsinput.css';
				$load_js_files[]		= BASE_URI . 'includes/js/jquery-tags-input/jquery.tagsinput.min.js';
				break;
			case 'chosen':
				$load_css_files[]		= BASE_URI . 'includes/js/chosen/chosen.min.css';
				$load_css_files[]		= BASE_URI . 'includes/js/chosen/chosen.bootstrap.css';
				$load_js_files[]		= BASE_URI . 'includes/js/chosen/chosen.jquery.min.js';
				break;
			case 'toggle':
				$load_css_files[]		= BASE_URI . 'includes/js/bootstrap-toggle/css/bootstrap-toggle.min.css';
				$load_js_files[]		= BASE_URI . 'includes/js/bootstrap-toggle/js/bootstrap-toggle.min.js';
				break;
			case 'plupload':
				$load_css_files[]		= BASE_URI . 'includes/plupload/js/jquery.plupload.queue/css/jquery.plupload.queue.css';
				$load_js_files[]		= BASE_URI . 'includes/js/browserplus-min.js';
				$load_js_files[]		= BASE_URI . 'includes/plupload/js/plupload.full.js';
				$load_js_files[]		= BASE_URI . 'includes/plupload/js/jquery.plupload.queue/jquery.plupload.queue.js';
				/**
				 * Load a plupload translation file, if the ProjectSend language
				 * on sys.config.php is set to anything other than "en", and the
				 * corresponding plupload file exists.
				 */
				if ( LOADED_LANG != 'en' ) {
					$plupload_lang_file = 'includes/plupload/js/i18n/'.LOADED_LANG.'.js';
					if ( file_exists( $plupload_lang_file ) ) {
						$load_js_files[] = BASE_URI . $plupload_lang_file;
					}
				}

				break;
			case 'flot':
				$load_js_files[]		= BASE_URI . 'includes/flot/jquery.flot.min.js';
				$load_js_files[]		= BASE_URI . 'includes/flot/jquery.flot.resize.min.js';
				$load_js_files[]		= BASE_URI . 'includes/flot/jquery.flot.time.min.js';
				$load_compat_js_files[]	= array(
												'file'	=> BASE_URI . 'includes/flot/excanvas.js',
												'cond'	=> 'lt IE 9',
											);
				break;
			case 'ckeditor':
				if ( DESCRIPTIONS_USE_CKEDITOR == '1' ) {
					$load_js_files[]		= BASE_URI . 'includes/js/ckeditor/ckeditor.js';
				}
				break;
		}
	}
}

$load_css_files[]	= BASE_URI . 'assets/bootstrap/css/bootstrap.min.css';
$load_css_files[]	= BASE_URI . 'css/main.css';
$load_css_files[]	= BASE_URI . 'css/mobile.css';

/**
 * Load a different css file when called from the default template.
 */
if ( isset( $this_template_css ) ) {
	$load_css_files[]	= $this_template_css;
}

/**
 * Custom CSS styles.
 */
$custom_css_location = ROOT_DIR . '/css/custom.css';
if ( file_exists( $custom_css_location ) ) {
	$load_css_files[]	= BASE_URI . 'css/custom.css';
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
 * Custom JS.
 */
$custom_js_location = ROOT_DIR . '/includes/js/custom.js';
if ( file_exists( $custom_js_location ) ) {
	$load_js_files[]	= BASE_URI . 'includes/js/custom.js';
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
?>