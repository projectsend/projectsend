<?php

$config = SimpleSAML_Configuration::getInstance();
$mconfig = SimpleSAML_Configuration::getOptionalConfig('config-metarefresh.php');

SimpleSAML\Utils\Auth::requireAdmin();

SimpleSAML_Logger::setCaptureLog(TRUE);


$sets = $mconfig->getConfigList('sets', array());

foreach ($sets AS $setkey => $set) {

	SimpleSAML_Logger::info('[metarefresh]: Executing set [' . $setkey . ']');

	try {
		

		$expireAfter = $set->getInteger('expireAfter', NULL);
		if ($expireAfter !== NULL) {
			$expire = time() + $expireAfter;
		} else {
			$expire = NULL;
		}

		$metaloader = new sspmod_metarefresh_MetaLoader($expire);

		# Get global black/whitelists
		$blacklist = $mconfig->getArray('blacklist', array());
		$whitelist = $mconfig->getArray('whitelist', array());

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

			SimpleSAML_Logger::debug('[metarefresh]: In set [' . $setkey . '] loading source ['  . $source['src'] . ']');
			$metaloader->loadSource($source);
		}

		$outputDir = $set->getString('outputDir');
		$outputDir = $config->resolvePath($outputDir);

		$outputFormat = $set->getValueValidate('outputFormat', array('flatfile', 'serialize'), 'flatfile');
		switch ($outputFormat) {
			case 'flatfile':
				$metaloader->writeMetadataFiles($outputDir);
				break;
			case 'serialize':
				$metaloader->writeMetadataSerialize($outputDir);
				break;
		}
	} catch (Exception $e) {
		$e = SimpleSAML_Error_Exception::fromException($e);
		$e->logWarning();
	}
	

}

$logentries = SimpleSAML_Logger::getCapturedLog();

$t = new SimpleSAML_XHTML_Template($config, 'metarefresh:fetch.tpl.php');
$t->data['logentries'] = $logentries;
$t->show();