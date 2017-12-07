#!/usr/bin/env php
<?php


function _readline($prompt = '') {
    echo $prompt;
    return rtrim( fgets( STDIN ), "\n" );
}

try {


	// This is the base directory of the SimpleSAMLphp installation
	$baseDir = dirname(dirname(dirname(dirname(__FILE__))));

	// Add library autoloader.
	require_once($baseDir . '/lib/_autoload.php');


	require_once(dirname(dirname(__FILE__)) . '/libextinc/OAuth.php');

	// Needed in order to make session_start to be called before output is printed.
	$session = SimpleSAML_Session::getSessionFromRequest();

	$baseurl = (isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : 'http://mars.foodle.local/simplesaml');
	$key = (isset($_SERVER['argv'][2]) ? $_SERVER['argv'][2] : 'key');
	$secret = (isset($_SERVER['argv'][3]) ? $_SERVER['argv'][3] : 'secret');

	echo 'Welcome to the OAuth CLI client' . "\n";
	$consumer = new sspmod_oauth_Consumer($key, $secret);

	// Get the request token
	$requestToken = $consumer->getRequestToken($baseurl . '/module.php/oauth/requestToken.php');
	echo "Got a request token from the OAuth service provider [" . $requestToken->key . "] with the secret [" . $requestToken->secret . "]\n";

	// Authorize the request token
	$url = $consumer->getAuthorizeRequest($baseurl . '/module.php/oauth/authorize.php', $requestToken, FALSE);

	echo('Go to this URL to authenticate/authorize the request: ' . $url . "\n");
	system('open ' . $url);

	_readline('Click enter when you have completed the authorization step using your web browser...');

	// Replace the request token with an access token
	$accessToken = $consumer->getAccessToken( $baseurl . '/module.php/oauth/accessToken.php', $requestToken);
	echo "Got an access token from the OAuth service provider [" . $accessToken->key . "] with the secret [" . $accessToken->secret . "]\n";

	$userdata = $consumer->getUserInfo($baseurl . '/module.php/oauth/getUserInfo.php', $accessToken);


	echo 'You are successfully authenticated to this Command Line CLI. ' . "\n";
	echo 'Got data [' . join(', ', array_keys($userdata)) . ']' . "\n";
	echo 'Your user ID is :  ' . $userdata['eduPersonPrincipalName'][0] . "\n";

} catch(Exception $e) {
	echo 'Error occurred: ' . $e->getMessage() . "\n\n";
}






