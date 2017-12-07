<?php

/**
 * This page provides a way to create a redirect to a POST request.
 *
 * @package SimpleSAMLphp
 */

if (array_key_exists('RedirId', $_REQUEST)) {
	$postId = $_REQUEST['RedirId'];
	$session = SimpleSAML_Session::getSessionFromRequest();
} elseif (array_key_exists('RedirInfo', $_REQUEST)) {
	$encData = base64_decode($_REQUEST['RedirInfo']);

	if (empty($encData)) {
		throw new SimpleSAML_Error_BadRequest('Invalid RedirInfo data.');
	}

	list($sessionId, $postId) = explode(':', SimpleSAML\Utils\Crypto::aesDecrypt($encData));

	if (empty($sessionId) || empty($postId)) {
		throw new SimpleSAML_Error_BadRequest('Invalid session info data.');
	}

	$session = SimpleSAML_Session::getSession($sessionId);
} else {
	throw new SimpleSAML_Error_BadRequest('Missing redirection info parameter.');
}

if ($session === NULL) {
	throw new Exception('Unable to load session.');
}

$postData = $session->getData('core_postdatalink', $postId);

if ($postData === NULL) {
	// The post data is missing, probably because it timed out
	throw new Exception('The POST data we should restore was lost.');
}

$session->deleteData('core_postdatalink', $postId);

assert('is_array($postData)');
assert('array_key_exists("url", $postData)');
assert('array_key_exists("post", $postData)');

$config = SimpleSAML_Configuration::getInstance();
$template = new SimpleSAML_XHTML_Template($config, 'post.php');
$template->data['destination'] = $postData['url'];
$template->data['post'] = $postData['post'];
$template->show();
exit(0);
