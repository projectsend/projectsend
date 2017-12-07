<?php

class sspmod_statistics_Statistics_FieldPresentation_Entity extends sspmod_statistics_Statistics_FieldPresentation_Base {
	
	
	public function getPresentation() {
		$mh = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
		$metadata = $mh->getList($this->config);
		
		$translation = array('_' => 'All services');
		foreach($this->fields AS $field) {
			if (array_key_exists($field, $metadata)) {
				if (array_key_exists('name', $metadata[$field])) {
					$translation[$field] = $this->template->t($metadata[$field]['name'], array(), FALSE);
				}
			}
		}
		return $translation;
	}

	
	
}