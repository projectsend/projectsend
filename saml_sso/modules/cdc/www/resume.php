<?php


if (!array_key_exists('domain', $_REQUEST)) {
	throw new SimpleSAML_Error_BadRequest('Missing domain to CDC resume handler.');
}

$domain = (string)$_REQUEST['domain'];
$client = new sspmod_cdc_Client($domain);

$response = $client->getResponse();
if ($response === NULL) {
	throw new SimpleSAML_Error_BadRequest('Missing CDC response to CDC resume handler.');
}

if (!isset($response['id'])) {
	throw new SimpleSAML_Error_BadRequest('CDCResponse without id.');
}
$state = SimpleSAML_Auth_State::loadState($response['id'], 'cdc:resume');

SimpleSAML_Auth_ProcessingChain::resumeProcessing($state);
