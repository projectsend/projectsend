<?php


/**
 * Authentication processing filter to generate a persistent NameID.
 *
 * @package SimpleSAMLphp
 */
class sspmod_saml_Auth_Process_SQLPersistentNameID extends sspmod_saml_BaseNameIDGenerator
{

    /**
     * Which attribute contains the unique identifier of the user.
     *
     * @var string
     */
    private $attribute;

    /**
     * Whether we should create a persistent NameID if not explicitly requested (as saml:PersistentNameID does).
     *
     * @var boolean
     */
    private $allowUnspecified = false;

    /**
     * Whether we should create a persistent NameID if a different format is requested.
     *
     * @var boolean
     */
    private $allowDifferent = false;

    /**
     * Whether we should ignore allowCreate in the NameID policy
     *
     * @var boolean
     */
    private $alwaysCreate = false;


    /**
     * Initialize this filter, parse configuration.
     *
     * @param array $config Configuration information about this filter.
     * @param mixed $reserved For future use.
     *
     * @throws SimpleSAML_Error_Exception If the 'attribute' option is not specified.
     */
    public function __construct($config, $reserved)
    {
        parent::__construct($config, $reserved);
        assert('is_array($config)');

        $this->format = SAML2_Const::NAMEID_PERSISTENT;

        if (!isset($config['attribute'])) {
            throw new SimpleSAML_Error_Exception("PersistentNameID: Missing required option 'attribute'.");
        }
        $this->attribute = $config['attribute'];

        if (isset($config['allowUnspecified'])) {
            $this->allowUnspecified = (bool) $config['allowUnspecified'];
        }

        if (isset($config['allowDifferent'])) {
            $this->allowDifferent = (bool) $config['allowDifferent'];
        }

        if (isset($config['alwaysCreate'])) {
            $this->alwaysCreate = (bool) $config['alwaysCreate'];
        }
    }


    /**
     * Get the NameID value.
     *
     * @param array $state The state array.
     * @return string|null The NameID value.
     *
     * @throws sspmod_saml_Error if the NameID creation policy is invalid.
     */
    protected function getValue(array &$state)
    {

        if (!isset($state['saml:NameIDFormat']) && !$this->allowUnspecified) {
            SimpleSAML_Logger::debug(
                'SQLPersistentNameID: Request did not specify persistent NameID format, '.
                'not generating persistent NameID.'
            );
            return null;
        }

        $validNameIdFormats = @array_filter(array(
            $state['saml:NameIDFormat'],
            $state['SPMetadata']['NameIDPolicy'],
            $state['SPMetadata']['NameIDFormat']
        ));
        if (count($validNameIdFormats) && !in_array($this->format, $validNameIdFormats) && !$this->allowDifferent) {
            SimpleSAML_Logger::debug(
                'SQLPersistentNameID: SP expects different NameID format ('.
                implode(', ', $validNameIdFormats).'),  not generating persistent NameID.'
            );
            return null;
        }

        if (!isset($state['Destination']['entityid'])) {
            SimpleSAML_Logger::warning('SQLPersistentNameID: No SP entity ID - not generating persistent NameID.');
            return null;
        }
        $spEntityId = $state['Destination']['entityid'];

        if (!isset($state['Source']['entityid'])) {
            SimpleSAML_Logger::warning('SQLPersistentNameID: No IdP entity ID - not generating persistent NameID.');
            return null;
        }
        $idpEntityId = $state['Source']['entityid'];

        if (!isset($state['Attributes'][$this->attribute]) || count($state['Attributes'][$this->attribute]) === 0) {
            SimpleSAML_Logger::warning(
                'SQLPersistentNameID: Missing attribute '.var_export($this->attribute, true).
                ' on user - not generating persistent NameID.'
            );
            return null;
        }
        if (count($state['Attributes'][$this->attribute]) > 1) {
            SimpleSAML_Logger::warning(
                'SQLPersistentNameID: More than one value in attribute '.var_export($this->attribute, true).
                ' on user - not generating persistent NameID.'
            );
            return null;
        }
        $uid = array_values($state['Attributes'][$this->attribute]); // just in case the first index is no longer 0
        $uid = $uid[0];

        if (empty($uid)) {
            SimpleSAML_Logger::warning(
                'Empty value in attribute '.var_export($this->attribute, true).
                ' on user - not generating persistent NameID.'
            );
            return null;
        }

        $value = sspmod_saml_IdP_SQLNameID::get($idpEntityId, $spEntityId, $uid);
        if ($value !== null) {
            SimpleSAML_Logger::debug(
                'SQLPersistentNameID: Found persistent NameID '.var_export($value, true).' for user '.
                var_export($uid, true).'.'
            );
            return $value;
        }

        if ((!isset($state['saml:AllowCreate']) || !$state['saml:AllowCreate']) && !$this->alwaysCreate) {
            SimpleSAML_Logger::warning(
                'SQLPersistentNameID: Did not find persistent NameID for user, and not allowed to create new NameID.'
            );
            throw new sspmod_saml_Error(
                SAML2_Const::STATUS_RESPONDER,
                'urn:oasis:names:tc:SAML:2.0:status:InvalidNameIDPolicy'
            );
        }

        $value = bin2hex(openssl_random_pseudo_bytes(20));
        SimpleSAML_Logger::debug(
            'SQLPersistentNameID: Created persistent NameID '.var_export($value, true).' for user '.
            var_export($uid, true).'.'
        );
        sspmod_saml_IdP_SQLNameID::add($idpEntityId, $spEntityId, $uid, $value);

        return $value;
    }
}
