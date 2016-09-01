<?php
/**
 * Load the i18n class and the corresponding language files
 *
 * @package		ProjectSend
 * @subpackage	Core
 */

/**
 * Current system language
 *
 * @see sys.config.sample.php
 */
$lang = SITE_LANG;

switch ( USE_BROWSER_LANG ) {
	case '0':
	default:
		break;
	case '1':
		$browser_lang	= substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
		$lang_file		= ROOT_DIR . '/lang/' . $browser_lang . '.mo';
		if ( file_exists( $lang_file ) ) {
			$lang = $browser_lang;
		}
		break;
}


define('I18N_DEFAULT_DOMAIN', 'cftp_admin');
require_once(ROOT_DIR.'/includes/classes/i18n.php');
I18n::LoadDomain(ROOT_DIR."/lang/{$lang}.mo", 'cftp_admin' );
?>