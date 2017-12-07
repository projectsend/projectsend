<?php

/**
 * This is a helper class for saving and loading state information.
 *
 * The state must be an associative array. This class will add additional keys to this
 * array. These keys will always start with 'SimpleSAML_Auth_State.'.
 *
 * It is also possible to add a restart URL to the state. If state information is lost, for
 * example because it timed out, or the user loaded a bookmarked page, the loadState function
 * will redirect to this URL. To use this, set $state[SimpleSAML_Auth_State::RESTART] to this
 * URL.
 *
 * Both the saveState and the loadState function takes in a $stage parameter. This parameter is
 * a security feature, and is used to prevent the user from taking a state saved one place and
 * using it as input a different place.
 *
 * The $stage parameter must be a unique string. To maintain uniqueness, it must be on the form
 * "<classname>.<identifier>" or "<module>:<identifier>".
 *
 * There is also support for passing exceptions through the state.
 * By defining an exception handler when creating the state array, users of the state
 * array can call throwException with the state and the exception. This exception will
 * be passed to the handler defined by the EXCEPTION_HANDLER_URL or EXCEPTION_HANDLER_FUNC
 * elements of the state array.
 *
 * @author Olav Morken, UNINETT AS.
 * @package SimpleSAMLphp
 */
class SimpleSAML_Auth_State {


	/**
	 * The index in the state array which contains the identifier.
	 */
	const ID = 'SimpleSAML_Auth_State.id';


	/**
	 * The index in the cloned state array which contains the identifier of the
	 * original state.
	 */
	const CLONE_ORIGINAL_ID = 'SimpleSAML_Auth_State.cloneOriginalId';


	/**
	 * The index in the state array which contains the current stage.
	 */
	const STAGE = 'SimpleSAML_Auth_State.stage';


	/**
	 * The index in the state array which contains the restart URL.
	 */
	const RESTART = 'SimpleSAML_Auth_State.restartURL';


	/**
	 * The index in the state array which contains the exception handler URL.
	 */
	const EXCEPTION_HANDLER_URL = 'SimpleSAML_Auth_State.exceptionURL';


	/**
	 * The index in the state array which contains the exception handler function.
	 */
	const EXCEPTION_HANDLER_FUNC = 'SimpleSAML_Auth_State.exceptionFunc';


	/**
	 * The index in the state array which contains the exception data.
	 */
	const EXCEPTION_DATA = 'SimpleSAML_Auth_State.exceptionData';


	/**
	 * The stage of a state with an exception.
	 */
	const EXCEPTION_STAGE = 'SimpleSAML_Auth_State.exceptionStage';


	/**
	 * The URL parameter which contains the exception state id.
	 */
	const EXCEPTION_PARAM = 'SimpleSAML_Auth_State_exceptionId';


	/**
	 * State timeout.
	 */
	private static $stateTimeout = NULL;


	/**
	 * Get the persistent authentication state from the state array.
	 *
	 * @param array $state The state array to analyze.
	 * @return array The persistent authentication state.
	 */
	public static function getPersistentAuthData(array $state)
	{
		// save persistent authentication data
		$persistent = array();

		if (array_key_exists('PersistentAuthData', $state)) {
			foreach ($state['PersistentAuthData'] as $key) {
				if (isset($state[$key])) {
					$persistent[$key] = $state[$key];
				}
			}
		}

		// add those that should always be included
		$mandatory = array(
			'Attributes',
			'Expire',
			'LogoutState',
			'AuthInstant',
			'RememberMe',
			'saml:sp:NameID'
		);
		foreach ($mandatory as $key) {
			if (isset($state[$key])) {
				$persistent[$key] = $state[$key];
			}
		}

		return $persistent;
	}


	/**
	 * Retrieve the ID of a state array.
	 *
	 * Note that this function will not save the state.
	 *
	 * @param array &$state  The state array.
	 * @param bool $rawId  Return a raw ID, without a restart URL. Defaults to FALSE.
	 * @return string  Identifier which can be used to retrieve the state later.
	 */
	public static function getStateId(&$state, $rawId = FALSE) {
		assert('is_array($state)');
		assert('is_bool($rawId)');

		if (!array_key_exists(self::ID, $state)) {
			$state[self::ID] = SimpleSAML\Utils\Random::generateID();
		}

		$id = $state[self::ID];

		if ($rawId || !array_key_exists(self::RESTART, $state)) {
			// Either raw ID or no restart URL. In any case, return the raw ID.
			return $id;
		}

		// We have a restart URL. Return the ID with that URL.
		return $id . ':' . $state[self::RESTART];
	}


	/**
	 * Retrieve state timeout.
	 *
	 * @return integer  State timeout.
	 */
	private static function getStateTimeout() {
		if (self::$stateTimeout === NULL) {
			$globalConfig = SimpleSAML_Configuration::getInstance();
			self::$stateTimeout = $globalConfig->getInteger('session.state.timeout', 60*60);
		}

		return self::$stateTimeout;
	}


	/**
	 * Save the state.
	 *
	 * This function saves the state, and returns an id which can be used to
	 * retrieve it later. It will also update the $state array with the identifier.
	 *
	 * @param array &$state  The login request state.
	 * @param string $stage  The current stage in the login process.
	 * @param bool $rawId  Return a raw ID, without a restart URL.
	 * @return string  Identifier which can be used to retrieve the state later.
	 */
	public static function saveState(&$state, $stage, $rawId = FALSE) {
		assert('is_array($state)');
		assert('is_string($stage)');
		assert('is_bool($rawId)');

		$return = self::getStateId($state, $rawId);
		$id = $state[self::ID];

		// Save stage
		$state[self::STAGE] = $stage;

		// Save state
		$serializedState = serialize($state);
		$session = SimpleSAML_Session::getSessionFromRequest();
		$session->setData('SimpleSAML_Auth_State', $id, $serializedState, self::getStateTimeout());

		SimpleSAML_Logger::debug('Saved state: ' . var_export($return, TRUE));

		return $return;
	}


	/**
	 * Clone the state.
	 *
	 * This function clones and returns the new cloned state.
	 *
	 * @param array $state  The original request state.
	 * @return array  Cloned state data.
	 */
	public static function cloneState(array $state) {
		$clonedState = $state;

		if (array_key_exists(self::ID, $state)) {
			$clonedState[self::CLONE_ORIGINAL_ID] = $state[self::ID];
			unset($clonedState[self::ID]);

			SimpleSAML_Logger::debug('Cloned state: ' . var_export($state[self::ID], TRUE));
		} else {
			SimpleSAML_Logger::debug('Cloned state with undefined id.');
		}

		return $clonedState;
	}


	/**
	 * Retrieve saved state.
	 *
	 * This function retrieves saved state information. If the state information has been lost,
	 * it will attempt to restart the request by calling the restart URL which is embedded in the
	 * state information. If there is no restart information available, an exception will be thrown.
	 *
	 * @param string $id  State identifier (with embedded restart information).
	 * @param string $stage  The stage the state should have been saved in.
	 * @param bool $allowMissing  Whether to allow the state to be missing.
	 * @return array|NULL  State information, or NULL if the state is missing and $allowMissing is TRUE.
	 */
	public static function loadState($id, $stage, $allowMissing = FALSE) {
		assert('is_string($id)');
		assert('is_string($stage)');
		assert('is_bool($allowMissing)');
		SimpleSAML_Logger::debug('Loading state: ' . var_export($id, TRUE));

		$sid = self::parseStateID($id);

		$session = SimpleSAML_Session::getSessionFromRequest();
		$state = $session->getData('SimpleSAML_Auth_State', $sid['id']);

		if ($state === NULL) {
			// Could not find saved data
			if ($allowMissing) {
				return NULL;
			}

			if ($sid['url'] === NULL) {
				throw new SimpleSAML_Error_NoState();
			}

			\SimpleSAML\Utils\HTTP::redirectUntrustedURL($sid['url']);
		}

		$state = unserialize($state);
		assert('is_array($state)');
		assert('array_key_exists(self::ID, $state)');
		assert('array_key_exists(self::STAGE, $state)');

		// Verify stage
		if ($state[self::STAGE] !== $stage) {
			/* This could be a user trying to bypass security, but most likely it is just
			 * someone using the back-button in the browser. We try to restart the
			 * request if that is possible. If not, show an error.
			 */

			$msg = 'Wrong stage in state. Was \'' . $state[self::STAGE] .
				'\', should be \'' . $stage . '\'.';

			SimpleSAML_Logger::warning($msg);

			if ($sid['url'] === NULL) {
				throw new Exception($msg);
			}

			\SimpleSAML\Utils\HTTP::redirectUntrustedURL($sid['url']);
		}

		return $state;
	}


	/**
	 * Delete state.
	 *
	 * This function deletes the given state to prevent the user from reusing it later.
	 *
	 * @param array &$state  The state which should be deleted.
	 */
	public static function deleteState(&$state) {
		assert('is_array($state)');

		if (!array_key_exists(self::ID, $state)) {
			// This state hasn't been saved
			return;
		}

		SimpleSAML_Logger::debug('Deleting state: ' . var_export($state[self::ID], TRUE));

		$session = SimpleSAML_Session::getSessionFromRequest();
		$session->deleteData('SimpleSAML_Auth_State', $state[self::ID]);
	}


	/**
	 * Throw exception to the state exception handler.
	 *
	 * @param array $state  The state array.
	 * @param SimpleSAML_Error_Exception $exception  The exception.
	 */
	public static function throwException($state, SimpleSAML_Error_Exception $exception) {
		assert('is_array($state)');

		if (array_key_exists(self::EXCEPTION_HANDLER_URL, $state)) {

			// Save the exception
			$state[self::EXCEPTION_DATA] = $exception;
			$id = self::saveState($state, self::EXCEPTION_STAGE);

			// Redirect to the exception handler
			\SimpleSAML\Utils\HTTP::redirectTrustedURL($state[self::EXCEPTION_HANDLER_URL], array(self::EXCEPTION_PARAM => $id));

		} elseif (array_key_exists(self::EXCEPTION_HANDLER_FUNC, $state)) {
			// Call the exception handler
			$func = $state[self::EXCEPTION_HANDLER_FUNC];
			assert('is_callable($func)');

			call_user_func($func, $exception, $state);
			assert(FALSE);

		} else {
			/*
			 * No exception handler is defined for the current state.
			 */
			throw $exception;
		}

	}


	/**
	 * Retrieve an exception state.
	 *
	 * @param string|NULL $id  The exception id. Can be NULL, in which case it will be retrieved from the request.
	 * @return array|NULL  The state array with the exception, or NULL if no exception was thrown.
	 */
	public static function loadExceptionState($id = NULL) {
		assert('is_string($id) || is_null($id)');

		if ($id === NULL) {
			if (!array_key_exists(self::EXCEPTION_PARAM, $_REQUEST)) {
				// No exception
				return NULL;
			}
			$id = $_REQUEST[self::EXCEPTION_PARAM];
		}

		$state = self::loadState($id, self::EXCEPTION_STAGE);
		assert('array_key_exists(self::EXCEPTION_DATA, $state)');

		return $state;
	}


	/**
	 * Get the ID and (optionally) a URL embedded in a StateID, in the form 'id:url'.
	 *
	 * @param string $stateId The state ID to use.
	 *
	 * @return array A hashed array with the ID and the URL (if any), in the 'id' and 'url' keys, respectively. If
	 * there's no URL in the input parameter, NULL will be returned as the value for the 'url' key.
	 *
	 * @author Andreas Solberg, UNINETT AS <andreas.solberg@uninett.no>
	 * @author Jaime Perez, UNINETT AS <jaime.perez@uninett.no>
	 */
	public static function parseStateID($stateId) {
		$tmp = explode(':', $stateId, 2);
		$id = $tmp[0];
		$url = null;
		if (count($tmp) === 2) {
			$url = $tmp[1];
		}
		return array('id' => $id, 'url' => $url);
	}

}
