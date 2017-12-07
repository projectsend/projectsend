<?php

/**
 * Authenticate using LiveID.
 *
 * @author Brook Schofield, TERENA.
 * @author Guy Halse, TENET.
 * @package SimpleSAMLphp
 */
class sspmod_authwindowslive_Auth_Source_LiveID extends SimpleSAML_Auth_Source
{

    /**
     * The string used to identify our states.
     */
    const STAGE_INIT = 'authwindowslive:init';

    /**
     * The key of the AuthId field in the state.
     */
    const AUTHID = 'authwindowslive:AuthId';

    private $key;
    private $secret;


    /**
     * Constructor for this authentication source.
     *
     * @param array $info  Information about this authentication source.
     * @param array $config  Configuration.
     *
     * @throws Exception In case of misconfiguration.
     */
    public function __construct($info, $config)
    {
        assert('is_array($info)');
        assert('is_array($config)');

        // Call the parent constructor first, as required by the interface
        parent::__construct($info, $config);

        if (!array_key_exists('key', $config)) {
            throw new Exception('LiveID authentication source is not properly configured: missing [key]');
        }

        $this->key = $config['key'];

        if (!array_key_exists('secret', $config)) {
            throw new Exception('LiveID authentication source is not properly configured: missing [secret]');
        }

        $this->secret = $config['secret'];
    }


    /**
     * Log-in using LiveID platform
     *
     * @param array &$state  Information about the current authentication.
     */
    public function authenticate(&$state)
    {
        assert('is_array($state)');

        // we are going to need the authId in order to retrieve this authentication source later
        $state[self::AUTHID] = $this->authId;

        $stateID = SimpleSAML_Auth_State::saveState($state, self::STAGE_INIT);

        SimpleSAML_Logger::debug('authwindowslive auth state id = ' . $stateID);

        // authenticate the user
        // documentation at:
        // https://azure.microsoft.com/en-us/documentation/articles/active-directory-v2-protocols-oauth-code/
        $authorizeURL = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize'
                . '?client_id=' . $this->key
                . '&response_type=code'
                . '&response_mode=query'
                . '&redirect_uri=' . urlencode(SimpleSAML_Module::getModuleUrl('authwindowslive') . '/linkback.php')
                . '&state=' . urlencode($stateID)
                . '&scope=' . urlencode('openid https://graph.microsoft.com/user.read')
        ;

        \SimpleSAML\Utils\HTTP::redirectTrustedURL($authorizeURL);
    }


    /**
     * @param $state
     *
     * @throws Exception
     */
    public function finalStep(&$state)
    {
        SimpleSAML_Logger::debug(
            "authwindowslive oauth: Using this verification code [".$state['authwindowslive:verification_code']."]"
        );

        // retrieve Access Token
        // documentation at:
        // https://azure.microsoft.com/en-us/documentation/articles/active-directory-v2-protocols-oauth-code/#request-an-access-token
        $postData = 'client_id=' . urlencode($this->key)
                . '&client_secret=' . urlencode($this->secret)
                . '&scope=' . urlencode('https://graph.microsoft.com/user.read')
                . '&grant_type=authorization_code'
                . '&redirect_uri=' . urlencode(SimpleSAML_Module::getModuleUrl('authwindowslive') . '/linkback.php')
                . '&code=' . urlencode($state['authwindowslive:verification_code']);

        $context = array(
            'http' => array(
                'method'  => 'POST',
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postData,
            ),
        );

        $result = \SimpleSAML\Utils\HTTP::fetch('https://login.microsoftonline.com/common/oauth2/v2.0/token', $context);

        $response = json_decode($result, true);

        // error checking of $response to make sure we can proceed
        if (!array_key_exists('access_token', $response)) {
            throw new Exception(
                '['.$response['error'].'] '.$response['error_description'].
                "\r\nNo access_token returned - cannot proceed\r\n" . implode(', ', $response['error_codes'])
            );
        }

        SimpleSAML_Logger::debug(
            "authwindowslive: Got an access token from the OAuth service provider [".$response['access_token']."]"
        );

        // documentation at: http://graph.microsoft.io/en-us/docs/overview/call_api
        $opts = array('http' => array('header' => "Accept: application/json\r\nAuthorization: Bearer ".
                        $response['access_token']."\r\n"));
        $data = \SimpleSAML\Utils\HTTP::fetch('https://graph.microsoft.com/v1.0/me', $opts);
        $userdata = json_decode($data, true);

        // this is the simplest case
        if (!array_key_exists('@odata.context', $userdata) || array_key_exists('error', $userdata)) {
            throw new Exception(
                'Unable to retrieve userdata from Microsoft Graph ['.$userdata['error']['code'].'] '.
                $userdata['error']['message']
            );
        }
        $attributes = array();
        $attributes['windowslive_targetedID'] = array(
            'https://graph.microsoft.com!'.(!empty($userdata['id']) ? $userdata['id'] : 'unknown')
        );
        foreach ($userdata as $key => $value) {
            if (is_string($value)) {
                $attributes['windowslive.' . $key] = array((string)$value);
            }
        }


        SimpleSAML_Logger::debug('LiveID Returned Attributes: '. implode(", ", array_keys($attributes)));

        $state['Attributes'] = $attributes;
    }
}
