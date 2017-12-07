<?php

/**
 * Hook to add the metadata for hosted entities to the frontpage.
 *
 * @param array &$metadataHosted  The metadata links for hosted metadata on the frontpage.
 */
function saml_hook_metadata_hosted(&$metadataHosted) {
	assert('is_array($metadataHosted)');

	$sources = SimpleSAML_Auth_Source::getSourcesOfType('saml:SP');

	foreach ($sources as $source) {

		$metadata = $source->getMetadata();

		$name = $metadata->getValue('name', NULL);
		if ($name === NULL) {
			$name = $metadata->getValue('OrganizationDisplayName', NULL);
		}
		if ($name === NULL) {
			$name = $source->getAuthID();
		}

		$md = array(
			'entityid' => $source->getEntityId(),
			'metadata-index' => $source->getEntityId(),
			'metadata-set' => 'saml20-sp-hosted',
			'metadata-url' => $source->getMetadataURL() . '?output=xhtml',
			'name' => $name,
		);

		$metadataHosted[] = $md;
	}

}
