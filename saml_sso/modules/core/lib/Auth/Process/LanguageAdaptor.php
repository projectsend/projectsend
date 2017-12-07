<?php

/**
 * Filter to set and get language settings from attributes.
 *
 * @author Andreas Åkre Solberg, UNINETT AS.
 * @package SimpleSAMLphp
 */
class sspmod_core_Auth_Process_LanguageAdaptor extends SimpleSAML_Auth_ProcessingFilter {

	private $langattr = 'preferredLanguage';


	/**
	 * Initialize this filter.
	 *
	 * @param array $config  Configuration information about this filter.
	 * @param mixed $reserved  For future use.
	 */
	public function __construct($config, $reserved) {
		parent::__construct($config, $reserved);
		assert('is_array($config)');

		if (array_key_exists('attributename', $config)) {
			$this->langattr = $config['attributename'];
		}
	}


	/**
	 * Apply filter to add or replace attributes.
	 *
	 * Add or replace existing attributes with the configured values.
	 *
	 * @param array &$request  The current request
	 */
	public function process(&$request) {
		assert('is_array($request)');
		assert('array_key_exists("Attributes", $request)');

		$attributes =& $request['Attributes'];

		$attrlang = NULL;
		if (array_key_exists($this->langattr, $attributes))
			$attrlang = $attributes[$this->langattr][0];

		$lang = SimpleSAML_XHTML_Template::getLanguageCookie();


		if (isset($attrlang))
			SimpleSAML_Logger::debug('LanguageAdaptor: Language in attribute was set [' . $attrlang . ']');
		if (isset($lang))
			SimpleSAML_Logger::debug('LanguageAdaptor: Language in session   was set [' . $lang . ']');


		if (isset($attrlang) && !isset($lang)) {
			// Language set in attribute but not in cookie - update cookie
			SimpleSAML_XHTML_Template::setLanguageCookie($attrlang);
		} elseif (!isset($attrlang) && isset($lang)) {
			// Language set in cookie, but not in attribute. Update attribute
			$request['Attributes'][$this->langattr] = array($lang);
		}

	}

}
