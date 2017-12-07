<?php

if (!isset($_REQUEST['id'])) {
	throw new SimpleSAML_Error_BadRequest('Missing required parameter: id');
}
$state = SimpleSAML_Auth_State::loadState($_REQUEST['id'], 'core:Logout-IFrame');
$idp = SimpleSAML_IdP::getByState($state);

$associations = $idp->getAssociations();

if (!isset($_REQUEST['cancel'])) {
	SimpleSAML_Logger::stats('slo-iframe done');
	SimpleSAML_Stats::log('core:idp:logout-iframe:page', array('type' => 'done'));
	$SPs = $state['core:Logout-IFrame:Associations'];
} else {
	// User skipped global logout
	SimpleSAML_Logger::stats('slo-iframe skip');
	SimpleSAML_Stats::log('core:idp:logout-iframe:page', array('type' => 'skip'));
	$SPs = array(); // No SPs should have been logged out
	$state['core:Failed'] = TRUE; /* Mark as partial logout. */
}

/* Find the status of all SPs. */
foreach ($SPs as $assocId => &$sp) {

	$spId = 'logout-iframe-' . sha1($assocId);

	if (isset($_REQUEST[$spId])) {
		$spStatus = $_REQUEST[$spId];
		if ($spStatus === 'completed' || $spStatus === 'failed') {
			$sp['core:Logout-IFrame:State'] = $spStatus;
		}
	}

	if (!isset($associations[$assocId])) {
		$sp['core:Logout-IFrame:State'] = 'completed';
	}

}


/* Terminate the associations. */
foreach ($SPs as $assocId => $sp) {

	if ($sp['core:Logout-IFrame:State'] === 'completed') {
		$idp->terminateAssociation($assocId);
	} else {
		SimpleSAML_Logger::warning('Unable to terminate association with ' . var_export($assocId, TRUE) . '.');
		if (isset($sp['saml:entityID'])) {
			$spId = $sp['saml:entityID'];
		} else {
			$spId = $assocId;
		}
		SimpleSAML_Logger::stats('slo-iframe-fail ' . $spId);
		SimpleSAML_Stats::log('core:idp:logout-iframe:spfail', array('sp' => $spId));
		$state['core:Failed'] = TRUE;
	}

}


/* We are done. */
$idp->finishLogout($state);
