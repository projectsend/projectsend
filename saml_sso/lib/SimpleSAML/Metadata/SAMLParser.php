<?php


/**
 * This is class for parsing of SAML 1.x and SAML 2.0 metadata.
 *
 * Metadata is loaded by calling the static methods parseFile, parseString or parseElement.
 * These functions returns an instance of SimpleSAML_Metadata_SAMLParser. To get metadata
 * from this object, use the methods getMetadata1xSP or getMetadata20SP.
 *
 * To parse a file which can contain a collection of EntityDescriptor or EntitiesDescriptor elements, use the
 * parseDescriptorsFile, parseDescriptorsString or parseDescriptorsElement methods. These functions will return
 * an array of SAMLParser elements where each element represents an EntityDescriptor-element.
 */
class SimpleSAML_Metadata_SAMLParser
{

    /**
     * This is the list of SAML 1.x protocols.
     *
     * @var string[]
     */
    private static $SAML1xProtocols = array(
        'urn:oasis:names:tc:SAML:1.0:protocol',
        'urn:oasis:names:tc:SAML:1.1:protocol',
    );


    /**
     * This is the list with the SAML 2.0 protocol.
     *
     * @var string[]
     */
    private static $SAML20Protocols = array(
        'urn:oasis:names:tc:SAML:2.0:protocol',
    );


    /**
     * This is the entity id we find in the metadata.
     *
     * @var string
     */
    private $entityId;


    /**
     * This is an array with the processed SPSSODescriptor elements we have found in this
     * metadata file.
     * Each element in the array is an associative array with the elements from parseSSODescriptor and:
     * - 'AssertionConsumerService': Array with the SP's assertion consumer services.
     *   Each assertion consumer service is stored as an associative array with the
     *   elements that parseGenericEndpoint returns.
     *
     * @var array[]
     */
    private $spDescriptors;


    /**
     * This is an array with the processed IDPSSODescriptor elements we have found.
     * Each element in the array is an associative array with the elements from parseSSODescriptor and:
     * - 'SingleSignOnService': Array with the IdP's single sign on service endpoints. Each endpoint is stored
     *   as an associative array with the elements that parseGenericEndpoint returns.
     *
     * @var array[]
     */
    private $idpDescriptors;


    /**
     * List of attribute authorities we have found.
     *
     * @var array
     */
    private $attributeAuthorityDescriptors = array();


    /**
     * This is an associative array with the organization name for this entity. The key of
     * the associative array is the language code, while the value is a string with the
     * organization name.
     *
     * @var string[]
     */
    private $organizationName = array();


    /**
     * This is an associative array with the organization display name for this entity. The key of
     * the associative array is the language code, while the value is a string with the
     * organization display name.
     *
     * @var string[]
     */
    private $organizationDisplayName = array();


    /**
     * This is an associative array with the organization URI for this entity. The key of
     * the associative array is the language code, while the value is the URI.
     *
     * @var string[]
     */
    private $organizationURL = array();


    /**
     * This is an array of the Contact Persons of this entity.
     *
     * @var array[]
     */
    private $contacts = array();


    /**
     * @var array
     */
    private $scopes;


    /**
     * @var array
     */
    private $entityAttributes;


    /**
     * @var array
     */
    private $tags;


    /**
     * This is an array of elements that may be used to validate this element.
     *
     * @var SAML2_SignedElementHelper[]
     */
    private $validators = array();


    /**
     * The original EntityDescriptor element for this entity, as a base64 encoded string.
     *
     * @var string
     */
    private $entityDescriptor;


    /**
     * This is the constructor for the SAMLParser class.
     *
     * @param SAML2_XML_md_EntityDescriptor $entityElement The EntityDescriptor.
     * @param int|NULL                      $maxExpireTime The unix timestamp for when this entity should expire, or
     *     NULL if unknown.
     * @param array                         $validators An array of parent elements that may validate this element.
     */
    private function __construct(
        SAML2_XML_md_EntityDescriptor $entityElement,
        $maxExpireTime,
        array $validators = array()
    ) {
        assert('is_null($maxExpireTime) || is_int($maxExpireTime)');

        $this->spDescriptors = array();
        $this->idpDescriptors = array();

        $e = $entityElement->toXML();
        $e = $e->ownerDocument->saveXML($e);
        $this->entityDescriptor = base64_encode($e);
        $this->entityId = $entityElement->entityID;

        $expireTime = self::getExpireTime($entityElement, $maxExpireTime);

        $this->validators = $validators;
        $this->validators[] = $entityElement;

        // process Extensions element, if it exists
        $ext = self::processExtensions($entityElement);
        $this->scopes = $ext['scope'];
        $this->tags = $ext['tags'];
        $this->entityAttributes = $ext['EntityAttributes'];

        // look over the RoleDescriptors
        foreach ($entityElement->RoleDescriptor as $child) {

            if ($child instanceof SAML2_XML_md_SPSSODescriptor) {
                $this->processSPSSODescriptor($child, $expireTime);
            } elseif ($child instanceof SAML2_XML_md_IDPSSODescriptor) {
                $this->processIDPSSODescriptor($child, $expireTime);
            } elseif ($child instanceof SAML2_XML_md_AttributeAuthorityDescriptor) {
                $this->processAttributeAuthorityDescriptor($child, $expireTime);
            }
        }

        if ($entityElement->Organization) {
            $this->processOrganization($entityElement->Organization);
        }

        if (!empty($entityElement->ContactPerson)) {
            foreach ($entityElement->ContactPerson as $contact) {
                $this->processContactPerson($contact);
            }
        }
    }


    /**
     * This function parses a file which contains XML encoded metadata.
     *
     * @param string $file The path to the file which contains the metadata.
     *
     * @return SimpleSAML_Metadata_SAMLParser An instance of this class with the metadata loaded.
     * @throws Exception If the file does not parse as XML.
     */
    public static function parseFile($file)
    {
        $data = \SimpleSAML\Utils\HTTP::fetch($file);

        try {
            $doc = SAML2_DOMDocumentFactory::fromString($data);
        } catch(\Exception $e) {
            throw new Exception('Failed to read XML from file: '.$file);
        }

        return self::parseDocument($doc);
    }


    /**
     * This function parses a string which contains XML encoded metadata.
     *
     * @param string $metadata A string which contains XML encoded metadata.
     *
     * @return SimpleSAML_Metadata_SAMLParser An instance of this class with the metadata loaded.
     * @throws Exception If the string does not parse as XML.
     */
    public static function parseString($metadata)
    {
        try {
            $doc = SAML2_DOMDocumentFactory::fromString($metadata);
        } catch(\Exception $e) {
            throw new Exception('Failed to parse XML string.');
        }

        return self::parseDocument($doc);
    }


    /**
     * This function parses a DOMDocument which is assumed to contain a single EntityDescriptor element.
     *
     * @param DOMDocument $document The DOMDocument which contains the EntityDescriptor element.
     *
     * @return SimpleSAML_Metadata_SAMLParser An instance of this class with the metadata loaded.
     */
    public static function parseDocument($document)
    {
        assert('$document instanceof DOMDocument');

        $entityElement = self::findEntityDescriptor($document);

        return self::parseElement($entityElement);
    }


    /**
     * This function parses a SAML2_XML_md_EntityDescriptor object which represents a EntityDescriptor element.
     *
     * @param SAML2_XML_md_EntityDescriptor $entityElement A SAML2_XML_md_EntityDescriptor object which represents a
     *     EntityDescriptor element.
     *
     * @return SimpleSAML_Metadata_SAMLParser An instance of this class with the metadata loaded.
     */
    public static function parseElement($entityElement)
    {
        assert('$entityElement instanceof SAML2_XML_md_EntityDescriptor');

        return new SimpleSAML_Metadata_SAMLParser($entityElement, null);
    }


    /**
     * This function parses a file where the root node is either an EntityDescriptor element or an
     * EntitiesDescriptor element. In both cases it will return an associative array of SAMLParser instances. If
     * the file contains a single EntityDescriptorElement, then the array will contain a single SAMLParser
     * instance.
     *
     * @param string $file The path to the file which contains the EntityDescriptor or EntitiesDescriptor element.
     *
     * @return SimpleSAML_Metadata_SAMLParser[] An array of SAMLParser instances.
     * @throws Exception If the file does not parse as XML.
     */
    public static function parseDescriptorsFile($file)
    {

        if ($file === null) {
            throw new Exception('Cannot open file NULL. File name not specified.');
        }

        $data = \SimpleSAML\Utils\HTTP::fetch($file);

        try {
            $doc = SAML2_DOMDocumentFactory::fromString($data);
        } catch(\Exception $e) {
            throw new Exception('Failed to read XML from file: '.$file);
        }

        if ($doc->documentElement === null) {
            throw new Exception('Opened file is not an XML document: '.$file);
        }

        return self::parseDescriptorsElement($doc->documentElement);
    }


    /**
     * This function parses a string with XML data. The root node of the XML data is expected to be either an
     * EntityDescriptor element or an EntitiesDescriptor element. It will return an associative array of
     * SAMLParser instances.
     *
     * @param string $string The string with XML data.
     *
     * @return SimpleSAML_Metadata_SAMLParser[] An associative array of SAMLParser instances. The key of the array will
     *     be the entity id.
     * @throws Exception If the string does not parse as XML.
     */
    public static function parseDescriptorsString($string)
    {
        try {
            $doc = SAML2_DOMDocumentFactory::fromString($string);
        } catch(\Exception $e) {
            throw new Exception('Failed to parse XML string.');
        }

        return self::parseDescriptorsElement($doc->documentElement);
    }


    /**
     * This function parses a DOMElement which represents either an EntityDescriptor element or an
     * EntitiesDescriptor element. It will return an associative array of SAMLParser instances in both cases.
     *
     * @param DOMElement|NULL $element The DOMElement which contains the EntityDescriptor element or the
     *     EntitiesDescriptor element.
     *
     * @return SimpleSAML_Metadata_SAMLParser[] An associative array of SAMLParser instances. The key of the array will
     *     be the entity id.
     * @throws Exception if the document is empty or the root is an unexpected node.
     */
    public static function parseDescriptorsElement(DOMElement $element = null)
    {
        if ($element === null) {
            throw new Exception('Document was empty.');
        }

        assert('$element instanceof DOMElement');

        if (SimpleSAML\Utils\XML::isDOMElementOfType($element, 'EntityDescriptor', '@md') === true) {
            return self::processDescriptorsElement(new SAML2_XML_md_EntityDescriptor($element));
        } elseif (SimpleSAML\Utils\XML::isDOMElementOfType($element, 'EntitiesDescriptor', '@md') === true) {
            return self::processDescriptorsElement(new SAML2_XML_md_EntitiesDescriptor($element));
        } else {
            throw new Exception('Unexpected root node: ['.$element->namespaceURI.']:'.$element->localName);
        }
    }


    /**
     *
     * @param SAML2_XML_md_EntityDescriptor|SAML2_XML_md_EntitiesDescriptor $element The element we should process.
     * @param int|NULL                                                      $maxExpireTime The maximum expiration time
     *     of the entities.
     * @param array                                                         $validators The parent-elements that may be
     *     signed.
     *
     * @return SimpleSAML_Metadata_SAMLParser[] Array of SAMLParser instances.
     */
    private static function processDescriptorsElement($element, $maxExpireTime = null, array $validators = array())
    {
        assert('is_null($maxExpireTime) || is_int($maxExpireTime)');

        if ($element instanceof SAML2_XML_md_EntityDescriptor) {
            $ret = new SimpleSAML_Metadata_SAMLParser($element, $maxExpireTime, $validators);
            $ret = array($ret->getEntityId() => $ret);
            /** @var SimpleSAML_Metadata_SAMLParser[] $ret */
            return $ret;
        }

        assert('$element instanceof SAML2_XML_md_EntitiesDescriptor');

        $expTime = self::getExpireTime($element, $maxExpireTime);

        $validators[] = $element;

        $ret = array();
        foreach ($element->children as $child) {
            $ret += self::processDescriptorsElement($child, $expTime, $validators);
        }

        return $ret;
    }


    /**
     * Determine how long a given element can be cached.
     *
     * This function looks for the 'validUntil' attribute to determine
     * how long a given XML-element is valid. It returns this as a unix timestamp.
     *
     * @param mixed    $element The element we should determine the expiry time of.
     * @param int|NULL $maxExpireTime The maximum expiration time.
     *
     * @return int The unix timestamp for when the element should expire. Will be NULL if no
     *             limit is set for the element.
     */
    private static function getExpireTime($element, $maxExpireTime)
    {
        // validUntil may be null
        $expire = $element->validUntil;

        if ($maxExpireTime !== null && ($expire === null || $maxExpireTime < $expire)) {
            $expire = $maxExpireTime;
        }

        return $expire;
    }


    /**
     * This function returns the entity id of this parsed entity.
     *
     * @return string The entity id of this parsed entity.
     */
    public function getEntityId()
    {
        return $this->entityId;
    }


    private function getMetadataCommon()
    {
        $ret = array();
        $ret['entityid'] = $this->entityId;
        $ret['entityDescriptor'] = $this->entityDescriptor;

        // add organizational metadata
        if (!empty($this->organizationName)) {
            $ret['description'] = $this->organizationName;
            $ret['OrganizationName'] = $this->organizationName;
        }
        if (!empty($this->organizationDisplayName)) {
            $ret['name'] = $this->organizationDisplayName;
            $ret['OrganizationDisplayName'] = $this->organizationDisplayName;
        }
        if (!empty($this->organizationURL)) {
            $ret['url'] = $this->organizationURL;
            $ret['OrganizationURL'] = $this->organizationURL;
        }

        //add contact metadata
        $ret['contacts'] = $this->contacts;

        return $ret;
    }


    /**
     * Add data parsed from extensions to metadata.
     *
     * @param array &$metadata The metadata that should be updated.
     * @param array $roleDescriptor The parsed role descriptor.
     */
    private function addExtensions(array &$metadata, array $roleDescriptor)
    {
        assert('array_key_exists("scope", $roleDescriptor)');
        assert('array_key_exists("tags", $roleDescriptor)');

        $scopes = array_merge($this->scopes, array_diff($roleDescriptor['scope'], $this->scopes));
        if (!empty($scopes)) {
            $metadata['scope'] = $scopes;
        }

        $tags = array_merge($this->tags, array_diff($roleDescriptor['tags'], $this->tags));
        if (!empty($tags)) {
            $metadata['tags'] = $tags;
        }

        if (!empty($this->entityAttributes)) {
            $metadata['EntityAttributes'] = $this->entityAttributes;

            // check for entity categories
            if (SimpleSAML\Utils\Config\Metadata::isHiddenFromDiscovery($metadata)) {
                $metadata['hide.from.discovery'] = true;
            }
        }

        if (!empty($roleDescriptor['UIInfo'])) {
            $metadata['UIInfo'] = $roleDescriptor['UIInfo'];
        }

        if (!empty($roleDescriptor['DiscoHints'])) {
            $metadata['DiscoHints'] = $roleDescriptor['DiscoHints'];
        }
    }


    /**
     * This function returns the metadata for SAML 1.x SPs in the format SimpleSAMLphp expects.
     * This is an associative array with the following fields:
     * - 'entityid': The entity id of the entity described in the metadata.
     * - 'AssertionConsumerService': String with the URL of the assertion consumer service which supports
     *   the browser-post binding.
     * - 'certData': X509Certificate for entity (if present).
     *
     * Metadata must be loaded with one of the parse functions before this function can be called.
     *
     * @return array An associative array with metadata or NULL if we are unable to generate metadata for a SAML 1.x SP.
     */
    public function getMetadata1xSP()
    {
        $ret = $this->getMetadataCommon();
        $ret['metadata-set'] = 'shib13-sp-remote';


        // find SP information which supports one of the SAML 1.x protocols
        $spd = $this->getSPDescriptors(self::$SAML1xProtocols);
        if (count($spd) === 0) {
            return null;
        }

        // we currently only look at the first SPDescriptor which supports SAML 1.x
        $spd = $spd[0];

        // add expire time to metadata
        if (array_key_exists('expire', $spd)) {
            $ret['expire'] = $spd['expire'];
        }

        // find the assertion consumer service endpoints
        $ret['AssertionConsumerService'] = $spd['AssertionConsumerService'];

        // add the list of attributes the SP should receive
        if (array_key_exists('attributes', $spd)) {
            $ret['attributes'] = $spd['attributes'];
        }
        if (array_key_exists('attributes.required', $spd)) {
            $ret['attributes.required'] = $spd['attributes.required'];
        }
        if (array_key_exists('attributes.NameFormat', $spd)) {
            $ret['attributes.NameFormat'] = $spd['attributes.NameFormat'];
        }

        // add name & description
        if (array_key_exists('name', $spd)) {
            $ret['name'] = $spd['name'];
        }
        if (array_key_exists('description', $spd)) {
            $ret['description'] = $spd['description'];
        }

        // add public keys
        if (!empty($spd['keys'])) {
            $ret['keys'] = $spd['keys'];
        }

        // add extensions
        $this->addExtensions($ret, $spd);

        // prioritize mdui:DisplayName as the name if available
        if (!empty($ret['UIInfo']['DisplayName'])) {
            $ret['name'] = $ret['UIInfo']['DisplayName'];
        }

        return $ret;
    }


    /**
     * This function returns the metadata for SAML 1.x IdPs in the format SimpleSAMLphp expects.
     * This is an associative array with the following fields:
     * - 'entityid': The entity id of the entity described in the metadata.
     * - 'name': Auto generated name for this entity. Currently set to the entity id.
     * - 'SingleSignOnService': String with the URL of the SSO service which supports the redirect binding.
     * - 'SingleLogoutService': String with the URL where we should send logout requests/responses.
     * - 'certData': X509Certificate for entity (if present).
     * - 'certFingerprint': Fingerprint of the X509Certificate from the metadata.
     *
     * Metadata must be loaded with one of the parse functions before this function can be called.
     *
     * @return array An associative array with metadata or NULL if we are unable to generate metadata for a SAML 1.x
     *     IdP.
     */
    public function getMetadata1xIdP()
    {
        $ret = $this->getMetadataCommon();
        $ret['metadata-set'] = 'shib13-idp-remote';

        // find IdP information which supports the SAML 1.x protocol
        $idp = $this->getIdPDescriptors(self::$SAML1xProtocols);
        if (count($idp) === 0) {
            return null;
        }

        // we currently only look at the first IDP descriptor which supports SAML 1.x
        $idp = $idp[0];

        // fdd expire time to metadata
        if (array_key_exists('expire', $idp)) {
            $ret['expire'] = $idp['expire'];
        }

        // find the SSO service endpoints
        $ret['SingleSignOnService'] = $idp['SingleSignOnService'];

        // find the ArtifactResolutionService endpoint
        $ret['ArtifactResolutionService'] = $idp['ArtifactResolutionService'];

        // add public keys
        if (!empty($idp['keys'])) {
            $ret['keys'] = $idp['keys'];
        }

        // add extensions
        $this->addExtensions($ret, $idp);

        // prioritize mdui:DisplayName as the name if available
        if (!empty($ret['UIInfo']['DisplayName'])) {
            $ret['name'] = $ret['UIInfo']['DisplayName'];
        }

        return $ret;
    }


    /**
     * This function returns the metadata for SAML 2.0 SPs in the format SimpleSAMLphp expects.
     * This is an associative array with the following fields:
     * - 'entityid': The entity id of the entity described in the metadata.
     * - 'AssertionConsumerService': String with the URL of the assertion consumer service which supports
     *   the browser-post binding.
     * - 'SingleLogoutService': String with the URL where we should send logout requests/responses.
     * - 'NameIDFormat': The name ID format this SP expects. This may be unset.
     * - 'certData': X509Certificate for entity (if present).
     *
     * Metadata must be loaded with one of the parse functions before this function can be called.
     *
     * @return array An associative array with metadata or NULL if we are unable to generate metadata for a SAML 2.x SP.
     */
    public function getMetadata20SP()
    {
        $ret = $this->getMetadataCommon();
        $ret['metadata-set'] = 'saml20-sp-remote';

        // find SP information which supports the SAML 2.0 protocol
        $spd = $this->getSPDescriptors(self::$SAML20Protocols);
        if (count($spd) === 0) {
            return null;
        }

        // we currently only look at the first SPDescriptor which supports SAML 2.0
        $spd = $spd[0];

        // add expire time to metadata
        if (array_key_exists('expire', $spd)) {
            $ret['expire'] = $spd['expire'];
        }

        // find the assertion consumer service endpoints
        $ret['AssertionConsumerService'] = $spd['AssertionConsumerService'];


        // find the single logout service endpoint
        $ret['SingleLogoutService'] = $spd['SingleLogoutService'];


        // find the NameIDFormat. This may not exist
        if (count($spd['nameIDFormats']) > 0) {
            // SimpleSAMLphp currently only supports a single NameIDFormat pr. SP. We use the first one
            $ret['NameIDFormat'] = $spd['nameIDFormats'][0];
        }

        // add the list of attributes the SP should receive
        if (array_key_exists('attributes', $spd)) {
            $ret['attributes'] = $spd['attributes'];
        }
        if (array_key_exists('attributes.required', $spd)) {
            $ret['attributes.required'] = $spd['attributes.required'];
        }
        if (array_key_exists('attributes.NameFormat', $spd)) {
            $ret['attributes.NameFormat'] = $spd['attributes.NameFormat'];
        }

        // add name & description
        if (array_key_exists('name', $spd)) {
            $ret['name'] = $spd['name'];
        }
        if (array_key_exists('description', $spd)) {
            $ret['description'] = $spd['description'];
        }

        // add public keys
        if (!empty($spd['keys'])) {
            $ret['keys'] = $spd['keys'];
        }

        // add validate.authnrequest
        if (array_key_exists('AuthnRequestsSigned', $spd)) {
            $ret['validate.authnrequest'] = $spd['AuthnRequestsSigned'];
        }

        // add saml20.sign.assertion
        if (array_key_exists('WantAssertionsSigned', $spd)) {
            $ret['saml20.sign.assertion'] = $spd['WantAssertionsSigned'];
        }

        // add extensions
        $this->addExtensions($ret, $spd);

        // prioritize mdui:DisplayName as the name if available
        if (!empty($ret['UIInfo']['DisplayName'])) {
            $ret['name'] = $ret['UIInfo']['DisplayName'];
        }

        return $ret;
    }


    /**
     * This function returns the metadata for SAML 2.0 IdPs in the format SimpleSAMLphp expects.
     * This is an associative array with the following fields:
     * - 'entityid': The entity id of the entity described in the metadata.
     * - 'name': Auto generated name for this entity. Currently set to the entity id.
     * - 'SingleSignOnService': String with the URL of the SSO service which supports the redirect binding.
     * - 'SingleLogoutService': String with the URL where we should send logout requests(/responses).
     * - 'SingleLogoutServiceResponse': String where we should send logout responses (if this is different from
     *   the 'SingleLogoutService' endpoint.
     * - 'NameIDFormats': The name ID formats this IdP supports.
     * - 'certData': X509Certificate for entity (if present).
     * - 'certFingerprint': Fingerprint of the X509Certificate from the metadata.
     *
     * Metadata must be loaded with one of the parse functions before this function can be called.
     *
     * @return array An associative array with metadata or NULL if we are unable to generate metadata for a SAML 2.0
     *     IdP.
     */
    public function getMetadata20IdP()
    {
        $ret = $this->getMetadataCommon();
        $ret['metadata-set'] = 'saml20-idp-remote';

        // find IdP information which supports the SAML 2.0 protocol
        $idp = $this->getIdPDescriptors(self::$SAML20Protocols);
        if (count($idp) === 0) {
            return null;
        }

        // we currently only look at the first IDP descriptor which supports SAML 2.0
        $idp = $idp[0];

        // add expire time to metadata
        if (array_key_exists('expire', $idp)) {
            $ret['expire'] = $idp['expire'];
        }

        // enable redirect.sign if WantAuthnRequestsSigned is enabled
        if ($idp['WantAuthnRequestsSigned']) {
            $ret['sign.authnrequest'] = true;
        }

        // find the SSO service endpoint
        $ret['SingleSignOnService'] = $idp['SingleSignOnService'];

        // find the single logout service endpoint
        $ret['SingleLogoutService'] = $idp['SingleLogoutService'];

        // find the ArtifactResolutionService endpoint
        $ret['ArtifactResolutionService'] = $idp['ArtifactResolutionService'];

        // add supported nameIDFormats
        $ret['NameIDFormats'] = $idp['nameIDFormats'];

        // add public keys
        if (!empty($idp['keys'])) {
            $ret['keys'] = $idp['keys'];
        }

        // add extensions
        $this->addExtensions($ret, $idp);

        // prioritize mdui:DisplayName as the name if available
        if (!empty($ret['UIInfo']['DisplayName'])) {
            $ret['name'] = $ret['UIInfo']['DisplayName'];
        }

        return $ret;
    }


    /**
     * Retrieve AttributeAuthorities from the metadata.
     *
     * @return array Array of AttributeAuthorityDescriptor entries.
     */
    public function getAttributeAuthorities()
    {
        return $this->attributeAuthorityDescriptors;
    }


    /**
     * Parse a RoleDescriptorType element.
     *
     * The returned associative array has the following elements:
     * - 'protocols': Array with the protocols supported.
     * - 'expire': Timestamp for when this descriptor expires.
     * - 'keys': Array of associative arrays with the elements from parseKeyDescriptor.
     *
     * @param SAML2_XML_md_RoleDescriptor $element The element we should extract metadata from.
     * @param int|NULL                    $expireTime The unix timestamp for when this element should expire, or
     *                             NULL if unknown.
     *
     * @return array An associative array with metadata we have extracted from this element.
     */
    private static function parseRoleDescriptorType(SAML2_XML_md_RoleDescriptor $element, $expireTime)
    {
        assert('is_null($expireTime) || is_int($expireTime)');

        $ret = array();

        $expireTime = self::getExpireTime($element, $expireTime);

        if ($expireTime !== null) {
            // we got an expired timestamp, either from this element or one of the parent elements
            $ret['expire'] = $expireTime;
        }

        $ret['protocols'] = $element->protocolSupportEnumeration;

        // process KeyDescriptor elements
        $ret['keys'] = array();
        foreach ($element->KeyDescriptor as $kd) {
            $key = self::parseKeyDescriptor($kd);
            if ($key !== null) {
                $ret['keys'][] = $key;
            }
        }

        $ext = self::processExtensions($element);
        $ret['scope'] = $ext['scope'];
        $ret['tags'] = $ext['tags'];
        $ret['EntityAttributes'] = $ext['EntityAttributes'];
        $ret['UIInfo'] = $ext['UIInfo'];
        $ret['DiscoHints'] = $ext['DiscoHints'];

        return $ret;
    }


    /**
     * This function extracts metadata from a SSODescriptor element.
     *
     * The returned associative array has the following elements:
     * - 'protocols': Array with the protocols this SSODescriptor supports.
     * - 'SingleLogoutService': Array with the single logout service endpoints. Each endpoint is stored
     *   as an associative array with the elements that parseGenericEndpoint returns.
     * - 'nameIDFormats': The NameIDFormats supported by this SSODescriptor. This may be an empty array.
     * - 'keys': Array of associative arrays with the elements from parseKeyDescriptor:
     *
     * @param SAML2_XML_md_SSODescriptorType $element The element we should extract metadata from.
     * @param int|NULL                       $expireTime The unix timestamp for when this element should expire, or
     *                             NULL if unknown.
     *
     * @return array An associative array with metadata we have extracted from this element.
     */
    private static function parseSSODescriptor(SAML2_XML_md_SSODescriptorType $element, $expireTime)
    {
        assert('is_null($expireTime) || is_int($expireTime)');

        $sd = self::parseRoleDescriptorType($element, $expireTime);

        // find all SingleLogoutService elements
        $sd['SingleLogoutService'] = self::extractEndpoints($element->SingleLogoutService);

        // find all ArtifactResolutionService elements
        $sd['ArtifactResolutionService'] = self::extractEndpoints($element->ArtifactResolutionService);


        // process NameIDFormat elements
        $sd['nameIDFormats'] = $element->NameIDFormat;

        return $sd;
    }


    /**
     * This function extracts metadata from a SPSSODescriptor element.
     *
     * @param SAML2_XML_md_SPSSODescriptor $element The element which should be parsed.
     * @param int|NULL                     $expireTime The unix timestamp for when this element should expire, or
     *                             NULL if unknown.
     */
    private function processSPSSODescriptor(SAML2_XML_md_SPSSODescriptor $element, $expireTime)
    {
        assert('is_null($expireTime) || is_int($expireTime)');

        $sp = self::parseSSODescriptor($element, $expireTime);

        // find all AssertionConsumerService elements
        $sp['AssertionConsumerService'] = self::extractEndpoints($element->AssertionConsumerService);

        // find all the attributes and SP name...
        $attcs = $element->AttributeConsumingService;
        if (count($attcs) > 0) {
            self::parseAttributeConsumerService($attcs[0], $sp);
        }

        // check AuthnRequestsSigned
        if ($element->AuthnRequestsSigned !== null) {
            $sp['AuthnRequestsSigned'] = $element->AuthnRequestsSigned;
        }

        // check WantAssertionsSigned
        if ($element->WantAssertionsSigned !== null) {
            $sp['WantAssertionsSigned'] = $element->WantAssertionsSigned;
        }

        $this->spDescriptors[] = $sp;
    }


    /**
     * This function extracts metadata from a IDPSSODescriptor element.
     *
     * @param SAML2_XML_md_IDPSSODescriptor $element The element which should be parsed.
     * @param int|NULL                      $expireTime The unix timestamp for when this element should expire, or
     *                             NULL if unknown.
     */
    private function processIDPSSODescriptor(SAML2_XML_md_IDPSSODescriptor $element, $expireTime)
    {
        assert('is_null($expireTime) || is_int($expireTime)');

        $idp = self::parseSSODescriptor($element, $expireTime);

        // find all SingleSignOnService elements
        $idp['SingleSignOnService'] = self::extractEndpoints($element->SingleSignOnService);

        if ($element->WantAuthnRequestsSigned) {
            $idp['WantAuthnRequestsSigned'] = true;
        } else {
            $idp['WantAuthnRequestsSigned'] = false;
        }

        $this->idpDescriptors[] = $idp;
    }


    /**
     * This function extracts metadata from a AttributeAuthorityDescriptor element.
     *
     * @param SAML2_XML_md_AttributeAuthorityDescriptor $element The element which should be parsed.
     * @param int|NULL                                  $expireTime The unix timestamp for when this element should
     *     expire, or NULL if unknown.
     */
    private function processAttributeAuthorityDescriptor(
        SAML2_XML_md_AttributeAuthorityDescriptor $element,
        $expireTime
    ) {
        assert('is_null($expireTime) || is_int($expireTime)');

        $aad = self::parseRoleDescriptorType($element, $expireTime);
        $aad['entityid'] = $this->entityId;
        $aad['metadata-set'] = 'attributeauthority-remote';

        $aad['AttributeService'] = self::extractEndpoints($element->AttributeService);
        $aad['AssertionIDRequestService'] = self::extractEndpoints($element->AssertionIDRequestService);
        $aad['NameIDFormat'] = $element->NameIDFormat;

        $this->attributeAuthorityDescriptors[] = $aad;
    }


    /**
     * Parse an Extensions element.
     *
     * @param mixed $element The element which contains the Extensions element.
     *
     * @return array An associative array with the extensions parsed.
     */
    private static function processExtensions($element)
    {
        $ret = array(
            'scope'            => array(),
            'tags'             => array(),
            'EntityAttributes' => array(),
            'UIInfo'           => array(),
            'DiscoHints'       => array(),
        );

        foreach ($element->Extensions as $e) {

            if ($e instanceof SAML2_XML_shibmd_Scope) {
                $ret['scope'][] = $e->scope;
                continue;
            }

            // Entity Attributes are only allowed at entity level extensions and not at RoleDescriptor level
            if ($element instanceof SAML2_XML_md_EntityDescriptor) {
                if ($e instanceof SAML2_XML_mdattr_EntityAttributes && !empty($e->children)) {
                    foreach ($e->children as $attr) {
                        // only saml:Attribute are currently supported here. The specifications also allows
                        // saml:Assertions, which more complex processing
                        if ($attr instanceof SAML2_XML_saml_Attribute) {
                            if (empty($attr->Name) || empty($attr->AttributeValue)) {
                                continue;
                            }

                            // attribute names that is not URI is prefixed as this: '{nameformat}name'
                            $name = $attr->Name;
                            if (empty($attr->NameFormat)) {
                                $name = '{'.SAML2_Const::NAMEFORMAT_UNSPECIFIED.'}'.$attr->Name;
                            } elseif ($attr->NameFormat !== 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri') {
                                $name = '{'.$attr->NameFormat.'}'.$attr->Name;
                            }

                            $values = array();
                            foreach ($attr->AttributeValue as $attrvalue) {
                                $values[] = $attrvalue->getString();
                            }

                            $ret['EntityAttributes'][$name] = $values;
                        }
                    }
                }
            }

            // UIInfo elements are only allowed at RoleDescriptor level extensions
            if ($element instanceof SAML2_XML_md_RoleDescriptor) {
                if ($e instanceof SAML2_XML_mdui_UIInfo) {

                    $ret['UIInfo']['DisplayName'] = $e->DisplayName;
                    $ret['UIInfo']['Description'] = $e->Description;
                    $ret['UIInfo']['InformationURL'] = $e->InformationURL;
                    $ret['UIInfo']['PrivacyStatementURL'] = $e->PrivacyStatementURL;

                    foreach ($e->Keywords as $uiItem) {
                        if (!($uiItem instanceof SAML2_XML_mdui_Keywords)
                            || empty($uiItem->Keywords)
                            || empty($uiItem->lang)
                        ) {
                            continue;
                        }
                        $ret['UIInfo']['Keywords'][$uiItem->lang] = $uiItem->Keywords;
                    }
                    foreach ($e->Logo as $uiItem) {
                        if (!($uiItem instanceof SAML2_XML_mdui_Logo)
                            || empty($uiItem->url)
                            || empty($uiItem->height)
                            || empty($uiItem->width)
                        ) {
                            continue;
                        }
                        $logo = array(
                            'url'    => $uiItem->url,
                            'height' => $uiItem->height,
                            'width'  => $uiItem->width,
                        );
                        if (!empty($uiItem->lang)) {
                            $logo['lang'] = $uiItem->lang;
                        }
                        $ret['UIInfo']['Logo'][] = $logo;
                    }
                }
            }

            // DiscoHints elements are only allowed at IDPSSODescriptor level extensions
            if ($element instanceof SAML2_XML_md_IDPSSODescriptor) {

                if ($e instanceof SAML2_XML_mdui_DiscoHints) {
                    $ret['DiscoHints']['IPHint'] = $e->IPHint;
                    $ret['DiscoHints']['DomainHint'] = $e->DomainHint;
                    $ret['DiscoHints']['GeolocationHint'] = $e->GeolocationHint;
                }
            }

            if (!($e instanceof SAML2_XML_Chunk)) {
                continue;
            }

            if ($e->localName === 'Attribute' && $e->namespaceURI === SAML2_Const::NS_SAML) {
                $attribute = $e->getXML();

                $name = $attribute->getAttribute('Name');
                $values = array_map(
                    array('SimpleSAML\Utils\XML', 'getDOMText'),
                    SimpleSAML\Utils\XML::getDOMChildren($attribute, 'AttributeValue', '@saml2')
                );

                if ($name === 'tags') {
                    foreach ($values as $tagname) {
                        if (!empty($tagname)) {
                            $ret['tags'][] = $tagname;
                        }
                    }
                }
            }
        }
        return $ret;
    }


    /**
     * Parse and process a Organization element.
     *
     * @param SAML2_XML_md_Organization $element The Organization element.
     */
    private function processOrganization(SAML2_XML_md_Organization $element)
    {
        $this->organizationName = $element->OrganizationName;
        $this->organizationDisplayName = $element->OrganizationDisplayName;
        $this->organizationURL = $element->OrganizationURL;
    }


    /**
     * Parse and process a ContactPerson element.
     *
     * @param SAML2_XML_md_ContactPerson $element The ContactPerson element.
     */

    private function processContactPerson(SAML2_XML_md_ContactPerson $element)
    {
        $contactPerson = array();
        if (!empty($element->contactType)) {
            $contactPerson['contactType'] = $element->contactType;
        }
        if (!empty($element->Company)) {
            $contactPerson['company'] = $element->Company;
        }
        if (!empty($element->GivenName)) {
            $contactPerson['givenName'] = $element->GivenName;
        }
        if (!empty($element->SurName)) {
            $contactPerson['surName'] = $element->SurName;
        }
        if (!empty($element->EmailAddress)) {
            $contactPerson['emailAddress'] = $element->EmailAddress;
        }
        if (!empty($element->TelephoneNumber)) {
            $contactPerson['telephoneNumber'] = $element->TelephoneNumber;
        }
        if (!empty($contactPerson)) {
            $this->contacts[] = $contactPerson;
        }
    }


    /**
     * This function parses AttributeConsumerService elements.
     *
     * @param SAML2_XML_md_AttributeConsumingService $element The AttributeConsumingService to parse.
     * @param array $sp The array with the SP's metadata.
     */
    private static function parseAttributeConsumerService(SAML2_XML_md_AttributeConsumingService $element, &$sp)
    {
        assert('is_array($sp)');

        $sp['name'] = $element->ServiceName;
        $sp['description'] = $element->ServiceDescription;

        $format = null;
        $sp['attributes'] = array();
        $sp['attributes.required'] = array();
        foreach ($element->RequestedAttribute as $child) {
            $attrname = $child->Name;
            $sp['attributes'][] = $attrname;

            if ($child->isRequired !== null && $child->isRequired === true) {
                $sp['attributes.required'][] = $attrname;
            }

            if ($child->NameFormat !== null) {
                $attrformat = $child->NameFormat;
            } else {
                $attrformat = SAML2_Const::NAMEFORMAT_UNSPECIFIED;
            }

            if ($format === null) {
                $format = $attrformat;
            } elseif ($format !== $attrformat) {
                $format = SAML2_Const::NAMEFORMAT_UNSPECIFIED;
            }
        }

        if (empty($sp['attributes'])) {
            // a really invalid configuration: all AttributeConsumingServices should have one or more attributes
            unset($sp['attributes']);
        }
        if (empty($sp['attributes.required'])) {
            unset($sp['attributes.required']);
        }

        if ($format !== SAML2_Const::NAMEFORMAT_UNSPECIFIED && $format !== null) {
            $sp['attributes.NameFormat'] = $format;
        }
    }


    /**
     * This function is a generic endpoint element parser.
     *
     * The returned associative array has the following elements:
     * - 'Binding': The binding this endpoint uses.
     * - 'Location': The URL to this endpoint.
     * - 'ResponseLocation': The URL where responses should be sent. This may not exist.
     * - 'index': The index of this endpoint. This attribute is only for indexed endpoints.
     * - 'isDefault': Whether this endpoint is the default endpoint for this type. This attribute may not exist.
     *
     * @param SAML2_XML_md_EndpointType $element The element which should be parsed.
     *
     * @return array An associative array with the data we have extracted from the element.
     */
    private static function parseGenericEndpoint(SAML2_XML_md_EndpointType $element)
    {
        $ep = array();

        $ep['Binding'] = $element->Binding;
        $ep['Location'] = $element->Location;

        if ($element->ResponseLocation !== null) {
            $ep['ResponseLocation'] = $element->ResponseLocation;
        }

        if ($element instanceof SAML2_XML_md_IndexedEndpointType) {
            $ep['index'] = $element->index;

            if ($element->isDefault !== null) {
                $ep['isDefault'] = $element->isDefault;
            }
        }

        return $ep;
    }


    /**
     * Extract generic endpoints.
     *
     * @param array $endpoints The endpoints we should parse.
     *
     * @return array Array of parsed endpoints.
     */
    private static function extractEndpoints(array $endpoints)
    {
        $ret = array();
        foreach ($endpoints as $ep) {
            $ret[] = self::parseGenericEndpoint($ep);
        }

        return $ret;
    }


    /**
     * This function parses a KeyDescriptor element. It currently only supports keys with a single
     * X509 certificate.
     *
     * The associative array for a key can contain:
     * - 'encryption': Indicates whether this key can be used for encryption.
     * - 'signing': Indicates whether this key can be used for signing.
     * - 'type: The type of the key. 'X509Certificate' is the only key type we support.
     * - 'X509Certificate': The contents of the first X509Certificate element (if the type is 'X509Certificate ').
     *
     * @param SAML2_XML_md_KeyDescriptor $kd The KeyDescriptor element.
     *
     * @return array|null An associative array describing the key, or null if this is an unsupported key.
     */
    private static function parseKeyDescriptor(SAML2_XML_md_KeyDescriptor $kd)
    {
        $r = array();

        if ($kd->use === 'encryption') {
            $r['encryption'] = true;
            $r['signing'] = false;
        } elseif ($kd->use === 'signing') {
            $r['encryption'] = false;
            $r['signing'] = true;
        } else {
            $r['encryption'] = true;
            $r['signing'] = true;
        }

        $keyInfo = $kd->KeyInfo;

        foreach ($keyInfo->info as $i) {
            if ($i instanceof SAML2_XML_ds_X509Data) {
                foreach ($i->data as $d) {
                    if ($d instanceof SAML2_XML_ds_X509Certificate) {
                        $r['type'] = 'X509Certificate';
                        $r['X509Certificate'] = $d->certificate;
                        return $r;
                    }
                }
            }
        }

        return null;
    }


    /**
     * This function finds SP descriptors which supports one of the given protocols.
     *
     * @param $protocols Array with the protocols we accept.
     *
     * @return Array with SP descriptors which supports one of the given protocols.
     */
    private function getSPDescriptors($protocols)
    {
        assert('is_array($protocols)');

        $ret = array();

        foreach ($this->spDescriptors as $spd) {
            $sharedProtocols = array_intersect($protocols, $spd['protocols']);
            if (count($sharedProtocols) > 0) {
                $ret[] = $spd;
            }
        }

        return $ret;
    }


    /**
     * This function finds IdP descriptors which supports one of the given protocols.
     *
     * @param $protocols Array with the protocols we accept.
     *
     * @return Array with IdP descriptors which supports one of the given protocols.
     */
    private function getIdPDescriptors($protocols)
    {
        assert('is_array($protocols)');

        $ret = array();

        foreach ($this->idpDescriptors as $idpd) {
            $sharedProtocols = array_intersect($protocols, $idpd['protocols']);
            if (count($sharedProtocols) > 0) {
                $ret[] = $idpd;
            }
        }

        return $ret;
    }


    /**
     * This function locates the EntityDescriptor node in a DOMDocument. This node should
     * be the first (and only) node in the document.
     *
     * This function will throw an exception if it is unable to locate the node.
     *
     * @param DOMDocument $doc The DOMDocument where we should find the EntityDescriptor node.
     *
     * @return SAML2_XML_md_EntityDescriptor The DOMEntity which represents the EntityDescriptor.
     * @throws Exception If the document is empty or the first element is not an EntityDescriptor element.
     */
    private static function findEntityDescriptor($doc)
    {
        assert('$doc instanceof DOMDocument');

        // find the EntityDescriptor DOMElement. This should be the first (and only) child of the DOMDocument
        $ed = $doc->documentElement;

        if ($ed === null) {
            throw new Exception('Failed to load SAML metadata from empty XML document.');
        }

        if (SimpleSAML\Utils\XML::isDOMElementOfType($ed, 'EntityDescriptor', '@md') === false) {
            throw new Exception('Expected first element in the metadata document to be an EntityDescriptor element.');
        }

        return new SAML2_XML_md_EntityDescriptor($ed);
    }


    /**
     * If this EntityDescriptor was signed this function use the public key to check the signature.
     *
     * @param array $certificates One ore more certificates with the public key. This makes it possible
     *                      to do a key rollover.
     *
     * @return boolean True if it is possible to check the signature with the certificate, false otherwise.
     * @throws Exception If the certificate file cannot be found.
     */
    public function validateSignature($certificates)
    {
        foreach ($certificates as $cert) {
            assert('is_string($cert)');
            $certFile = \SimpleSAML\Utils\Config::getCertPath($cert);
            if (!file_exists($certFile)) {
                throw new Exception(
                    'Could not find certificate file ['.$certFile.'], which is needed to validate signature'
                );
            }
            $certData = file_get_contents($certFile);

            foreach ($this->validators as $validator) {
                $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type' => 'public'));
                $key->loadKey($certData);
                try {
                    if ($validator->validate($key)) {
                        return true;
                    }
                } catch (Exception $e) {
                    // this certificate did not sign this element, skip
                }
            }
        }
        SimpleSAML_Logger::debug('Could not validate signature');
        return false;
    }


    /**
     * This function checks if this EntityDescriptor was signed with a certificate with the
     * given fingerprint.
     *
     * @param string $fingerprint Fingerprint of the certificate which should have been used to sign this
     *                      EntityDescriptor.
     *
     * @return boolean True if it was signed with the certificate with the given fingerprint, false otherwise.
     */
    public function validateFingerprint($fingerprint)
    {
        assert('is_string($fingerprint)');

        $fingerprint = strtolower(str_replace(":", "", $fingerprint));

        $candidates = array();
        foreach ($this->validators as $validator) {
            foreach ($validator->getValidatingCertificates() as $cert) {

                $fp = strtolower(sha1(base64_decode($cert)));
                $candidates[] = $fp;
                if ($fp === $fingerprint) {
                    return true;
                }
            }
        }
        SimpleSAML_Logger::debug('Fingerprint was ['.$fingerprint.'] not one of ['.join(', ', $candidates).']');
        return false;
    }
}
