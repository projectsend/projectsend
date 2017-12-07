<?php

/**
 * Class for handling the metadata extensions for login and discovery user interface
 *
 * @link: http://docs.oasis-open.org/security/saml/Post2.0/sstc-saml-metadata-ui/v1.0/sstc-saml-metadata-ui-v1.0.pdf
 * @package SimpleSAMLphp
 */
class SAML2_XML_mdui_DiscoHints
{
    /**
     * The namespace used for the DiscoHints extension.
     */
    const NS = 'urn:oasis:names:tc:SAML:metadata:ui';

    /**
     * Array with child elements.
     *
     * The elements can be any of the other SAML2_XML_mdui_* elements.
     *
     * @var SAML2_XML_Chunk[]
     */
    public $children = array();

    /**
     * The IPHint, as an array of strings.
     *
     * @var string[]
     */
    public $IPHint = array();

    /**
     * The DomainHint, as an array of strings.
     *
     * @var string[]
     */
    public $DomainHint = array();

    /**
     * The GeolocationHint, as an array of strings.
     *
     * @var string[]
     */
    public $GeolocationHint = array();

    /**
     * Create a DiscoHints element.
     *
     * @param DOMElement|NULL $xml The XML element we should load.
     */
    public function __construct(DOMElement $xml = NULL)
    {
        if ($xml === NULL) {
            return;
        }

        $this->IPHint =          SAML2_Utils::extractStrings($xml, self::NS, 'IPHint');
        $this->DomainHint =      SAML2_Utils::extractStrings($xml, self::NS, 'DomainHint');
        $this->GeolocationHint = SAML2_Utils::extractStrings($xml, self::NS, 'GeolocationHint');

        foreach (SAML2_Utils::xpQuery($xml, "./*[namespace-uri()!='".self::NS."']") as $node) {
            $this->children[] = new SAML2_XML_Chunk($node);
        }
    }

    /**
     * Convert this DiscoHints to XML.
     *
     * @param DOMElement $parent The element we should append to.
     * @return DOMElement|NULL
     */
    public function toXML(DOMElement $parent)
    {
        assert('is_array($this->IPHint)');
        assert('is_array($this->DomainHint)');
        assert('is_array($this->GeolocationHint)');
        assert('is_array($this->children)');

        if (!empty($this->IPHint)
         || !empty($this->DomainHint)
         || !empty($this->GeolocationHint)
         || !empty($this->children)) {
            $doc = $parent->ownerDocument;

            $e = $doc->createElementNS(self::NS, 'mdui:DiscoHints');
            $parent->appendChild($e);

            if (!empty($this->children)) {
                foreach ($this->children as $child) {
                    $child->toXML($e);
                }
            }

            SAML2_Utils::addStrings($e, self::NS, 'mdui:IPHint', FALSE, $this->IPHint);
            SAML2_Utils::addStrings($e, self::NS, 'mdui:DomainHint', FALSE, $this->DomainHint);
            SAML2_Utils::addStrings($e, self::NS, 'mdui:GeolocationHint', FALSE, $this->GeolocationHint);

            return $e;
        }

        return NULL;
    }

}
