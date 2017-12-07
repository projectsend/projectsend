<?php

/**
 * Attribute filter for renaming attributes.
 *
 * @author Gyula Szabo MTA SZTAKI
 * @package SimpleSAMLphp
 *
 * You just follow the 'source' => 'destination' schema. In this example user's  * cn will be the user's displayName.
 *
 *    5 => array(
 *        'class' => 'core:AttributeCopy',
 *        'cn' => 'displayName',
 *        'uid' => 'username',
 *         ),
 *
 */
class sspmod_core_Auth_Process_AttributeCopy extends SimpleSAML_Auth_ProcessingFilter {

	/**
	 * Assosiative array with the mappings of attribute names.
	 */
	private $map = array();


	/**
	 * Initialize this filter, parse configuration
	 *
	 * @param array $config  Configuration information about this filter.
	 * @param mixed $reserved  For future use.
	 */
	public function __construct($config, $reserved) {
		parent::__construct($config, $reserved);

		assert('is_array($config)');

		foreach($config as $source => $destination) {

			if(!is_string($source)) {
				throw new Exception('Invalid source attribute name: ' . var_export($source, TRUE));
			}

			if(!is_string($destination)) {
				throw new Exception('Invalid destination attribute name: ' . var_export($destination, TRUE));
			}

			$this->map[$source] = $destination;
		}
	}


	/**
	 * Apply filter to rename attributes.
	 *
	 * @param array &$request  The current request
	 */
	public function process(&$request) {
		assert('is_array($request)');
		assert('array_key_exists("Attributes", $request)');

		$attributes =& $request['Attributes'];

		foreach($attributes as $name => $values) {
			if (array_key_exists($name,$this->map)){
				$attributes[$this->map[$name]] = $values;
			}
		}

	}
}
