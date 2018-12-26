<?php
/**
 * Language loading and translations related Functions
 *
 * @package		ProjectSend
 * @subpackage	Language
 */
use ProjectSend\Classes\I18n;

 /**
 * Reads the lang folder and scans for .mo files.
 * Returns an array of avaiable languages.
 */
function get_available_languages()
{
	global $locales_names;

	$langs = array();

	$mo_files = glob(CORE_LANG_DIR . DS . '*.mo');
	foreach ($mo_files as $file) {
        $lang_file	= pathinfo($file, PATHINFO_FILENAME);

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

/** From here on, these functions are for the i18n class */
/**
 * Get a translated string
 *
 * @param String $string To be translated
 * @param String $domain String domain text
 * @return String Translated string if possible
 *
 */
function __( $string, $domain = I18N_DEFAULT_DOMAIN )
{
    return I18n::Translate( $string, $domain );
}

/**
 * Print a translated string
 *
 * @param String $string To be translated
 * @param String $domain String domain text
 *
 */
function _e( $string, $domain = I18N_DEFAULT_DOMAIN ) 
{
    echo I18n::Translate( $string, $domain );
}

/**
 * Get a translated singular or plural string
 *
 * @param String $singular Singular string version
 * @param String $plural Plural string version
 * @param Integer $count Determine what string version use
 * @param String $domain String domain text
 * @return String Translated string if possible
 *
 */
function _n( $singular, $plural, $count, $domain = I18N_DEFAULT_DOMAIN )
{
    return I18n::NTranslate( $singular, $plural, $count, $domain );
}

/**
 * Print a translated singular or plural string
 *
 * @param String $singular Singular string version
 * @param String $plural Plural string version
 * @param Integer $count Determine what string version use
 * @param String $domain String domain text
 *
 */
function _ne( $singular, $plural, $count, $domain = I18N_DEFAULT_DOMAIN )
{
    echo I18n::NTranslate( $singular, $plural, $count, $domain );
}
