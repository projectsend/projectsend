<?php

/**
 * Exception which will show a 400 Bad Request error page.
 *
 * This exception can be thrown from within an module page handler. The user will then be
 * shown a 400 Bad Request error page.
 *
 * @author Olav Morken, UNINETT AS.
 * @package SimpleSAMLphp
 */
class SimpleSAML_Error_BadRequest extends SimpleSAML_Error_Error {


	/**
	 * Reason why this request was invalid.
	 */
	private $reason;


	/**
	 * Create a new BadRequest error.
	 *
	 * @param string $reason  Description of why the request was unacceptable.
	 */
	public function __construct($reason) {
		assert('is_string($reason)');

		$this->reason = $reason;
		parent::__construct(array('BADREQUEST', '%REASON%' => $this->reason));
		$this->httpCode = 400;
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
