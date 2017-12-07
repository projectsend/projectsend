<?php

class sspmod_smartattributes_Auth_Process_SmartID extends SimpleSAML_Auth_ProcessingFilter {

	/**
	 * Which attributes to use as identifiers?
	 *
	 * IMPORTANT: If you use the (default) attributemaps (twitter2name, facebook2name,
	 * etc., be sure to comment out the entries that map xxx_targetedID to
	 * eduPersonTargetedID, or there will be no way to see its origin any more.
	 */
	private $_candidates = array(
		'eduPersonTargetedID',
		'eduPersonPrincipalName',
		'openid',
		'facebook_targetedID',
		'twitter_targetedID',
		'windowslive_targetedID',
		'myspace_targetedID',
		'linkedin_targetedID',
	);

	/**
	 * The name of the generated ID attribute.
	 */
	private $_id_attribute = 'smart_id';

	/**
	 * Whether to append the AuthenticatingAuthority, separated by '!'
	 * This only works when SSP is used as a gateway.
	 */
	private $_add_authority = true;

	/**
	 * Whether to prepend the CandidateID, separated by ':'
	 */
	private $_add_candidate = true;

	/**
	 * Attributes which should be added/appended.
	 *
	 * Associative array of arrays.
	 */
	private $attributes = array();


	public function __construct($config, $reserved) {
		parent::__construct($config, $reserved);

		assert('is_array($config)');

		if (array_key_exists('candidates', $config)) {
			$this->_candidates = $config['candidates'];
			if (!is_array($this->_candidates)) {
				throw new Exception('SmartID authproc configuration error: \'candidates\' should be an array.');
			}
		}

		if (array_key_exists('id_attribute', $config)) {
			$this->_id_attribute = $config['id_attribute'];
			if (!is_string($this->_id_attribute)) {
				throw new Exception('SmartID authproc configuration error: \'id_attribute\' should be a string.');
			}
		}

		if (array_key_exists('add_authority', $config)) {
			$this->_add_authority = $config['add_authority'];
			if (!is_bool($this->_add_authority)) {
				throw new Exception('SmartID authproc configuration error: \'add_authority\' should be a boolean.');
			}
		}

		if (array_key_exists('add_candidate', $config)) {
			$this->_add_candidate = $config['add_candidate'];
			if (!is_bool($this->_add_candidate)) {
				throw new Exception('SmartID authproc configuration error: \'add_candidate\' should be a boolean.');
			}
		}

	}

	private function addID($attributes, $request) {
		foreach ($this->_candidates as $idCandidate) {
			if (isset($attributes[$idCandidate][0])) {
				if(($this->_add_authority) && (isset($request['saml:AuthenticatingAuthority'][0]))) {
					return ($this->_add_candidate ? $idCandidate.':' : '').$attributes[$idCandidate][0] . '!' . $request['saml:AuthenticatingAuthority'][0];
				} else {
					return ($this->_add_candidate ? $idCandidate.':' : '').$attributes[$idCandidate][0];
				}
			}
		}
		/*
		* At this stage no usable id_candidate has been detected.
		*/
		throw new SimpleSAML_Error_Exception('This service needs at least one of the following
		attributes to identity users: '.implode(', ', $this->_candidates).'. Unfortunately not
		one of them was detected. Please ask your institution administrator to release one of
		them, or try using another identity provider.');
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

		$ID = $this->addID($request['Attributes'], $request);

		if(isset($ID)) $request['Attributes'][$this->_id_attribute] = array($ID);
	}
}
