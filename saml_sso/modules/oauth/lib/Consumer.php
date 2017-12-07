<?php

require_once(dirname(dirname(__FILE__)) . '/libextinc/OAuth.php');

/**
 * OAuth Consumer
 *
 * @author Andreas Åkre Solberg, <andreas.solberg@uninett.no>, UNINETT AS.
 * @package SimpleSAMLphp
 */
class sspmod_oauth_Consumer {
	
	private $consumer;
	private $signer;
	
	public function __construct($key, $secret) {
		$this->consumer = new OAuthConsumer($key, $secret, NULL);
		$this->signer = new OAuthSignatureMethod_HMAC_SHA1();
	}
	
	// Used only to load the libextinc library early
	public static function dummy() {}
	
	
	public static function getOAuthError($hrh) {
		foreach($hrh AS $h) {
			if (preg_match('|OAuth-Error:\s([^;]*)|i', $h, $matches)) {
				return $matches[1];
			}
		}
		return null;
	}
	
	public static function getContentType($hrh) {
		foreach($hrh AS $h) {
			if (preg_match('|Content-Type:\s([^;]*)|i', $h, $matches)) {
				return $matches[1];
			}
		}
		return null;
	}
	
	/*
	 * This static helper function wraps file_get_contents
	 * and throws an exception with diagnostics messages if it appear
	 * to be failing on an OAuth endpoint.
	 * 
	 * If the status code is not 200, an exception is thrown. If the content-type
	 * of the response if text/plain, the content of the response is included in 
	 * the text of the Exception thrown.
	 */
	public static function getHTTP($url, $context = '') {
		$response = @file_get_contents($url);
		
		if ($response === FALSE) {
			$statuscode = 'unknown';
			if (preg_match('/^HTTP.*\s([0-9]{3})/', $http_response_header[0], $matches)) $statuscode = $matches[1];
			
			$error = $context . ' [statuscode: ' . $statuscode . ']: ';
			$contenttype = self::getContentType($http_response_header);
			$oautherror = self::getOAuthError($http_response_header);
			
			if (!empty($oautherror)) $error .= $oautherror;

			throw new Exception($error . ':' . $url);
		} 
		// Fall back to return response, if could not reckognize HTTP header. Should not happen.
		return $response;
	}
	
	public function getRequestToken($url, $parameters = NULL) {
		$req_req = OAuthRequest::from_consumer_and_token($this->consumer, NULL, "GET", $url, $parameters);
		$req_req->sign_request($this->signer, $this->consumer, NULL);

		$response_req = self::getHTTP($req_req->to_url(), 
			'Contacting request_token endpoint on the OAuth Provider');

		parse_str($response_req, $responseParsed);
		
		if(array_key_exists('error', $responseParsed))
			throw new Exception('Error getting request token: ' . $responseParsed['error']);
			
		$requestToken = $responseParsed['oauth_token'];
		$requestTokenSecret = $responseParsed['oauth_token_secret'];
		
		return new OAuthToken($requestToken, $requestTokenSecret);
	}
	
	public function getAuthorizeRequest($url, $requestToken, $redirect = TRUE, $callback = NULL) {
		$params = array('oauth_token' => $requestToken->key);
		if ($callback) {
			$params['oauth_callback'] = $callback;
		}
		$authorizeURL = \SimpleSAML\Utils\HTTP::addURLParameters($url, $params);
		if ($redirect) {
			\SimpleSAML\Utils\HTTP::redirectTrustedURL($authorizeURL);
			exit;
		}	
		return $authorizeURL;
	}
	
	public function getAccessToken($url, $requestToken, $parameters = NULL) {

		$acc_req = OAuthRequest::from_consumer_and_token($this->consumer, $requestToken, "GET", $url, $parameters);
		$acc_req->sign_request($this->signer, $this->consumer, $requestToken);
		
		$response_acc = file_get_contents($acc_req->to_url());
		if ($response_acc === FALSE) {
			throw new Exception('Error contacting request_token endpoint on the OAuth Provider');
		}

		SimpleSAML_Logger::debug('oauth: Reponse to get access token: '. $response_acc);
		
		parse_str($response_acc, $accessResponseParsed);
		
		if(array_key_exists('error', $accessResponseParsed))
			throw new Exception('Error getting request token: ' . $accessResponseParsed['error']);
		
		$accessToken = $accessResponseParsed['oauth_token'];
		$accessTokenSecret = $accessResponseParsed['oauth_token_secret'];

		return new OAuthToken($accessToken, $accessTokenSecret);
	}
	
	public function postRequest($url, $accessToken, $parameters) {
		$data_req = OAuthRequest::from_consumer_and_token($this->consumer, $accessToken, "POST", $url, $parameters);
		$data_req->sign_request($this->signer, $this->consumer, $accessToken);
		$postdata = $data_req->to_postdata();

		$opts = array(
			'ssl' => array(
				'verify_peer' => FALSE,
				'capture_peer_cert' => TRUE,
				'capture_peer_chain' => TRUE,
			),
			'http' => array(
				'method' => 'POST',
				'content' => $postdata,
				'header' => 'Content-Type: application/x-www-form-urlencoded',
			),
		);
		$context = stream_context_create($opts);
		$response = file_get_contents($url, FALSE, $context);
		if ($response === FALSE) {
			throw new SimpleSAML_Error_Exception('Failed to push definition file to ' . $url);
		}
		return $response;
	}
	
	public function getUserInfo($url, $accessToken, $opts = NULL) {
		
		$data_req = OAuthRequest::from_consumer_and_token($this->consumer, $accessToken, "GET", $url, NULL);
		$data_req->sign_request($this->signer, $this->consumer, $accessToken);

		if (is_array($opts)) {
			$opts = stream_context_create($opts);
		}
		$data = file_get_contents($data_req->to_url(), FALSE, $opts);

		$dataDecoded = json_decode($data, TRUE);
		return $dataDecoded;
	}
	
}

