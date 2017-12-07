<?php


/**
 * Authentication processing filter to create the eduPersonTargetedID attribute from the persistent NameID.
 *
 * @package SimpleSAMLphp
 */
class sspmod_saml_Auth_Process_PersistentNameID2TargetedID extends SimpleSAML_Auth_ProcessingFilter
{

    /**
     * The attribute we should save the NameID in.
     *
     * @var string
     */
    private $attribute;


    /**
     * Whether we should insert it as an saml:NameID element.
     *
     * @var boolean
     */
    private $nameId;


    /**
     * Initialize this filter, parse configuration.
     *
     * @param array $config Configuration information about this filter.
     * @param mixed $reserved For future use.
     */
    public function __construct($config, $reserved)
    {
        parent::__construct($config, $reserved);
        assert('is_array($config)');

        if (isset($config['attribute'])) {
            $this->attribute = (string) $config['attribute'];
        } else {
            $this->attribute = 'eduPersonTargetedID';
        }

        if (isset($config['nameId'])) {
            $this->nameId = (bool) $config['nameId'];
        } else {
            $this->nameId = true;
        }
    }


    /**
     * Store a NameID to attribute.
     *
     * @param array &$state The request state.
     */
    public function process(&$state)
    {
        assert('is_array($state)');

        if (!isset($state['saml:NameID'][SAML2_Const::NAMEID_PERSISTENT])) {
            SimpleSAML_Logger::warning(
                'Unable to generate eduPersonTargetedID because no persistent NameID was available.'
            );
            return;
        }

        $nameID = $state['saml:NameID'][SAML2_Const::NAMEID_PERSISTENT];

        if ($this->nameId) {
            $doc = SAML2_DOMDocumentFactory::create();
            $root = $doc->createElement('root');
            $doc->appendChild($root);
            SAML2_Utils::addNameId($root, $nameID);
            $value = $doc->saveXML($root->firstChild);
        } else {
            $value = $nameID['Value'];
        }

        $state['Attributes'][$this->attribute] = array($value);
    }
}
