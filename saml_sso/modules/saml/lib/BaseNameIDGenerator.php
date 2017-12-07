<?php

/**
 * Base filter for generating NameID values.
 *
 * @package SimpleSAMLphp
 */
abstract class sspmod_saml_BaseNameIDGenerator extends SimpleSAML_Auth_ProcessingFilter {

	/**
	 * What NameQualifier should be used.
	 * Can be one of:
	 *  - a string: The qualifier to use.
	 *  - FALSE: Do not include a NameQualifier. This is the default.
	 *  - TRUE: Use the IdP entity ID.
	 *
	 * @var string|bool
	 */
	private $nameQualifier;


	/**
	 * What SPNameQualifier should be used.
	 * Can be one of:
	 *  - a string: The qualifier to use.
	 *  - FALSE: Do not include a SPNameQualifier.
	 *  - TRUE: Use the SP entity ID. This is the default.
	 *
	 * @var string|bool
	 */
	private $spNameQualifier;


	/**
	 * The format of this NameID.
	 *
	 * This property must be initialized the subclass.
	 *
	 * @var string
	 */
	protected $format;


	/**
	 * Initialize this filter, parse configuration.
	 *
	 * @param array $config  Configuration information about this filter.
	 * @param mixed $reserved  For future use.
	 */
	public function __construct($config, $reserved) {
		parent::__construct($config, $reserved);
		assert('is_array($config)');

		if (isset($config['NameQualifier'])) {
			$this->nameQualifier = $config['NameQualifier'];
		} else {
			$this->nameQualifier = FALSE;
		}

		if (isset($config['SPNameQualifier'])) {
			$this->spNameQualifier = $config['SPNameQualifier'];
		} else {
			$this->spNameQualifier = TRUE;
		}
	}


	/**
	 * Get the NameID value.
	 *
	 * @return string|NULL  The NameID value.
	 */
	abstract protected function getValue(array &$state);


	/**
	 * Generate transient NameID.
	 *
	 * @param array &$state  The request state.
	 */
	public function process(&$state) {
		assert('is_array($state)');
		assert('is_string($this->format)');

		$value = $this->getValue($state);
		if ($value === NULL) {
			return;
		}

		$nameId = array('Value' => $value);

		if ($this->nameQualifier === TRUE) {
			if (isset($state['IdPMetadata']['entityid'])) {
				$nameId['NameQualifier'] = $state['IdPMetadata']['entityid'];
			} else {
				SimpleSAML_Logger::warning('No IdP entity ID, unable to set NameQualifier.');
			}
		} elseif (is_string($this->nameQualifier)) {
			$nameId['NameQualifier'] = $this->nameQualifier;
		}

		if ($this->spNameQualifier === TRUE) {
			if (isset($state['SPMetadata']['entityid'])) {
				$nameId['SPNameQualifier'] = $state['SPMetadata']['entityid'];
			} else {
				SimpleSAML_Logger::warning('No SP entity ID, unable to set SPNameQualifier.');
			}
		} elseif (is_string($this->spNameQualifier)) {
			$nameId['SPNameQualifier'] = $this->spNameQualifier;
		}

		$state['saml:NameID'][$this->format] = $nameId;
	}

}
