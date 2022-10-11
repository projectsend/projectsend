<?php
use ProjectSend\Classes\I18n;

/**
 * Load the i18n class and the corresponding language files
 */

/**
 * Current system language defined in the configuration file
 * Loaded language falls back to this value if neither of the
 * following 2 options is valid.
 *
 * @see sys.config.sample.php
 */
if ( !defined( 'SITE_LANG' ) ) {
	define( 'SITE_LANG', 'en' );
}
$lang = SITE_LANG;

/**
 * If a user selected a language on the log in form, use it
 */
if ( isset( $_SESSION['lang'] ) ) {
	$lang_sess = $_SESSION['lang'];
	$lang_file = ROOT_DIR . DS . 'lang' . DS . $lang_sess . '.mo';
	if ( file_exists( $lang_file ) ) {
		$lang = $lang_sess;
	}
}
/**
 * If not, check if the admin selected the option to use
 * the browser's language (if available)
 */
else {
    $options_object = new \ProjectSend\Classes\Options;
    $browser_lang = $options_object->getOption('use_browser_lang');
	if ( !empty($browser_lang) ) {
		switch ( $browser_lang ) {
			case '0':
			default:
				break;
			case '1':
                if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                    $browser_lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
                    $lang_file = ROOT_DIR . DS . 'lang' . DS . $browser_lang . '.mo';
                    if ( file_exists( $lang_file ) ) {
                        $lang = $browser_lang;
                    }
                }
				break;
		}
	}
}

define('LOADED_LANG', $lang);

I18n::LoadDomain(ROOT_DIR.DS."lang".DS."{$lang}.mo", 'cftp_admin' );
