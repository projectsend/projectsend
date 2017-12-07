<?php
/*
 * @author Andreas Åkre Solberg <andreas.solberg@uninett.no>
 * @package SimpleSAMLphp
 */
class sspmod_metarefresh_MetaLoader {


	private $expire;
	private $metadata;
	private $oldMetadataSrc;
	private $stateFile;
	private $changed;
	private $types = array(
		'saml20-idp-remote',
		'saml20-sp-remote',
		'shib13-idp-remote',
		'shib13-sp-remote',
		'attributeauthority-remote'
	);


	/**
	 * Constructor
	 *
	 * @param array $sources 	Sources...
	 * @param 
	 */
	public function __construct($expire = NULL, $stateFile = NULL, $oldMetadataSrc = NULL) {
		$this->expire = $expire;
		$this->metadata = array();
		$this->oldMetadataSrc = $oldMetadataSrc;
		$this->stateFile = $stateFile;
		$this->changed = FALSE;

		// Read file containing $state from disk
		if(is_readable($stateFile)) {
			require($stateFile);
		}

		$this->state = (isset($state)) ? $state : array();

	}


	/**
	 * Get the types of entities that will be loaded.
	 *
	 * @return array The entity types allowed.
	 */
	public function getTypes()
	{
		return $this->types;
	}


	/**
	 * Set the types of entities that will be loaded.
	 *
	 * @param string|array $types Either a string with the name of one single type allowed, or an array with a list of
	 * types. Pass an empty array to reset to all types of entities.
	 */
	public function setTypes($types)
	{
		if (!is_array($types)) {
			$types = array($types);
		}
		$this->types = $types;
	}


	/**
	 * This function processes a SAML metadata file.
	 *
	 * @param $source
	 */
	public function loadSource($source) {

		if (preg_match('@^https?://@i', $source['src'])) {
			// Build new HTTP context
			$context = $this->createContext($source);

			// GET!
			try {
				list($data, $responseHeaders) = \SimpleSAML\Utils\HTTP::fetch($source['src'], $context, TRUE);
			} catch(Exception $e) {
				SimpleSAML_Logger::warning('metarefresh: ' . $e->getMessage());
			}

			// We have response headers, so the request succeeded
			if(!isset($responseHeaders)) {
				// No response headers, this means the request failed in some way, so re-use old data
				SimpleSAML_Logger::debug('No response from ' . $source['src'] . ' - attempting to re-use cached metadata');
				$this->addCachedMetadata($source);
				return;
			} elseif(preg_match('@^HTTP/1\.[01]\s304\s@', $responseHeaders[0])) {
				// 304 response
				SimpleSAML_Logger::debug('Received HTTP 304 (Not Modified) - attempting to re-use cached metadata');
				$this->addCachedMetadata($source);
				return;
			} elseif(!preg_match('@^HTTP/1\.[01]\s200\s@', $responseHeaders[0])) {
				// Other error
				SimpleSAML_Logger::debug('Error from ' . $source['src'] . ' - attempting to re-use cached metadata');
				$this->addCachedMetadata($source);
				return;
			}
		} else {
			// Local file.
			$data = file_get_contents($source['src']);
			$responseHeaders = NULL;
		}

		// Everything OK. Proceed.
		if (isset($source['conditionalGET']) && $source['conditionalGET']) {
			// Stale or no metadata, so a fresh copy
			SimpleSAML_Logger::debug('Downloaded fresh copy');
		}

		try {
			$entities = $this->loadXML($data, $source);
		} catch(Exception $e) {
			SimpleSAML_Logger::debug('XML parser error when parsing ' . $source['src'] . ' - attempting to re-use cached metadata');
			$this->addCachedMetadata($source);
			return;
		}

		foreach($entities as $entity) {

			if(isset($source['blacklist'])) {
				if(!empty($source['blacklist']) && in_array($entity->getEntityID(), $source['blacklist'])) {
					SimpleSAML_Logger::info('Skipping "' .  $entity->getEntityID() . '" - blacklisted.' . "\n");
					continue;
				}
			}

			if(isset($source['whitelist'])) {
				if(!empty($source['whitelist']) && !in_array($entity->getEntityID(), $source['whitelist'])) {
					SimpleSAML_Logger::info('Skipping "' .  $entity->getEntityID() . '" - not in the whitelist.' . "\n");
					continue;
				}
			}

			if(array_key_exists('certificates', $source) && $source['certificates'] !== NULL) {
				if(!$entity->validateSignature($source['certificates'])) {
					SimpleSAML_Logger::info('Skipping "' . $entity->getEntityId() . '" - could not verify signature using certificate.' . "\n");
					continue;
				}
			}

			if(array_key_exists('validateFingerprint', $source) && $source['validateFingerprint'] !== NULL) {
				if(!array_key_exists('certificates', $source) || $source['certificates'] == NULL) {
					if(!$entity->validateFingerprint($source['validateFingerprint'])) {
						SimpleSAML_Logger::info('Skipping "' . $entity->getEntityId() . '" - could not verify signature using fingerprint.' . "\n");
						continue;
					}
				} else {
					SimpleSAML_Logger::info('Skipping validation with fingerprint since option certificate is set.' . "\n");
				}
			}

			$template = NULL;
			if (array_key_exists('template', $source)) $template = $source['template'];

			$this->addMetadata($source['src'], $entity->getMetadata1xSP(), 'shib13-sp-remote', $template);
			$this->addMetadata($source['src'], $entity->getMetadata1xIdP(), 'shib13-idp-remote', $template);
			$this->addMetadata($source['src'], $entity->getMetadata20SP(), 'saml20-sp-remote', $template);
			$this->addMetadata($source['src'], $entity->getMetadata20IdP(), 'saml20-idp-remote', $template);
			$attributeAuthorities = $entity->getAttributeAuthorities();
			if (!empty($attributeAuthorities)) {
				$this->addMetadata($source['src'], $attributeAuthorities[0], 'attributeauthority-remote', $template);
			}
		}

		$this->saveState($source, $responseHeaders);
	}

	/**
	 * Create HTTP context, with any available caches taken into account
	 */
	private function createContext($source) {

		$context = NULL;

		$config = SimpleSAML_Configuration::getInstance();
		$name = $config->getString('technicalcontact_name', NULL);
		$mail = $config->getString('technicalcontact_email', NULL);

		$rawheader = "User-Agent: SimpleSAMLphp metarefresh, run by $name <$mail>\r\n";

		if (isset($source['conditionalGET']) && $source['conditionalGET']) {
			if(array_key_exists($source['src'], $this->state)) {

				$sourceState = $this->state[$source['src']];

				if(isset($sourceState['last-modified'])) {
					$rawheader .= 'If-Modified-Since: ' . $sourceState['last-modified'] . "\r\n";
				}

				if(isset($sourceState['etag'])) {
					$rawheader .= 'If-None-Match: ' . $sourceState['etag'] . "\r\n";
				}
			}
		}

		return array('http' => array('header' => $rawheader));
	}


	private function addCachedMetadata($source) {
		if(isset($this->oldMetadataSrc)) {
			foreach($this->types as $type) {
				foreach($this->oldMetadataSrc->getMetadataSet($type) as $entity) {
					if(array_key_exists('metarefresh:src', $entity)) {
						if($entity['metarefresh:src'] == $source['src']) {
							$this->addMetadata($source['src'], $entity, $type);
						}
					}
				}
			}
		}
	}


	/**
	 * Store caching state data for a source
	 */
	private function saveState($source, $responseHeaders) {

		if (isset($source['conditionalGET']) && $source['conditionalGET']) {

			// Headers section
			$candidates = array('last-modified', 'etag');

			foreach($candidates as $candidate) {
				if(array_key_exists($candidate, $responseHeaders)) {
					$this->state[$source['src']][$candidate] = $responseHeaders[$candidate];
				}
			}

			if(!empty($this->state[$source['src']])) {
				// Timestamp when this src was requested.
				$this->state[$source['src']]['requested_at'] = $this->getTime();

				$this->changed = TRUE;
			}
		}
	}


	/**
	 * Parse XML metadata and return entities
	 */
	private function loadXML($data, $source) {
		$entities = array();
		try {
			$doc = SAML2_DOMDocumentFactory::fromString($data);
		} catch (Exception $e) {
			throw new Exception('Failed to read XML from ' . $source['src']);
		}
		if ($doc->documentElement === NULL) {
			throw new Exception('Opened file is not an XML document: ' . $source['src']);
		}
		$entities = SimpleSAML_Metadata_SAMLParser::parseDescriptorsElement($doc->documentElement);
		return $entities;
	}


	/**
	 * This function writes the state array back to disk
	 */
	public function writeState() {
		if($this->changed) {
			SimpleSAML_Logger::debug('Writing: ' . $this->stateFile);
            SimpleSAML\Utils\System::writeFile(
				$this->stateFile,
				"<?php\n/* This file was generated by the metarefresh module at ".$this->getTime() . ".\n".
				" Do not update it manually as it will get overwritten. */\n".
				'$state = ' . var_export($this->state, TRUE) . ";\n?>\n",
				0644
			);
		}
	}


	/**
	 * This function writes the metadata to stdout.
	 */
	public function dumpMetadataStdOut() {
	
		foreach($this->metadata as $category => $elements) {
	
			echo('/* The following data should be added to metadata/' . $category . '.php. */' . "\n");
	
	
			foreach($elements as $m) {
				$filename = $m['filename'];
				$entityID = $m['metadata']['entityid'];
	
				echo("\n");
				echo('/* The following metadata was generated from ' . $filename . ' on ' . $this->getTime() . '. */' . "\n");
				echo('$metadata[\'' . addslashes($entityID) . '\'] = ' . var_export($m['metadata'], TRUE) . ';' . "\n");
			}
	
	
			echo("\n");
			echo('/* End of data which should be added to metadata/' . $category . '.php. */' . "\n");
			echo("\n");
		}
	}

	
	/**
	 * This function adds metadata from the specified file to the list of metadata.
	 * This function will return without making any changes if $metadata is NULL.
	 *
	 * @param $filename The filename the metadata comes from.
	 * @param $metadata The metadata.
	 * @param $type The metadata type.
	 */
	private function addMetadata($filename, $metadata, $type, $template = NULL) {
	
		if($metadata === NULL) {
			return;
		}
	
		if (isset($template)) {
			$metadata = array_merge($metadata, $template);
		}
	
		$metadata['metarefresh:src'] = $filename;
		if(!array_key_exists($type, $this->metadata)) {
			$this->metadata[$type] = array();
		}
		
		// If expire is defined in constructor...
		if (!empty($this->expire)) {
			
			// If expire is already in metadata
			if (array_key_exists('expire', $metadata)) {
			
				// Override metadata expire with more restrictive global config-
				if ($this->expire < $metadata['expire'])
					$metadata['expire'] = $this->expire;
					
			// If expire is not already in metadata use global config
			} else {
				$metadata['expire'] = $this->expire;
			}
		}
		

	
		$this->metadata[$type][] = array('filename' => $filename, 'metadata' => $metadata);
	}


	/**
	 * This function writes the metadata to an ARP file
	 */
	function writeARPfile($config) {
		
		assert('is_a($config, \'SimpleSAML_Configuration\')');
		
		$arpfile = $config->getValue('arpfile');
		$types = array('saml20-sp-remote');
		
		$md = array();
		foreach($this->metadata as $category => $elements) {
			if (!in_array($category, $types)) continue;
			$md = array_merge($md, $elements);
		}
		
		// $metadata, $attributemap, $prefix, $suffix
		$arp = new sspmod_metarefresh_ARP($md, 
			$config->getValue('attributemap', ''),  
			$config->getValue('prefix', ''),  
			$config->getValue('suffix', '')
		);
		
		
		$arpxml = $arp->getXML();

		SimpleSAML_Logger::info('Writing ARP file: ' . $arpfile . "\n");
		file_put_contents($arpfile, $arpxml);

	}
	
	
	/**
	 * This function writes the metadata to to separate files in the output directory.
	 */
	function writeMetadataFiles($outputDir) {
	
		while(strlen($outputDir) > 0 && $outputDir[strlen($outputDir) - 1] === '/') {
			$outputDir = substr($outputDir, 0, strlen($outputDir) - 1);
		}
	
		if(!file_exists($outputDir)) {
			SimpleSAML_Logger::info('Creating directory: ' . $outputDir . "\n");
			$res = @mkdir($outputDir, 0777, TRUE);
			if ($res === FALSE) {
				throw new Exception('Error creating directory: ' . $outputDir);
			}
		}
	
		foreach($this->types as $type) {

			$filename = $outputDir . '/' . $type . '.php';

			if(array_key_exists($type, $this->metadata)) {
				$elements = $this->metadata[$type];
				SimpleSAML_Logger::debug('Writing: ' . $filename);

				$content  = '<?php' . "\n" . '/* This file was generated by the metarefresh module at '. $this->getTime() . "\n";
				$content .= ' Do not update it manually as it will get overwritten' . "\n" . '*/' . "\n";

				foreach($elements as $m) {
					$entityID = $m['metadata']['entityid'];
					$content .= "\n";
					$content .= '$metadata[\'' . addslashes($entityID) . '\'] = ' . var_export($m['metadata'], TRUE) . ';' . "\n";
				}

				$content .= "\n" . '?>';

                SimpleSAML\Utils\System::writeFile($filename, $content, 0644);
			} elseif(is_file($filename)) {
				if(unlink($filename)) {
					SimpleSAML_Logger::debug('Deleting stale metadata file: ' . $filename);
				} else {
					SimpleSAML_Logger::warning('Could not delete stale metadata file: ' . $filename);
				}
			}
		}
	}


	/**
	 * Save metadata for loading with the 'serialize' metadata loader.
	 *
	 * @param string $outputDir  The directory we should save the metadata to.
	 */
	public function writeMetadataSerialize($outputDir) {
		assert('is_string($outputDir)');

		$metaHandler = new SimpleSAML_Metadata_MetaDataStorageHandlerSerialize(array('directory' => $outputDir));

		/* First we add all the metadata entries to the metadata handler. */
		foreach ($this->metadata as $set => $elements) {
			foreach ($elements as $m) {
				$entityId = $m['metadata']['entityid'];

				SimpleSAML_Logger::debug('metarefresh: Add metadata entry ' .
					var_export($entityId, TRUE) . ' in set ' . var_export($set, TRUE) . '.');
				$metaHandler->saveMetadata($entityId, $set, $m['metadata']);
			}
		}

		/* Then we delete old entries which should no longer exist. */
		$ct = time();
		foreach ($metaHandler->getMetadataSets() as $set) {
			foreach ($metaHandler->getMetadataSet($set) as $entityId => $metadata) {
				if (!array_key_exists('expire', $metadata)) {
					SimpleSAML_Logger::warning('metarefresh: Metadata entry without expire timestamp: ' . var_export($entityId, TRUE) . 
						' in set ' . var_export($set, TRUE) . '.');
					continue;
				}
				if ($metadata['expire'] > $ct) {
					continue;
				}
				SimpleSAML_Logger::debug('metarefresh: ' . $entityId . ' expired ' . date('l jS \of F Y h:i:s A', $metadata['expire']) );
				SimpleSAML_Logger::debug('metarefresh: Delete expired metadata entry ' .
					var_export($entityId, TRUE) . ' in set ' . var_export($set, TRUE) . '. (' . ($ct - $metadata['expire']) . ' sec)');
				$metaHandler->deleteMetadata($entityId, $set);
			}
		}
	}


	private function getTime() {
		/* The current date, as a string. */
		date_default_timezone_set('UTC');
		$when = date('Y-m-d\\TH:i:s\\Z');
		return $when;
	}

}
