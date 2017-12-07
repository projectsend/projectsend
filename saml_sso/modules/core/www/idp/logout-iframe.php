<?php

if (!isset($_REQUEST['id'])) {
	throw new SimpleSAML_Error_BadRequest('Missing required parameter: id');
}

if (isset($_REQUEST['type'])) {
	$type = (string)$_REQUEST['type'];
	if (!in_array($type, array('init', 'js', 'nojs', 'embed'), TRUE)) {
		throw new SimpleSAML_Error_BadRequest('Invalid value for type.');
	}
} else {
	$type = 'init';
}

if ($type !== 'embed' && $type !== 'async') {
	SimpleSAML_Logger::stats('slo-iframe ' . $type);
	SimpleSAML_Stats::log('core:idp:logout-iframe:page', array('type' => $type));
}

$state = SimpleSAML_Auth_State::loadState($_REQUEST['id'], 'core:Logout-IFrame');
$idp = SimpleSAML_IdP::getByState($state);

if ($type !== 'init') {
	// Update association state

	$associations = $idp->getAssociations();

	foreach ($state['core:Logout-IFrame:Associations'] as $assocId => &$sp) {

		$spId = sha1($assocId);

		// Move SPs from 'onhold' to 'inprogress'
		if ($sp['core:Logout-IFrame:State'] === 'onhold') {
			$sp['core:Logout-IFrame:State'] = 'inprogress';
		}

		/* Check for update through request. */
		if (isset($_REQUEST[$spId])) {
			$s = $_REQUEST[$spId];
			if ($s == 'completed' || $s == 'failed') {
				$sp['core:Logout-IFrame:State'] = $s;
			}
		}

		/* Check for timeout. */
		if (isset($sp['core:Logout-IFrame:Timeout']) && $sp['core:Logout-IFrame:Timeout'] < time()) {
			if ($sp['core:Logout-IFrame:State'] === 'inprogress') {
				$sp['core:Logout-IFrame:State'] = 'failed';
			}
		}

		/* In case we are refreshing a page. */
		if (!isset($associations[$assocId])) {
			$sp['core:Logout-IFrame:State'] = 'completed';
		}

		/* Update the IdP. */
		if ($sp['core:Logout-IFrame:State'] === 'completed') {
			$idp->terminateAssociation($assocId);
		}

		if (!isset($sp['core:Logout-IFrame:Timeout'])) {
			if (method_exists($sp['Handler'], 'getAssociationConfig')) {
				$assocIdP = SimpleSAML_IdP::getByState($sp);
				$assocConfig = call_user_func(array($sp['Handler'], 'getAssociationConfig'), $assocIdP, $sp);
				$sp['core:Logout-IFrame:Timeout'] = $assocConfig->getInteger('core:logout-timeout', 5) + time();
			} else {
				$sp['core:Logout-IFrame:Timeout'] = time() + 5;
			}
		}
	}
}

if ($type === 'js' || $type === 'nojs') {
	foreach ($state['core:Logout-IFrame:Associations'] as $assocId => &$sp) {

		if ($sp['core:Logout-IFrame:State'] !== 'inprogress') {
			/* This SP isn't logging out. */
			continue;
		}

		try {
			$assocIdP = SimpleSAML_IdP::getByState($sp);
			$url = call_user_func(array($sp['Handler'], 'getLogoutURL'), $assocIdP, $sp, NULL);
			$sp['core:Logout-IFrame:URL'] = $url;
		} catch (Exception $e) {
			$sp['core:Logout-IFrame:State'] = 'failed';
		}
	}
}

$id = SimpleSAML_Auth_State::saveState($state, 'core:Logout-IFrame');

$globalConfig = SimpleSAML_Configuration::getInstance();

if ($type === 'nojs') {
	$t = new SimpleSAML_XHTML_Template($globalConfig, 'core:logout-iframe-wrapper.php');
	$t->data['id'] = $id;
	$t->data['SPs'] = $state['core:Logout-IFrame:Associations'];
	$t->show();
	exit(0);
}

$t = new SimpleSAML_XHTML_Template($globalConfig, 'core:logout-iframe.php');
$t->data['id'] = $id;
$t->data['type'] = $type;
$t->data['from'] = $state['core:Logout-IFrame:From'];
$t->data['SPs'] = $state['core:Logout-IFrame:Associations'];
$t->data['jquery'] = array('core' => TRUE, 'ui' => FALSE, 'css' => FALSE);
$t->show();
exit(0);
