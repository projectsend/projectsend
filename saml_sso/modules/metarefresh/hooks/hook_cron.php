<?php
/**
 * Hook to run a cron job.
 *
 * @param array &$croninfo  Output
 */
function metarefresh_hook_cron(&$croninfo) {
	assert('is_array($croninfo)');
	assert('array_key_exists("summary", $croninfo)');
	assert('array_key_exists("tag", $croninfo)');

	SimpleSAML_Logger::info('cron [metarefresh]: Running cron in cron tag [' . $croninfo['tag'] . '] ');

	try {
		$config = SimpleSAML_Configuration::getInstance();
		$mconfig = SimpleSAML_Configuration::getOptionalConfig('config-metarefresh.php');

		$sets = $mconfig->getConfigList('sets', array());
		$stateFile = $config->getPathValue('datadir', 'data/') . 'metarefresh-state.php';

		foreach ($sets AS $setkey => $set) {
			// Only process sets where cron matches the current cron tag
			$cronTags = $set->getArray('cron');
			if (!in_array($croninfo['tag'], $cronTags)) continue;

			SimpleSAML_Logger::info('cron [metarefresh]: Executing set [' . $setkey . ']');

			$expireAfter = $set->getInteger('expireAfter', NULL);
			if ($expireAfter !== NULL) {
				$expire = time() + $expireAfter;
			} else {
				$expire = NULL;
			}

			$outputDir = $set->getString('outputDir');
			$outputDir = $config->resolvePath($outputDir);
			$outputFormat = $set->getValueValidate('outputFormat', array('flatfile', 'serialize'), 'flatfile');

			$oldMetadataSrc = SimpleSAML_Metadata_MetaDataStorageSource::getSource(array(
				'type' => $outputFormat,
				'directory' => $outputDir,
			));

			$metaloader = new sspmod_metarefresh_MetaLoader($expire, $stateFile, $oldMetadataSrc);

			# Get global blacklist, whitelist and caching info
			$blacklist = $mconfig->getArray('blacklist', array());
			$whitelist = $mconfig->getArray('whitelist', array());
			$conditionalGET = $mconfig->getBoolean('conditionalGET', FALSE);

			// get global type filters
			$available_types = array(
				'saml20-idp-remote',
				'saml20-sp-remote',
				'shib13-idp-remote',
				'shib13-sp-remote',
				'attributeauthority-remote'
			);
			$set_types = $set->getArrayize('types', $available_types);

			foreach($set->getArray('sources') AS $source) {

				// filter metadata by type of entity
				if (isset($source['types'])) {
					$metaloader->setTypes($source['types']);
				} else {
					$metaloader->setTypes($set_types);
				}

				# Merge global and src specific blacklists
				if(isset($source['blacklist'])) {
					$source['blacklist'] = array_unique(array_merge($source['blacklist'], $blacklist));
				} else {
					$source['blacklist'] = $blacklist;
				}

				# Merge global and src specific whitelists
				if(isset($source['whitelist'])) {
					$source['whitelist'] = array_unique(array_merge($source['whitelist'], $whitelist));
				} else {
					$source['whitelist'] = $whitelist;
				}

				# Let src specific conditionalGET override global one
				if(!isset($source['conditionalGET'])) {
					$source['conditionalGET'] = $conditionalGET;
				}

				SimpleSAML_Logger::debug('cron [metarefresh]: In set [' . $setkey . '] loading source ['  . $source['src'] . ']');
				$metaloader->loadSource($source);
			}

			// Write state information back to disk
			$metaloader->writeState();

			switch ($outputFormat) {
				case 'flatfile':
					$metaloader->writeMetadataFiles($outputDir);
					break;
				case 'serialize':
					$metaloader->writeMetadataSerialize($outputDir);
					break;
			}

			if ($set->hasValue('arp')) {
				$arpconfig = SimpleSAML_Configuration::loadFromArray($set->getValue('arp'));
				$metaloader->writeARPfile($arpconfig);
			}
		}

	} catch (Exception $e) {
		$croninfo['summary'][] = 'Error during metarefresh: ' . $e->getMessage();
	}
}
