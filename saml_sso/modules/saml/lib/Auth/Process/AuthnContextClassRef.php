<?php


/**
 * Filter for setting the AuthnContextClassRef in the response.
 *
 * @package SimpleSAMLphp
 */
class sspmod_saml_Auth_Process_AuthnContextClassRef extends SimpleSAML_Auth_ProcessingFilter
{

    /**
     * The URI we should set as the AuthnContextClassRef in the login response.
     *
     * @var string
     */
    private $authnContextClassRef;


    /**
     * Initialize this filter.
     *
     * @param array $config Configuration information about this filter.
     * @param mixed $reserved For future use.
     *
     * @throws SimpleSAML_Error_Exception if the mandatory 'AuthnContextClassRef' option is missing.
     */
    public function __construct($config, $reserved)
    {
        parent::__construct($config, $reserved);
        assert('is_array($config)');

        if (!isset($config['AuthnContextClassRef'])) {
            throw new SimpleSAML_Error_Exception('Missing AuthnContextClassRef option in processing filter.');
        }

        $this->authnContextClassRef = (string) $config['AuthnContextClassRef'];
    }


    /**
     * Set the AuthnContextClassRef in the SAML 2 response.
     *
     * @param array &$state The state array for this request.
     */
    public function process(&$state)
    {
        assert('is_array($state)');

        $state['saml:AuthnContextClassRef'] = $this->authnContextClassRef;
    }
}
