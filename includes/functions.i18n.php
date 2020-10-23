<?php
use ProjectSend\Classes\I18n;

/**
 * Translation functions
 *
 * @package		ProjectSend
 * @subpackage	Functions
 */

/**
 * Define this constant to be use as the default domain
 * using in auxilar functions around {@link I18n} class.
 *
 */
if (!defined('I18N_DEFAULT_DOMAIN') && !defined('IS_TEMPLATE_VIEW')) {
    define( 'I18N_DEFAULT_DOMAIN', $default_domain );
}

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