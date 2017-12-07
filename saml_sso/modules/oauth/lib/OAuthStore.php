<?php
require_once(dirname(dirname(__FILE__)) . '/libextinc/OAuth.php');

/**
 * OAuth Store
 * 
 * Updated version, works with consumer-callbacks, certificates and 1.0-RevA protocol 
 * behaviour (requestToken-callbacks and verifiers)
 *
 * @author Andreas Åkre Solberg, <andreas.solberg@uninett.no>, UNINETT AS.
 * @author Mark Dobrinic, <mdobrinic@cozmanova.com>, Cozmanova bv
 * @package SimpleSAMLphp
 */
class sspmod_oauth_OAuthStore extends OAuthDataStore {

	private $store;
	private $config;
	private $defaultversion = '1.0';

	protected $_store_tables = array(
					'consumers' => 'consumer = array with consumer attributes', 
					'nonce' => 'nonce+consumer_key = -boolean-',
					'requesttorequest' => 'requestToken.key = array(version,callback,consumerKey,)',
					'authorized' => 'requestToken.key, verifier = array(authenticated-user-attributes)',
					'access' => 'accessToken.key+consumerKey = accestoken',
					'request' => 'requestToken.key+consumerKey = requesttoken',
				);
				
    function __construct() {
		$this->store = new sspmod_core_Storage_SQLPermanentStorage('oauth');
		$this->config = SimpleSAML_Configuration::getOptionalConfig('module_oauth.php');
    }
	
    
    /**
     * Attach the data to the token, and establish the Callback URL and verifier
     * @param $requestTokenKey RequestToken that was authorized
     * @param $data Data that is authorized and to be attached to the requestToken
     * @return array(string:url, string:verifier) ; empty verifier for 1.0-response
     */
	public function authorize($requestTokenKey, $data) {
		$url = null;
		$verifier = '';
		$version = $this->defaultversion;
		
		// See whether to remember values from the original requestToken request:
		$request_attributes = $this->store->get('requesttorequest', $requestTokenKey, '');	// must be there ..
		if ($request_attributes['value']) {
			// establish version to work with
			$v = $request_attributes['value']['version'];
			if ($v) $version = $v; 
			
			// establish callback to use
			if ($request_attributes['value']['callback']) {
				$url = $request_attributes['value']['callback'];
			}
		}
		
		
		// Is there a callback registered? This is leading, even over a supplied oauth_callback-parameter
		$oConsumer = $this->lookup_consumer($request_attributes['value']['consumerKey']);
		
		if ($oConsumer && ($oConsumer->callback_url)) $url = $oConsumer->callback_url;
		
		$verifier = SimpleSAML\Utils\Random::generateID();
		$url = \SimpleSAML\Utils\HTTP::addURLParameters($url, array("oauth_verifier"=>$verifier));
		
		$this->store->set('authorized', $requestTokenKey, $verifier, $data, $this->config->getValue('requestTokenDuration', 60*30) );
		
		return array($url, $verifier);
	}
	
	/**
	 * Perform lookup whether a given token exists in the list of authorized tokens; if a verifier is
	 * passed as well, the verifier *must* match the verifier that was registered with the token<br/>
	 * Note that an accessToken should never be stored with a verifier
	 * @param $requestToken
	 * @param $verifier
	 * @return unknown_type
	 */
	public function isAuthorized($requestToken, $verifier='') {
		SimpleSAML_Logger::info('OAuth isAuthorized(' . $requestToken . ')');
		return $this->store->exists('authorized', $requestToken, $verifier);
	}
	
	public function getAuthorizedData($token, $verifier = '') {
		SimpleSAML_Logger::info('OAuth getAuthorizedData(' . $token . ')');
		$data = $this->store->get('authorized', $token, $verifier);
		return $data['value'];
	}
	
	public function moveAuthorizedData($requestToken, $verifier, $accessTokenKey) {
		SimpleSAML_Logger::info('OAuth moveAuthorizedData(' . $requestToken . ', ' . $accessTokenKey . ')');

		// Retrieve authorizedData from authorized.requestToken (with provider verifier)
		$authorizedData = $this->getAuthorizedData($requestToken, $verifier);
		
		// Remove the requesttoken+verifier from authorized store
		$this->store->remove('authorized', $requestToken, $verifier);
		
		// Add accesstoken with authorizedData to authorized store (with empty verifier)
		// accessTokenKey+consumer => accessToken is already registered in 'access'-table
		$this->store->set('authorized', $accessTokenKey, '', $authorizedData, $this->config->getValue('accessTokenDuration', 60*60*24));
	}
	
    public function lookup_consumer($consumer_key) {
		SimpleSAML_Logger::info('OAuth lookup_consumer(' . $consumer_key . ')');
		if (! $this->store->exists('consumers', $consumer_key, ''))  return NULL;
		$consumer = $this->store->get('consumers', $consumer_key, '');
		
		$callback = NULL;
		if ($consumer['value']['callback_url']) $callback = $consumer['value']['callback_url'];

		if ($consumer['value']['RSAcertificate']) {
			return new OAuthConsumer($consumer['value']['key'], $consumer['value']['RSAcertificate'], $callback);
		} else {
			return new OAuthConsumer($consumer['value']['key'], $consumer['value']['secret'], $callback);
		}
    }

    function lookup_token($consumer, $tokenType = 'default', $token) {
		SimpleSAML_Logger::info('OAuth lookup_token(' . $consumer->key . ', ' . $tokenType. ',' . $token . ')');
		$data = $this->store->get($tokenType, $token, $consumer->key);
		if ($data == NULL) throw new Exception('Could not find token');
		return $data['value'];
    }

    function lookup_nonce($consumer, $token, $nonce, $timestamp) {
		SimpleSAML_Logger::info('OAuth lookup_nonce(' . $consumer . ', ' . $token. ',' . $nonce . ')');
		if ($this->store->exists('nonce', $nonce, $consumer->key))  return TRUE;
		$this->store->set('nonce', $nonce, $consumer->key, TRUE, $this->config->getValue('nonceCache', 60*60*24*14));
		return FALSE;
    }

    function new_request_token($consumer, $callback = null, $version = null) {
		SimpleSAML_Logger::info('OAuth new_request_token(' . $consumer . ')');
		
		$lifetime = $this->config->getValue('requestTokenDuration', 60*30); 
		
		$token = new OAuthToken(SimpleSAML\Utils\Random::generateID(), SimpleSAML\Utils\Random::generateID());
		$token->callback = $callback;	// OAuth1.0-RevA
		$this->store->set('request', $token->key, $consumer->key, $token, $lifetime);
		
		// also store in requestToken->key => array('callback'=>CallbackURL, 'version'=>oauth_version
		$request_attributes = array(
				'callback' => $callback, 
				'version' => ($version?$version:$this->defaultversion),
				'consumerKey' => $consumer->key,
			);
		$this->store->set('requesttorequest', $token->key, '', $request_attributes, $lifetime);
		
		// also store in requestToken->key => Consumer->key (enables consumer-lookup during reqToken-authorization stage)
		$this->store->set('requesttoconsumer', $token->key, '', $consumer->key, $lifetime);
		
        return $token;
    }

    function new_access_token($requestToken, $consumer, $verifier = null) {
		SimpleSAML_Logger::info('OAuth new_access_token(' . $requestToken . ',' . $consumer . ')');
		$accestoken = new OAuthToken(SimpleSAML\Utils\Random::generateID(), SimpleSAML\Utils\Random::generateID());
		$this->store->set('access', $accestoken->key, $consumer->key, $accestoken, $this->config->getValue('accessTokenDuration', 60*60*24) );
        return $accestoken;
    }
    
    /**
     * Return OAuthConsumer-instance that a given requestToken was issued to
     * @param $requestTokenKey
     * @return unknown_type
     */
    public function lookup_consumer_by_requestToken($requestTokenKey) {
		SimpleSAML_Logger::info('OAuth lookup_consumer_by_requestToken(' . $requestTokenKey . ')');
		if (! $this->store->exists('requesttorequest', $requestTokenKey, '')) return NULL;
		
		$request = $this->store->get('requesttorequest', $requestTokenKey, '');
		$consumerKey = $request['value']['consumerKey'];
		if (! $consumerKey) {
			return NULL;
		}
		
		$consumer = $this->store->get('consumers', $consumerKey['value'], '');
		return $consumer['value'];
	}
	
    

}
