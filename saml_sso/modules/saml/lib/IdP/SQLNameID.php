<?php

/**
 * Helper class for working with persistent NameIDs stored in SQL datastore.
 *
 * @package SimpleSAMLphp
 */
class sspmod_saml_IdP_SQLNameID  {

	/**
	 * Create NameID table in SQL, if it is missing.
	 *
	 * @param SimpleSAML_Store_SQL $store  The datastore.
	 */
	private static function createTable(SimpleSAML_Store_SQL $store) {

		if ($store->getTableVersion('saml_PersistentNameID') === 1) {
			return;
		}

		$query = 'CREATE TABLE ' . $store->prefix . '_saml_PersistentNameID (
			_idp VARCHAR(256) NOT NULL,
			_sp VARCHAR(256) NOT NULL,
			_user VARCHAR(256) NOT NULL,
			_value VARCHAR(40) NOT NULL,
			UNIQUE (_idp, _sp, _user)
		)';
		$store->pdo->exec($query);

		$query = 'CREATE INDEX ' . $store->prefix . '_saml_PersistentNameID_idp_sp ON '  . $store->prefix . '_saml_PersistentNameID (_idp, _sp)';
		$store->pdo->exec($query);

		$store->setTableVersion('saml_PersistentNameID', 1);
	}


	/**
	 * Retrieve the SQL datastore.
	 *
	 * Will also ensure that the NameID table is present.
	 *
	 * @return SimpleSAML_Store_SQL  SQL datastore.
	 */
	private static function getStore() {

		$store = SimpleSAML_Store::getInstance();
		if (!($store instanceof SimpleSAML_Store_SQL)) {
			throw new SimpleSAML_Error_Exception('SQL NameID store requires SimpleSAMLphp to be configured with a SQL datastore.');
		}

		self::createTable($store);

		return $store;
	}


	/**
	 * Add a NameID into the database.
	 *
	 * @param SimpleSAML_Store_SQL $store  The data store.
	 * @param string $idpEntityId  The IdP entityID.
	 * @param string $spEntityId  The SP entityID.
	 * @param string $user  The user's unique identificator (e.g. username).
	 * @param string $value  The NameID value.
	 */
	public static function add($idpEntityId, $spEntityId, $user, $value) {
		assert('is_string($idpEntityId)');
		assert('is_string($spEntityId)');
		assert('is_string($user)');
		assert('is_string($value)');

		$store = self::getStore();

		$params = array(
			'_idp' => $idpEntityId,
			'_sp' => $spEntityId,
			'_user' => $user,
			'_value' => $value,
		);

		$query = 'INSERT INTO ' . $store->prefix . '_saml_PersistentNameID (_idp, _sp, _user, _value) VALUES(:_idp, :_sp, :_user, :_value)';
		$query = $store->pdo->prepare($query);
		$query->execute($params);
	}


	/**
	 * Retrieve a NameID into from database.
	 *
	 * @param string $idpEntityId  The IdP entityID.
	 * @param string $spEntityId  The SP entityID.
	 * @param string $user  The user's unique identificator (e.g. username).
	 * @return string|NULL $value  The NameID value, or NULL of no NameID value was found.
	 */
	public static function get($idpEntityId, $spEntityId, $user) {
		assert('is_string($idpEntityId)');
		assert('is_string($spEntityId)');
		assert('is_string($user)');

		$store = self::getStore();

		$params = array(
			'_idp' => $idpEntityId,
			'_sp' => $spEntityId,
			'_user' => $user,
		);

		$query = 'SELECT _value FROM ' . $store->prefix . '_saml_PersistentNameID WHERE _idp = :_idp AND _sp = :_sp AND _user = :_user';
		$query = $store->pdo->prepare($query);
		$query->execute($params);

		$row = $query->fetch(PDO::FETCH_ASSOC);
		if ($row === FALSE) {
			// No NameID found
			return NULL;
		}

		return $row['_value'];
	}


	/**
	 * Delete a NameID from the database.
	 *
	 * @param string $idpEntityId  The IdP entityID.
	 * @param string $spEntityId  The SP entityID.
	 * @param string $user  The user's unique identificator (e.g. username).
	 */
	public static function delete($idpEntityId, $spEntityId, $user) {
		assert('is_string($idpEntityId)');
		assert('is_string($spEntityId)');
		assert('is_string($user)');

		$store = self::getStore();

		$params = array(
			'_idp' => $idpEntityId,
			'_sp' => $spEntityId,
			'_user' => $user,
		);

		$query = 'DELETE FROM ' . $store->prefix . '_saml_PersistentNameID WHERE _idp = :_idp AND _sp = :_sp AND _user = :_user';
		$query = $store->pdo->prepare($query);
		$query->execute($params);
	}


	/**
	 * Retrieve all federated identities for an IdP-SP pair.
	 *
	 * @param string $idpEntityId  The IdP entityID.
	 * @param string $spEntityId  The SP entityID.
	 * @return array  Array of userid => NameID.
	 */
	public static function getIdentities($idpEntityId, $spEntityId) {
		assert('is_string($idpEntityId)');
		assert('is_string($spEntityId)');

		$store = self::getStore();

		$params = array(
			'_idp' => $idpEntityId,
			'_sp' => $spEntityId,
		);

		$query = 'SELECT _user, _value FROM ' . $store->prefix . '_saml_PersistentNameID WHERE _idp = :_idp AND _sp = :_sp';
		$query = $store->pdo->prepare($query);
		$query->execute($params);

		$res = array();
		while ( ($row = $query->fetch(PDO::FETCH_ASSOC)) !== FALSE) {
			$res[$row['_user']] = $row['_value'];
		}

		return $res;
	}

}
