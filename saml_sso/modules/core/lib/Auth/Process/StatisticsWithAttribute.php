<?php

/**
 * Log a line in the STAT log with one attribute.
 *
 * @author Andreas Åkre Solberg, UNINETT AS.
 * @package SimpleSAMLphp
 */
class sspmod_core_Auth_Process_StatisticsWithAttribute extends SimpleSAML_Auth_ProcessingFilter {


	/**
	 * The attribute to log
	 */
	private $attribute = NULL;
	
	private $typeTag = 'saml20-idp-SSO';


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
			$this->attribute = $config['attributename'];
			if (!is_string($this->attribute)) {
				throw new Exception('Invalid attribute name given to core:StatisticsWithAttribute filter.');
			}
		}
		
		if (array_key_exists('type', $config)) {
			$this->typeTag = $config['type'];
			if (!is_string($this->typeTag)) {
				throw new Exception('Invalid typeTag given to core:StatisticsWithAttribute filter.');
			}
		}
	}


	/**
	 * Log line.
	 *
	 * @param array &$state  The current state.
	 */
	public function process(&$state) {
		assert('is_array($state)');
		assert('array_key_exists("Attributes", $state)');

		$logAttribute = 'NA';
		$source = 'NA';
		$dest = 'NA';

		if (array_key_exists($this->attribute, $state['Attributes'])) $logAttribute = $state['Attributes'][$this->attribute][0];		
		if (array_key_exists('Source', $state)) {
			if (isset($state['Source']['core:statistics-id'])) {
				$source = $state['Source']['core:statistics-id'];
			} else {
				$source = $state['Source']['entityid'];
			}
		}

		if (array_key_exists('Destination', $state)) {
			if (isset($state['Destination']['core:statistics-id'])) {
				$dest = $state['Destination']['core:statistics-id'];
			} else {
				$dest = $state['Destination']['entityid'];
			}
		}

		if (!array_key_exists('PreviousSSOTimestamp', $state)) {
			// The user hasn't authenticated with this SP earlier in this session
			SimpleSAML_Logger::stats($this->typeTag . '-first ' . $dest . ' ' . $source . ' ' . $logAttribute);
		}

		SimpleSAML_Logger::stats($this->typeTag . ' ' . $dest . ' ' . $source . ' ' . $logAttribute);
	}

}
