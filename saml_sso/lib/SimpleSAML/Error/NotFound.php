<?php

/**
 * Exception which will show a 404 Not Found error page.
 *
 * This exception can be thrown from within an module page handler. The user will then be
 * shown a 404 Not Found error page.
 *
 * @author Olav Morken, UNINETT AS.
 * @package SimpleSAMLphp
 */
class SimpleSAML_Error_NotFound extends SimpleSAML_Error_Error {


	/**
	 * Reason why the given page could not be found.
	 */
	private $reason;


	/**
	 * Create a new NotFound error
	 *
	 * @param string $reason  Optional description of why the given page could not be found.
	 */
	public function __construct($reason = NULL) {

		assert('is_null($reason) || is_string($reason)');

		$url = \SimpleSAML\Utils\HTTP::getSelfURL();

		if($reason === NULL) {
			parent::__construct(array('NOTFOUND', '%URL%' => $url));
		} else {
			parent::__construct(array('NOTFOUNDREASON', '%URL%' => $url, '%REASON%' => $reason));
		}

		$this->reason = $reason;
		$this->httpCode = 404;
	}


	/**
	 * Retrieve the reason why the given page could not be found.
	 *
	 * @return string|NULL  The reason why the page could not be found.
	 */
	public function getReason() {
		return $this->reason;
	}

}
