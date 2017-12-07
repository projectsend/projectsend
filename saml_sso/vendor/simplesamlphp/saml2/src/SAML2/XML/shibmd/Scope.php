<?php

/**
 * Class which represents the Scope element found in Shibboleth metadata.
 *
 * @link https://wiki.shibboleth.net/confluence/display/SHIB/ShibbolethMetadataProfile
 * @package SimpleSAMLphp
 */
class SAML2_XML_shibmd_Scope
{
    /**
     * The namespace used for the Scope extension element.
     */
    const NS = 'urn:mace:shibboleth:metadata:1.0';

    /**
     * The scope.
     *
     * @var string
     */
    public $scope;

    /**
     * Whether this is a regexp scope.
     *
     * @var bool|NULL
     */
    public $regexp = NULL;

    /**
     * Create a Scope.
     *
     * @param DOMElement|NULL $xml The XML element we should load.
     */
    public function __construct(DOMElement $xml = NULL)
    {
        if ($xml === NULL) {
            return;
        }

        $this->scope = $xml->textContent;
        $this->regexp = SAML2_Utils::parseBoolean($xml, 'regexp', NULL);
    }

    /**
     * Convert this Scope to XML.
     *
     * @param DOMElement $parent The element we should append this Scope to.
     * @return DOMElement
     */
    public function toXML(DOMElement $parent)
    {
        assert('is_string($this->scope)');
        assert('is_bool($this->regexp) || is_null($this->regexp)');

        $doc = $parent->ownerDocument;

        $e = $doc->createElementNS(SAML2_XML_shibmd_Scope::NS, 'shibmd:Scope');
        $parent->appendChild($e);

        $e->appendChild($doc->createTextNode($this->scope));

        if ($this->regexp === TRUE) {
            $e->setAttribute('regexp', 'true');
        } elseif ($this->regexp === FALSE) {
            $e->setAttribute('regexp', 'false');
        }

        return $e;
    }

}
