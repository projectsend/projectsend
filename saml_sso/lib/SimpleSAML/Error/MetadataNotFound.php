<?php

/**
 * Error for missing metadata.
 *
 * @package SimpleSAMLphp
 */
class SimpleSAML_Error_MetadataNotFound extends SimpleSAML_Error_Error {


	/**
	 * Create the error
	 *
	 * @param string $entityId  The entityID we were unable to locate.
	 */
	public function __construct($entityId) {
		assert('is_string($entityId)');

		$this->includeTemplate = 'core:no_metadata.tpl.php';
		parent::__construct(array(
				'METADATANOTFOUND',
				'%ENTITYID%' => htmlspecialchars(var_export($entityId, TRUE))
		));
	}

}
