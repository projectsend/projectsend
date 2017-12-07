<?php

/**
 * Exception indicating user aborting the authentication process.
 *
 * @package SimpleSAMLphp
 */
class SimpleSAML_Error_UserAborted extends SimpleSAML_Error_Error {

	/**
	 * Create the error
	 *
	 * @param Exception|NULL $cause  The exception that caused this error.
	 */
	public function __construct(Exception $cause = NULL) {
		parent::__construct('USERABORTED', $cause);
	}

}
