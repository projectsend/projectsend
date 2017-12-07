<?php
/**
 * Baseclass for auth source exceptions.
 * 
 * @package SimpleSAMLphp_base
 *
 */
class SimpleSAML_Error_AuthSource extends SimpleSAML_Error_Error {


	/**
	 * Authsource module name.
	 */
	private $authsource;


	/**
	 * Reason why this request was invalid.
	 */
	private $reason;


	/**
	 * Create a new AuthSource error.
	 *
	 * @param string $authsource  Authsource module name from where this error was thrown.
	 * @param string $reason  Description of the error.
	 */
	public function __construct($authsource, $reason, $cause = NULL) {
		assert('is_string($authsource)');
		assert('is_string($reason)');

		$this->authsource = $authsource;
		$this->reason = $reason;
		parent::__construct(
			array(
				'AUTHSOURCEERROR',
				'%AUTHSOURCE%' => htmlspecialchars(var_export($this->authsource, TRUE)),
				'%REASON%' => htmlspecialchars(var_export($this->reason, TRUE))
			),
			$cause
		);
	}


	/**
	 * Retrieve the authsource module name from where this error was thrown.
	 *
	 * @return string  Authsource module name.
	 */
	public function getAuthSource() {
		return $this->authsource;
	}


	/**
	 * Retrieve the reason why the request was invalid.
	 *
	 * @return string  The reason why the request was invalid.
	 */
	public function getReason() {
		return $this->reason;
	}

	
}
