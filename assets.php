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
/* commends has been removed to fix password generate */
//$load_js_files[]	= BASE_URI . 'assets/bootstrap/js/bootstrap.min.js';
$load_js_files[]	= BASE_URI . 'includes/js/jquery.validations.js';
$load_js_files[]	= BASE_URI . 'includes/js/jen/jen.js';
$load_js_files[]	= BASE_URI . 'includes/js/main.js';

/*---------------------added by B)-----------------------------------*/
$load_js_files[]	= BASE_URI . 'assets/wrap/js/app.config.js';
$load_js_files[]	= BASE_URI . 'assets/wrap/js/plugin/jquery-touch/jquery.ui.touch-punch.min.js';
$load_js_files[]	= BASE_URI . 'assets/wrap/js/bootstrap/bootstrap.min.js';
$load_js_files[]	= BASE_URI . 'assets/wrap/js/notification/SmartNotification.min.js';
$load_js_files[]	= BASE_URI . 'assets/wrap/js/smartwidgets/jarvis.widget.min.js';
$load_js_files[]	= BASE_URI . 'assets/wrap/js/plugin/easy-pie-chart/jquery.easy-pie-chart.min.js';
$load_js_files[]	= BASE_URI . 'assets/wrap/js/plugin/sparkline/jquery.sparkline.min.js';
$load_js_files[]	= BASE_URI . 'assets/wrap/js/plugin/jquery-validate/jquery.validate.min.js';
$load_js_files[]	= BASE_URI . 'assets/wrap/js/plugin/masked-input/jquery.maskedinput.min.js';
$load_js_files[]	= BASE_URI . 'assets/wrap/js/plugin/select2/select2.min.js';
$load_js_files[]	= BASE_URI . 'assets/wrap/js/plugin/select2/select2.min.js';
$load_js_files[]	= BASE_URI . 'assets/wrap/js/plugin/bootstrap-slider/bootstrap-slider.min.js';
$load_js_files[]	= BASE_URI . 'assets/wrap/js/plugin/msie-fix/jquery.mb.browser.min.js';
$load_js_files[]	= BASE_URI . 'assets/wrap/js/plugin/fastclick/fastclick.min.js';
//$load_js_files[]	= BASE_URI . 'assets/wrap/js/demo.min.js';
//$load_js_files[]	= BASE_URI . 'assets/wrap/js/app.min.js';
$load_js_files[]	= BASE_URI . 'assets/wrap/js/smart-chat-ui/smart.chat.ui.min.js';
$load_js_files[]	= BASE_URI . 'assets/wrap/js/speech/voicecommand.min.js';
$load_js_files[]	= BASE_URI . 'assets/wrap/js/smart-chat-ui/smart.chat.manager.min.js';
$load_js_files[]	= BASE_URI . 'assets/wrap/js/plugin/flot/jquery.flot.cust.min.js';
$load_js_files[]	= BASE_URI . 'assets/wrap/js/plugin/flot/jquery.flot.resize.min.js';
$load_js_files[]	= BASE_URI . 'assets/wrap/js/plugin/flot/jquery.flot.time.min.js';
$load_js_files[]	= BASE_URI . 'assets/wrap/js/plugin/flot/jquery.flot.tooltip.min.js';
$load_js_files[]	= BASE_URI . 'assets/wrap/js/plugin/vectormap/jquery-jvectormap-1.2.2.min.js';
$load_js_files[]	= BASE_URI . 'assets/wrap/js/plugin/moment/moment.min.js';
$load_js_files[]	= BASE_URI . 'assets/wrap/js/plugin/fullcalendar/jquery.fullcalendar.min.js';
$load_js_files[]	= 'https://ajax.googleapis.com/ajax/libs/jquery/3.1.1/jquery.min.js';
$load_js_files[]	= BASE_URI . 'includes/js/jquery.psendmodal.js';
$load_css_files[]	= 'https://fonts.googleapis.com/css?family=Open+Sans:400italic,700italic,300,400,700';
/*---------------------end by B)-----------------------------------*/
/** CSS */

/** Fonts*/
/*$load_css_files[]	= 'https://fonts.googleapis.com/css?family=Open+Sans:400,700,300';
$load_css_files[]	= 'https://fonts.googleapis.com/css?family=Abel';*/

/**
 * Optional scripts
 */
 /* -------------------commnted by B)---------------------------- */
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
				$load_css_files[]		= BASE_URI . 'includes/js/footable/css/footable.core.css';
				$load_css_files[]		= BASE_URI . 'css/footable.css';
				$load_js_files[]		= BASE_URI . 'includes/js/footable/footable.all.min.js';
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
			case 'plupload':
			$load_css_files[]		= BASE_URI . 'includes/pluploadOld/js/jquery.plupload.queue/css/jquery.plupload.queue.css';
			// $load_js_files[]		= BASE_URI . 'includes/plupload/js/plupload.dev.js';
			$load_js_files[]		= BASE_URI . 'includes/plupload/js/plupload.full.min.js';
			$load_js_files[]		= BASE_URI . 'includes/plupload/js/jquery.plupload.queue/jquery.plupload.queue.min.js';

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
		}
	}
}
 /* -------------------commnted by B)---------------------------- */

// $load_css_files[]	= BASE_URI . 'css/shared.css';
// $load_css_files[]	= BASE_URI . 'css/mobile.css';

/* -------------------- added B) -------------------------------- */
$load_css_files[]	= BASE_URI . 'assets/wrap/css/smartadmin-production-plugins.min.css';
$load_css_files[]	= BASE_URI . 'assets/wrap/css/smartadmin-production.min.css';
$load_css_files[]	= BASE_URI . 'assets/wrap/css/smartadmin-skins.min.css';
$load_css_files[]	= BASE_URI . 'assets/wrap/css/smartadmin-rtl.min.css';
$load_css_files[]	= BASE_URI . 'assets/wrap/css/your_style.css';
$load_css_files[]	= BASE_URI . 'assets/wrap/css/demo.min.css';

/* -------------------- end B) -------------------------------- */

/**
 * Load a different css file when called from the admin, or
 * the default template.
 */
if ( !isset( $this_template_css ) ) {
	/** Back-end */
	$load_css_files[]	= BASE_URI . 'css/base.css';
}
else {
	/** Template */
	//$load_css_files[]	= $this_template_css;
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
