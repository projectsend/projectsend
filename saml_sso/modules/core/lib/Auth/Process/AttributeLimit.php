<?php

/**
 * A filter for limiting which attributes are passed on.
 *
 * @author Olav Morken, UNINETT AS.
 * @package SimpleSAMLphp
 */
class sspmod_core_Auth_Process_AttributeLimit extends SimpleSAML_Auth_ProcessingFilter {

	/**
	 * List of attributes which this filter will allow through.
	 */
	private $allowedAttributes = array();


	/**
	 * Whether the 'attributes' option in the metadata takes precedence.
	 *
	 * @var bool
	 */
	private $isDefault = FALSE;


	/**
	 * Initialize this filter.
	 *
	 * @param array $config  Configuration information about this filter.
	 * @param mixed $reserved  For future use
     * @throws SimpleSAML_Error_Exception If invalid configuration is found.
	 */
	public function __construct($config, $reserved) {
		parent::__construct($config, $reserved);

		assert('is_array($config)');

		foreach ($config as $index => $value) {
			if ($index === 'default') {
				$this->isDefault = (bool)$value;
			} elseif (is_int($index)) {
				if (!is_string($value)) {
					throw new SimpleSAML_Error_Exception('AttributeLimit: Invalid attribute name: ' .
                        var_export($value, TRUE));
				}
				$this->allowedAttributes[] = $value;
            } elseif (is_string($index)) {
                if (!is_array($value)) {
                    throw new SimpleSAML_Error_Exception('AttributeLimit: Values for ' . var_export($index, TRUE) .
                        ' must be specified in an array.');
                }
                $this->allowedAttributes[$index] = $value;
			} else {
				throw new SimpleSAML_Error_Exception('AttributeLimit: Invalid option: ' . var_export($index, TRUE));
			}
		}
	}


	/**
	 * Get list of allowed from the SP/IdP config.
	 *
	 * @param array &$request  The current request.
	 * @return array|NULL  Array with attribute names, or NULL if no limit is placed.
	 */
	private static function getSPIdPAllowed(array &$request) {

		if (array_key_exists('attributes', $request['Destination'])) {
			// SP Config
			return $request['Destination']['attributes'];
		}
		if (array_key_exists('attributes', $request['Source'])) {
			// IdP Config
			return $request['Source']['attributes'];
		}
		return NULL;
	}


	/**
	 * Apply filter to remove attributes.
	 *
	 * Removes all attributes which aren't one of the allowed attributes.
	 *
	 * @param array &$request  The current request
     * @throws SimpleSAML_Error_Exception If invalid configuration is found.
	 */
	public function process(&$request) {
		assert('is_array($request)');
		assert('array_key_exists("Attributes", $request)');

		if ($this->isDefault) {
			$allowedAttributes = self::getSPIdPAllowed($request);
			if ($allowedAttributes === NULL) {
				$allowedAttributes = $this->allowedAttributes;
			}
		} elseif (!empty($this->allowedAttributes)) {
			$allowedAttributes = $this->allowedAttributes;
		} else {
			$allowedAttributes = self::getSPIdPAllowed($request);
			if ($allowedAttributes === NULL) {
				return; /* No limit on attributes. */
			}
		}

		$attributes =& $request['Attributes'];

		foreach ($attributes as $name => $values) {
			if (!in_array($name, $allowedAttributes, TRUE)) {
                // the attribute name is not in the array of allowed attributes
                if (array_key_exists($name, $allowedAttributes)) {
                    // but it is an index of the array
                    if (!is_array($allowedAttributes[$name])) {
                        throw new SimpleSAML_Error_Exception('AttributeLimit: Values for ' . var_export($name, TRUE) .
                            ' must be specified in an array.');
                    }
                    $attributes[$name] = array_intersect($attributes[$name], $allowedAttributes[$name]);
                    if (!empty($attributes[$name])) {
                        continue;
                    }
                }
                unset($attributes[$name]);
			}
		}

	}

}
