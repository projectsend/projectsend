<?php
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
  return ProjectSend\I18n::Translate( $string, $domain );
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
  echo ProjectSend\I18n::Translate( $string, $domain );
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
  return ProjectSend\I18n::NTranslate( $singular, $plural, $count, $domain );
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
  echo ProjectSend\I18n::NTranslate( $singular, $plural, $count, $domain );
}
