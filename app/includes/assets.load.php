<?php
/**
 * List of CSS and JS files to load.
 *
 * @package ProjectSend
 */
global
    $load_css_files,
    $load_js_files,
    $load_compat_js_files,
    $jquery_location,
    $migrate_location;

$load_css_files			= [];
$load_js_files			= [];
$load_compat_js_files	= [];

/**
 * CSS files block
 */
/** External */
$load_css_files[]	= 'https://fonts.googleapis.com/css?family=Open+Sans:400,700,300';
/** Dependencies */
// Bower
$bower_dependencies_css = [
    'bootstrap/dist/css/bootstrap.min.css',
    'font-awesome/css/font-awesome.min.css',
    'bootstrap-datepicker/dist/css/bootstrap-datepicker3.min.css',
    'bootstrap-toggle/css/bootstrap-toggle.min.css',
    'chosen/chosen.min.css',
    'footable/css/footable.core.css',
    'jquery.tagsinput/dist/jquery.tagsinput.min.css',
];
foreach ( $bower_dependencies_css as $dep ) {
    $load_css_files[]	= BOWER_DEPENDENCIES_URI . $dep;
}
// Composer
$composer_dependencies_css = [
    'moxiecode/plupload/js/jquery.plupload.queue/css/jquery.plupload.queue.css',
];
foreach ( $composer_dependencies_css as $dep ) {
    $load_css_files[]	= COMPOSER_DEPENDENCIES_URI . $dep;
}
/** Own CSS files */
$load_css_files[]	= ASSETS_CSS_URI . 'main.min.css';
/** Current template CSS file */
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
 * Javascript files block
 */
$jquery_location	= BOWER_DEPENDENCIES_URI . 'jquery/dist/jquery.min.js';
$migrate_location	= BOWER_DEPENDENCIES_URI . 'jquery-migrate/jquery-migrate.min.js';
/** Dependencies */
// Bower
$bower_dependencies_js = [
    'bootstrap/dist/js/bootstrap.min.js',
    'jen/jen.js',
    'sprintf/dist/sprintf.min.js',
    'js-cookie/src/js.cookie.js',
    'bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js',
    'bootstrap-toggle/js/bootstrap-toggle.min.js',
    'chosen/chosen.jquery.min.js',
    'ckeditor/ckeditor.js',
    'Flot/jquery.flot.js',
    'Flot/jquery.flot.resize.js',
    'Flot/jquery.flot.time.js',
    'footable/js/footable.js',
    'jquery.tagsinput/src/jquery.tagsinput.js',
];
foreach ( $bower_dependencies_js as $dep ) {
    $load_js_files[]	= BOWER_DEPENDENCIES_URI . $dep;
}
// Composer
$composer_dependencies_js = [
    'moxiecode/plupload/js/plupload.full.min.js',
    'moxiecode/plupload/js/jquery.plupload.queue/jquery.plupload.queue.min.js',
];
foreach ( $composer_dependencies_js as $dep ) {
    $load_js_files[]	= COMPOSER_DEPENDENCIES_URI . $dep;
}
/** Own JS files */
$load_js_files[]	= ASSETS_JS_URI . 'projectsend.min.js';
/** Compatibility */
$load_compat_js_files[]	= array(
	'file'	=> BOWER_DEPENDENCIES_URI . 'flot/excanvas.js',
	'cond'	=> 'lt IE 9',
);
/** Languages */
if ( LOADED_LANG != 'en' ) {
	/** Plupload */
	$plupload_lang_file = 'vendor/moxiecode/plupload/js/i18n/'.LOADED_LANG.'.js';
	if ( file_exists( $plupload_lang_file ) ) {
		$load_js_files[] = BASE_URI . $plupload_lang_file;
	}
}
/**
 * Custom JS files.
 * Possible locations: includes/js/custom.js | assets/custom/custom.js
 */
$custom_js_locations = [ 'includes/js/custom.js', 'assets/custom/custom.js' ];
foreach ( $custom_js_locations as $js_file ) {
	if ( file_exists ( ROOT_DIR . DS . $js_file ) ) {
		$load_css_files[]	= BASE_URI . $js_file;
	}
}
/** External */
$load_js_files[]	= 'https://www.google.com/recaptcha/api.js';
?>

<!--[if lt IE 9]>
	<script src="<?php echo ASSETS_JS_URI; ?>ie8compatibility.min.js"></script>
<![endif]-->