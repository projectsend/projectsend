<?php

/**
 * Class for saving normal exceptions for serialization.
 *
 * This class is used by the SimpleSAML_Auth_State class when it needs
 * to serialize an exception which doesn't subclass the
 * SimpleSAML_Error_Exception class.
 *
 * It creates a new exception which contains the backtrace and message
 * of the original exception.
 *
 * @package SimpleSAMLphp
 */
class SimpleSAML_Error_UnserializableException extends SimpleSAML_Error_Exception {

	/**
	 * The classname of the original exception.
	 *
	 * @var string
	 */
	private $class;


	/**
	 * Create a serializable exception representing an unserializable exception.
	 *
	 * @param Exception $original  The original exception.
	 */
	public function __construct(Exception $original) {

		$this->class = get_class($original);
		$msg = $original->getMessage();
		$code = $original->getCode();

		if (!is_int($code)) {
			// PDOException uses a string as the code. Filter it out here.
			$code = -1;
		}

		parent::__construct($msg, $code);
		$this->initBacktrace($original);
	}


	/**
	 * Retrieve the class of this exception.
	 *
	 * @return string  The classname.
	 */
	public function getClass() {
		return $this->class;
	}

}
