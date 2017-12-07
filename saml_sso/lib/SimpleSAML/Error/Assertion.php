<?php

/**
 * Class for creating exceptions from assertion failures.
 *
 * @author Olav Morken, UNINETT AS.
 * @package SimpleSAMLphp
 */
class SimpleSAML_Error_Assertion extends SimpleSAML_Error_Exception {


	/**
	 * The assertion which failed, or NULL if only an expression was passed to the
	 * assert-function.
	 */
	private $assertion;


	/**
	 * Constructor for the assertion exception.
	 *
	 * Should only be called from the onAssertion handler.
	 *
	 * @param string|NULL $assertion  The assertion which failed, or NULL if the assert-function was
	 *                                given an expression.
	 */
	public function __construct($assertion = NULL) {
		assert('is_null($assertion) || is_string($assertion)');

		$msg = 'Assertion failed: ' . var_export($assertion, TRUE);
		parent::__construct($msg);

		$this->assertion = $assertion;
	}


	/**
	 * Retrieve the assertion which failed.
	 *
	 * @return string|NULL  The assertion which failed, or NULL if the assert-function was called with an expression.
	 */
	public function getAssertion() {
		return $this->assertion;
	}


	/**
	 * Install this assertion handler.
	 *
	 * This function will register this assertion handler. If will not enable assertions if they are
	 * disabled.
	 */
	public static function installHandler() {

		assert_options(ASSERT_WARNING,    0);
		assert_options(ASSERT_QUIET_EVAL, 0);
		assert_options(ASSERT_CALLBACK,   array('SimpleSAML_Error_Assertion', 'onAssertion'));
	}


	/**
	 * Handle assertion.
	 *
	 * This function handles an assertion.
	 *
	 * @param string $file  The file assert was called from.
	 * @param int $line  The line assert was called from.
	 * @param mixed $message  The expression which was passed to the assert-function.
	 */
	public static function onAssertion($file, $line, $message) {

		if(!empty($message)) {
			$exception = new self($message);
		} else {
			$exception = new self();
		}

		$exception->logError();
	}

}
