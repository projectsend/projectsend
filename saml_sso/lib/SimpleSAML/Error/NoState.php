<?php

/**
 * Exception which will show a page telling the user
 * that we don't know what to do.
 *
 * @package SimpleSAMLphp
 */
class SimpleSAML_Error_NoState extends SimpleSAML_Error_Error {


	/**
	 * Create the error
	 */
	public function __construct() {
		$this->includeTemplate = 'core:no_state.tpl.php';
		parent::__construct('NOSTATE');
	}

}
