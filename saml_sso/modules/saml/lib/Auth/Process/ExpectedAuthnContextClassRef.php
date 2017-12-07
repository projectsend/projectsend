<?php


/**
 * Attribute filter to validate AuthnContextClassRef values.
 *
 * Example configuration:
 *
 * 91 => array(
 *      'class' => 'saml:ExpectedAuthnContextClassRef',
 *      'accepted' => array(
 *         'urn:oasis:names:tc:SAML:2.0:post:ac:classes:nist-800-63:3',
 *         'urn:oasis:names:tc:SAML:2.0:ac:classes:Password',
 *         ),
 *       ),
 *
 * @package SimpleSAMLphp
 */
class sspmod_saml_Auth_Process_ExpectedAuthnContextClassRef extends SimpleSAML_Auth_ProcessingFilter
{

    /**
     * Array of accepted AuthnContextClassRef
     * @var array
     */
    private $accepted;


    /**
     * AuthnContextClassRef of the assertion
     * @var string
     */
    private $AuthnContextClassRef;


    /**
     * Initialize this filter, parse configuration
     *
     * @param array $config Configuration information about this filter.
     * @param mixed $reserved For future use.
     *
     * @throws SimpleSAML_Error_Exception if the mandatory 'accepted' configuration option is missing.
     */
    public function __construct($config, $reserved)
    {
        parent::__construct($config, $reserved);

        assert('is_array($config)');
        if (empty($config['accepted'])) {
            SimpleSAML_Logger::error(
                'ExpectedAuthnContextClassRef: Configuration error. There is no accepted AuthnContextClassRef.'
            );
            throw new SimpleSAML_Error_Exception(
                'ExpectedAuthnContextClassRef: Configuration error. There is no accepted AuthnContextClassRef.'
            );
        }
        $this->accepted = $config['accepted'];
    }


    /**
     *
     * @param array &$request The current request
     */
    public function process(&$request)
    {
        assert('is_array($request)');
        assert('array_key_exists("Attributes", $request)');

        $this->AuthnContextClassRef = $request['saml:sp:State']['saml:sp:AuthnContext'];

        if (!in_array($this->AuthnContextClassRef, $this->accepted)) {
            $this->unauthorized($request);
        }
    }


    /**
     * When the process logic determines that the user is not
     * authorized for this service, then forward the user to
     * an 403 unauthorized page.
     *
     * Separated this code into its own method so that child
     * classes can override it and change the action. Forward
     * thinking in case a "chained" ACL is needed, more complex
     * permission logic.
     *
     * @param array $request
     */
    protected function unauthorized(&$request)
    {
        SimpleSAML_Logger::error(
            'ExpectedAuthnContextClassRef: Invalid authentication context: '.$this->AuthnContextClassRef.
            '. Accepted values are: '.var_export($this->accepted, true)
        );

        $id = SimpleSAML_Auth_State::saveState($request, 'saml:ExpectedAuthnContextClassRef:unauthorized');
        $url = SimpleSAML_Module::getModuleURL(
            'saml/sp/wrong_authncontextclassref.php'
        );
        \SimpleSAML\Utils\HTTP::redirectTrustedURL($url, array('StateId' => $id));
    }
}
