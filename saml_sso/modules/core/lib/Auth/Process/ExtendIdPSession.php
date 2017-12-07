<?php

/**
 * Extend IdP session and cookies.
*/
class sspmod_core_Auth_Process_ExtendIdPSession extends SimpleSAML_Auth_ProcessingFilter {

	public function process(&$state) {
		assert('is_array($state)');

		if (empty($state['Expire']) || empty($state['Authority'])) {
			return;
		}

		$now = time();
		$delta = $state['Expire'] - $now;

		$globalConfig = SimpleSAML_Configuration::getInstance();
		$sessionDuration = $globalConfig->getInteger('session.duration', 8*60*60);

		// Extend only if half of session duration already passed
		if ($delta >= ($sessionDuration * 0.5)) {
			return;
		}

		// Update authority expire time
		$session = SimpleSAML_Session::getSessionFromRequest();
		$session->setAuthorityExpire($state['Authority']);

		/* Update session cookies duration */

		/* If remember me is active */
		$rememberMeExpire = $session->getRememberMeExpire();
		if (!empty($state['RememberMe']) && $rememberMeExpire !== NULL && $globalConfig->getBoolean('session.rememberme.enable', FALSE)) {
			$session->setRememberMeExpire();
			return;
		}

		/* Or if session lifetime is more than zero */
		$sessionHandler = SimpleSAML_SessionHandler::getSessionHandler();
		$cookieParams = $sessionHandler->getCookieParams();
		if ($cookieParams['lifetime'] > 0) {
			$session->updateSessionCookies();
		}
	}

}
