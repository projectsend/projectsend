#!/usr/bin/env php
<?php

// This is the base directory of the SimpleSAMLphp installation
$baseDir = dirname(dirname(__FILE__));

// Add library autoloader
require_once($baseDir . '/lib/_autoload.php');

if (count($argv) !== 3) {
	echo "Wrong number of parameters. Run:   " . $argv[0] . " [pulldef,push,pull] filename\n"; exit;
}

$action = $argv[1];
$file = $argv[2];

$translationconfig = SimpleSAML_Configuration::getConfig('translation.php');

$application = $translationconfig->getString('application', 'simplesamlphp');
$base = $translationconfig->getString('baseurl') . '/module.php/translationportal/';

if (!preg_match('/^(.*?)(?:\.(definition|translation))?\.(json|php)/', $file, $match))
	throw new Exception('Illlegal file name. Must end on (definition|translation).json');
$fileWithoutExt = $match[1];
if (!empty($match[2])) {
	$type = $match[2];
} else {
	$type = 'definition';
}

$basefile = basename($fileWithoutExt);


echo 'Action: [' . $action. ']' . "\n";
echo 'Application: [' . $application. ']' . "\n";
echo 'File orig: [' . $file . ']'. "\n";
echo 'File base: [' . $basefile . ']'. "\n";


switch($action) {
	case 'pulldef':
		
		$content = \SimpleSAML\Utils\HTTP::fetch($base . 'export.php?aid=' . $application . '&type=def&file=' . $basefile);
		file_put_contents($fileWithoutExt . '.definition.json' , $content);
		break;
		
	case 'pull':

		try {
			$content = \SimpleSAML\Utils\HTTP::fetch($base . 'export.php?aid=' . $application . '&type=translation&file=' . $basefile);
			file_put_contents($fileWithoutExt . '.translation.json' , $content);
		}
		catch (SimpleSAML_Error_Exception $e) {
			echo 'Translation unavailable for ' . $basefile; 
			SimpleSAML_Logger::warning("Translation unavailable for $basefile in $base: " . $e->getMessage());
		}
		break;
	
	case 'push':

		push($file, $basefile, $application, $type);
		
		break;
		
	case 'convert':

		include($file);
		$definition = json_format(convert_definition($lang));
		$translation = json_format(convert_translation($lang)) . "\n";
		file_put_contents($fileWithoutExt . '.definition.json' , $definition);
		file_put_contents($fileWithoutExt . '.translation.json' , $translation);
		break;

	
	default:
		throw new Exception('Unknown action [' . $action . ']');
}

function ssp_readline($prompt = '') {
    echo $prompt;
    return rtrim( fgets( STDIN ), "\n" );
}

function convert_definition($data) {
	$new = array();
	foreach($data AS $key => $value) {
		$new[$key] = array('en' => $value['en']);
	}
	return $new;
}

function convert_translation($data) {
	foreach ($data as &$value) {
		unset($value['en']);
	}
	return $data;
}

function push($file, $fileWithoutExt, $aid, $type) {
	
	if (!file_exists($file)) throw new Exception('Could not find file: ' . $file);
	
	$fileContent = file_get_contents($file);
	
	
	global $baseDir;
	
	require_once($baseDir . '/modules/oauth/libextinc/OAuth.php');


	
	$translationconfig = SimpleSAML_Configuration::getConfig('translation.php');

	$baseurl = $translationconfig->getString('baseurl');
	$key = $translationconfig->getString('key');
	$secret = $translationconfig->getString('secret');
	
	echo 'Using OAuth to authenticate you to the translation portal' . "\n";
	$consumer = new sspmod_oauth_Consumer($key, $secret);

	
	
	$storage = new sspmod_core_Storage_SQLPermanentStorage('oauth_clientcache');
	
	$cachedAccessToken = $storage->get('accesstoken', 'translation', '');
	$accessToken = NULL;
	if (empty($cachedAccessToken)) {

		// Get the request token
		$requestToken = $consumer->getRequestToken($baseurl . '/module.php/oauth/requestToken.php');
		echo "Got a request token from the OAuth service provider [" . $requestToken->key . "] with the secret [" . $requestToken->secret . "]\n";

		// Authorize the request token
		$url = $consumer->getAuthorizeRequest($baseurl . '/module.php/oauth/authorize.php', $requestToken, FALSE);

		echo('Go to this URL to authenticate/authorize the request: ' . $url . "\n");
		system('open ' . $url);

		ssp_readline('Click enter when you have completed the authorization step using your web browser...');

		// Replace the request token with an access token
		$accessToken = $consumer->getAccessToken( $baseurl . '/module.php/oauth/accessToken.php', $requestToken);
		echo "Got an access token from the OAuth service provider [" . $accessToken->key . "] with the secret [" . $accessToken->secret . "]\n";
		
		$storage->set('accesstoken', 'translation', '', $accessToken);
		
	} else {
		$accessToken = $cachedAccessToken['value'];
		echo 'Successfully read OAuth Access Token from cache [' . $accessToken->key . ']' . "\n";
	}

	$pushURL = $baseurl . '/module.php/translationportal/push.php';
	$request = array('data' => base64_encode($fileContent), 'file' => $fileWithoutExt, 'aid' => $aid, 'type' => $type);
	
	$result = $consumer->postRequest($pushURL, $accessToken, $request);
	
	echo $result;
	
	
}

/**
 * Format an associative array as a json string.
 *
 * @param mixed $data  The data that should be json encoded.
 * @param string $indentation  The current indentation level. Optional.
 * @return string  The json encoded data.
 */
function json_format($data, $indentation = '') {
	assert('is_string($indentation)');

	if (!is_array($data)) {
		return json_encode($data);
	}

	$ret = "{";
	$first = TRUE;
	foreach ($data as $k => $v) {
		$k = json_encode((string)$k);
		$v = json_format($v, $indentation . "\t");

		if ($first) {
			$ret .= "\n";
			$first = FALSE;
		} else {
			$ret .= ",\n";
		}

		$ret .= $indentation . "\t" . $k . ': ' . $v;
	}
	$ret .= "\n" . $indentation . '}';

	return $ret;
}
