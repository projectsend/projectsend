<?php

/**
 * Filter to generate the eduPersonTargetedID attribute.
 *
 * By default, this filter will generate the ID based on the UserID of the current user.
 * This is by default generated from the attribute configured in 'userid.attribute' in the
 * metadata. If this attribute isn't present, the userid will be generated from the
 * eduPersonPrincipalName attribute, if it is present.
 *
 * It is possible to generate this attribute from another attribute by specifying this attribute
 * in this configuration.
 *
 * Example - generate from user ID:
 * <code>
 * 'authproc' => array(
 *   50 => 'core:TargetedID',
 * )
 * </code>
 *
 * Example - generate from mail-attribute:
 * <code>
 * 'authproc' => array(
 *   50 => array('class' => 'core:TargetedID' , 'attributename' => 'mail'),
 * ),
 * </code>
 *
 * @author Olav Morken, UNINETT AS.
 * @package SimpleSAMLphp
 */
class sspmod_core_Auth_Process_TargetedID extends SimpleSAML_Auth_ProcessingFilter {


	/**
	 * The attribute we should generate the targeted id from, or NULL if we should use the
	 * UserID.
	 */
	private $attribute = NULL;


	/**
	 * Whether the attribute should be generated as a NameID value, or as a simple string.
	 *
	 * @var boolean
	 */
	private $generateNameId = FALSE;


	/**
	 * Initialize this filter.
	 *
	 * @param array $config  Configuration information about this filter.
	 * @param mixed $reserved  For future use.
	 */
	public function __construct($config, $reserved) {
		parent::__construct($config, $reserved);

		assert('is_array($config)');

		if (array_key_exists('attributename', $config)) {
			$this->attribute = $config['attributename'];
			if (!is_string($this->attribute)) {
				throw new Exception('Invalid attribute name given to core:TargetedID filter.');
			}
		}

		if (array_key_exists('nameId', $config)) {
			$this->generateNameId = $config['nameId'];
			if (!is_bool($this->generateNameId)) {
				throw new Exception('Invalid value of \'nameId\'-option to core:TargetedID filter.');
			}
		}
	}


	/**
	 * Apply filter to add the targeted ID.
	 *
	 * @param array &$state  The current state.
	 */
	public function process(&$state) {
		assert('is_array($state)');
		assert('array_key_exists("Attributes", $state)');

		if ($this->attribute === NULL) {
			if (!array_key_exists('UserID', $state)) {
				throw new Exception('core:TargetedID: Missing UserID for this user. Please' .
					' check the \'userid.attribute\' option in the metadata against the' .
					' attributes provided by the authentication source.');
			}

			$userID = $state['UserID'];
		} else {
			if (!array_key_exists($this->attribute, $state['Attributes'])) {
				throw new Exception('core:TargetedID: Missing attribute \'' . $this->attribute .
					'\', which is needed to generate the targeted ID.');
			}

			$userID = $state['Attributes'][$this->attribute][0];
		}


		$secretSalt = SimpleSAML\Utils\Config::getSecretSalt();

		if (array_key_exists('Source', $state)) {
			$srcID = self::getEntityId($state['Source']);
		} else {
			$srcID = '';
		}

		if (array_key_exists('Destination', $state)) {
			$dstID = self::getEntityId($state['Destination']);
		} else {
			$dstID = '';
		}

		$uidData = 'uidhashbase' . $secretSalt;
		$uidData .= strlen($srcID) . ':' . $srcID;
		$uidData .= strlen($dstID) . ':' . $dstID;
		$uidData .= strlen($userID) . ':' . $userID;
		$uidData .= $secretSalt;

		$uid = hash('sha1', $uidData);

		if ($this->generateNameId) {
			// Convert the targeted ID to a SAML 2.0 name identifier element
			$nameId = array(
				'Format' => SAML2_Const::NAMEID_PERSISTENT,
				'Value' => $uid,
			);

			if (isset($state['Source']['entityid'])) {
				$nameId['NameQualifier'] = $state['Source']['entityid'];
			}
			if (isset($state['Destination']['entityid'])) {
				$nameId['SPNameQualifier'] = $state['Destination']['entityid'];
			}

			$doc = SAML2_DOMDocumentFactory::create();
			$root = $doc->createElement('root');
			$doc->appendChild($root);

			SAML2_Utils::addNameId($root, $nameId);
			$uid = $doc->saveXML($root->firstChild);
		}

		$state['Attributes']['eduPersonTargetedID'] = array($uid);
	}


	/**
	 * Generate ID from entity metadata.
	 *
	 * This function takes in the metadata of an entity, and attempts to generate
	 * an unique identifier based on that.
	 *
	 * @param array $metadata  The metadata of the entity.
	 * @return string  The unique identifier for the entity.
	 */
	private static function getEntityId($metadata) {
		assert('is_array($metadata)');

		$id = '';

		if (array_key_exists('metadata-set', $metadata)) {
			$set = $metadata['metadata-set'];
			$id .= 'set' . strlen($set) . ':' . $set;
		}

		if (array_key_exists('entityid', $metadata)) {
			$entityid = $metadata['entityid'];
			$id .= 'set' . strlen($entityid) . ':' . $entityid;
		}

		return $id;
	}

}
