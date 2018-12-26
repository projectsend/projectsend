<?php
/**
 * Load the i18n class and the corresponding language files
 *
 * @package		ProjectSend
 * @subpackage	Language
 */
use ProjectSend\Classes\I18n;

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
	$lang_file	= CORE_LANG_DIR . $lang_sess . '.mo';
	if ( file_exists( $lang_file ) ) {
		$lang = $lang_sess;
	}
}
/**
 * If not, check if the admin selected the option to use
 * the browser's language (if available)
 */
else {
	if ( defined('USE_BROWSER_LANG') ) {
		switch ( USE_BROWSER_LANG ) {
			case '0':
			default:
				break;
			case '1':
				$browser_lang	= substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
				$lang_file		= CORE_LANG_DIR . $browser_lang . '.mo';
				if ( file_exists( $lang_file ) ) {
					$lang = $browser_lang;
				}
				break;
		}
	}
}

define('LOADED_LANG', $lang);
define('I18N_DEFAULT_DOMAIN', 'cftp_admin');
ProjectSend\Classes\I18n::LoadDomain(CORE_LANG_DIR . DS . "{$lang}.mo", 'cftp_admin' );

/** Gettext for Twig */
putenv('LC_ALL='.$lang);
setlocale(LC_ALL, $lang);
bindtextdomain('cftp_admin', CORE_LANG_DIR);
bind_textdomain_codeset('cftp_admin', 'UTF-8');
textdomain('cftp_admin');