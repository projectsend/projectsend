<?php

/**
 * A directory over logout information.
 *
 * @package SimpleSAMLphp
 */
class sspmod_saml_SP_LogoutStore {

	/**
	 * Create logout table in SQL, if it is missing.
	 *
	 * @param SimpleSAML_Store_SQL $store  The datastore.
	 */
	private static function createLogoutTable(SimpleSAML_Store_SQL $store) {

		if ($store->getTableVersion('saml_LogoutStore') === 1) {
			return;
		}

		$query = 'CREATE TABLE ' . $store->prefix . '_saml_LogoutStore (
			_authSource VARCHAR(30) NOT NULL,
			_nameId VARCHAR(40) NOT NULL,
			_sessionIndex VARCHAR(50) NOT NULL,
			_expire TIMESTAMP NOT NULL,
			_sessionId VARCHAR(50) NOT NULL,
			UNIQUE (_authSource, _nameID, _sessionIndex)
		)';
		$store->pdo->exec($query);

		$query = 'CREATE INDEX ' . $store->prefix . '_saml_LogoutStore_expire ON '  . $store->prefix . '_saml_LogoutStore (_expire)';
		$store->pdo->exec($query);

		$query = 'CREATE INDEX ' . $store->prefix . '_saml_LogoutStore_nameId ON '  . $store->prefix . '_saml_LogoutStore (_authSource, _nameId)';
		$store->pdo->exec($query);

		$store->setTableVersion('saml_LogoutStore', 1);
	}


	/**
	 * Clean the logout table of expired entries.
	 *
	 * @param SimpleSAML_Store_SQL $store  The datastore.
	 */
	private static function cleanLogoutStore(SimpleSAML_Store_SQL $store) {

		SimpleSAML_Logger::debug('saml.LogoutStore: Cleaning logout store.');

		$query = 'DELETE FROM ' . $store->prefix . '_saml_LogoutStore WHERE _expire < :now';
		$params = array('now' => gmdate('Y-m-d H:i:s'));

		$query = $store->pdo->prepare($query);
		$query->execute($params);
	}


	/**
	 * Register a session in the SQL datastore.
	 *
	 * @param SimpleSAML_Store_SQL $store  The datastore.
	 * @param string $authId  The authsource ID.
	 * @param string $nameId  The hash of the users NameID.
	 * @param string $sessionIndex  The SessionIndex of the user.
	 */
	private static function addSessionSQL(SimpleSAML_Store_SQL $store, $authId, $nameId, $sessionIndex, $expire, $sessionId) {
		assert('is_string($authId)');
		assert('is_string($nameId)');
		assert('is_string($sessionIndex)');
		assert('is_string($sessionId)');
		assert('is_int($expire)');

		self::createLogoutTable($store);

		if (rand(0, 1000) < 10) {
			self::cleanLogoutStore($store);
		}

		$data = array(
			'_authSource' => $authId,
			'_nameId' => $nameId,
			'_sessionIndex' => $sessionIndex,
			'_expire' => gmdate('Y-m-d H:i:s', $expire),
			'_sessionId' => $sessionId,
		);
		$store->insertOrUpdate($store->prefix . '_saml_LogoutStore', array('_authSource', '_nameId', '_sessionIndex'), $data);
	}


	/**
	 * Retrieve sessions from the SQL datastore.
	 *
	 * @param SimpleSAML_Store_SQL $store  The datastore.
	 * @param string $authId  The authsource ID.
	 * @param string $nameId  The hash of the users NameID.
	 * @return array  Associative array of SessionIndex =>  SessionId.
	 */
	private static function getSessionsSQL(SimpleSAML_Store_SQL $store, $authId, $nameId) {
		assert('is_string($authId)');
		assert('is_string($nameId)');

		self::createLogoutTable($store);

		$params = array(
			'_authSource' => $authId,
			'_nameId' => $nameId,
			'now' => gmdate('Y-m-d H:i:s'),
		);

		// We request the columns in lowercase in order to be compatible with PostgreSQL
		$query = 'SELECT _sessionIndex AS _sessionindex, _sessionId AS _sessionid FROM ' . $store->prefix . '_saml_LogoutStore' .
			' WHERE _authSource = :_authSource AND _nameId = :_nameId AND _expire >= :now';
		$query = $store->pdo->prepare($query);
		$query->execute($params);

		$res = array();
		while ( ($row = $query->fetch(PDO::FETCH_ASSOC)) !== FALSE) {
			$res[$row['_sessionindex']] = $row['_sessionid'];
		}

		return $res;
	}


	/**
	 * Retrieve all session IDs from a key-value store.
	 *
	 * @param SimpleSAML_Store $store  The datastore.
	 * @param string $authId  The authsource ID.
	 * @param string $nameId  The hash of the users NameID.
	 * @param array $sessionIndexes  The session indexes.
	 * @return array  Associative array of SessionIndex =>  SessionId.
	 */
	private static function getSessionsStore(SimpleSAML_Store $store, $authId, $nameId, array $sessionIndexes) {
		assert('is_string($authId)');
		assert('is_string($nameId)');

		$res = array();
		foreach ($sessionIndexes as $sessionIndex) {
			$sessionId = $store->get('saml.LogoutStore', $nameId . ':' . $sessionIndex);
			if ($sessionId === NULL) {
				continue;
			}
			assert('is_string($sessionId)');
			$res[$sessionIndex] = $sessionId;
		}

		return $res;
	}


	/**
	 * Register a new session in the datastore.
	 *
	 * @param string $authId  The authsource ID.
	 * @param array $nameId  The NameID of the user.
	 * @param string|NULL $sessionIndex  The SessionIndex of the user.
	 */
	public static function addSession($authId, array $nameId, $sessionIndex, $expire) {
		assert('is_string($authId)');
		assert('is_string($sessionIndex) || is_null($sessionIndex)');
		assert('is_int($expire)');

		if ($sessionIndex === NULL) {
			/* This IdP apparently did not include a SessionIndex, and thus probably does not
			 * support SLO. We still want to add the session to the data store just in case
			 * it supports SLO, but we don't want an LogoutRequest with a specific
			 * SessionIndex to match this session. We therefore generate our own session index.
			 */
			$sessionIndex = SimpleSAML\Utils\Random::generateID();
		}

		$store = SimpleSAML_Store::getInstance();
		if ($store === FALSE) {
			// We don't have a datastore.
			return;
		}

		/* Normalize NameID. */
		ksort($nameId);
		$strNameId = serialize($nameId);
		$strNameId = sha1($strNameId);

		/* Normalize SessionIndex. */
		if (strlen($sessionIndex) > 50) {
			$sessionIndex = sha1($sessionIndex);
		}

		$session = SimpleSAML_Session::getSessionFromRequest();
		$sessionId = $session->getSessionId();

		if ($store instanceof SimpleSAML_Store_SQL) {
			self::addSessionSQL($store, $authId, $strNameId, $sessionIndex, $expire, $sessionId);
		} else {
			$store->set('saml.LogoutStore', $strNameId . ':' . $sessionIndex, $sessionId, $expire);
		}
	}


	/**
	 * Log out of the given sessions.
	 *
	 * @param string $authId  The authsource ID.
	 * @param array $nameId  The NameID of the user.
	 * @param array $sessionIndexes  The SessionIndexes we should log out of. Logs out of all if this is empty.
	 * @returns int|FALSE  Number of sessions logged out, or FALSE if not supported.
	 */
	public static function logoutSessions($authId, array $nameId, array $sessionIndexes) {
		assert('is_string($authId)');

		$store = SimpleSAML_Store::getInstance();
		if ($store === FALSE) {
			/* We don't have a datastore. */
			return FALSE;
		}

		/* Normalize NameID. */
		ksort($nameId);
		$strNameId = serialize($nameId);
		$strNameId = sha1($strNameId);

		/* Normalize SessionIndexes. */
		foreach ($sessionIndexes as &$sessionIndex) {
			assert('is_string($sessionIndex)');
			if (strlen($sessionIndex) > 50) {
				$sessionIndex = sha1($sessionIndex);
			}
		}
		unset($sessionIndex); // Remove reference

		if ($store instanceof SimpleSAML_Store_SQL) {
			$sessions = self::getSessionsSQL($store, $authId, $strNameId);
		} elseif (empty($sessionIndexes)) {
			/* We cannot fetch all sessions without a SQL store. */
			return FALSE;
		} else {
			/** @var SimpleSAML_Store $sessions At this point the store cannot be false */
			$sessions = self::getSessionsStore($store, $authId, $strNameId, $sessionIndexes);

		}

		if (empty($sessionIndexes)) {
			$sessionIndexes = array_keys($sessions);
		}

		$sessionHandler = SimpleSAML_SessionHandler::getSessionHandler();

		$numLoggedOut = 0;
		foreach ($sessionIndexes as $sessionIndex) {
			if (!isset($sessions[$sessionIndex])) {
				SimpleSAML_Logger::info('saml.LogoutStore: Logout requested for unknown SessionIndex.');
				continue;
			}

			$sessionId = $sessions[$sessionIndex];

			$session = SimpleSAML_Session::getSession($sessionId);
			if ($session === NULL) {
				SimpleSAML_Logger::info('saml.LogoutStore: Skipping logout of missing session.');
				continue;
			}

			if (!$session->isValid($authId)) {
				SimpleSAML_Logger::info('saml.LogoutStore: Skipping logout of session because it isn\'t authenticated.');
				continue;
			}

			SimpleSAML_Logger::info('saml.LogoutStore: Logging out of session with trackId [' . $session->getTrackID() . '].');
			$session->doLogout($authId);
			$numLoggedOut += 1;
		}

		return $numLoggedOut;
	}

}
