<?php

/**
 * Class representing the saml:NameID element.
 *
 * @package SimpleSAMLphp
 */
class SAML2_XML_saml_NameID
{
    /**
     * The NameQualifier or the NameID.
     *
     * @var string|NULL
     */
    public $NameQualifier;

    /**
     * The SPNameQualifier or the NameID.
     *
     * @var string|NULL
     */
    public $SPNameQualifier;

    /**
     * The Format or the NameID.
     *
     * @var string|NULL
     */
    public $Format;

    /**
     * The SPProvidedID or the NameID.
     *
     * @var string|NULL
     */
    public $SPProvidedID;

    /**
     * The value of this NameID.
     *
     * @var string
     */
    public $value;

    /**
     * Initialize a saml:NameID.
     *
     * @param DOMElement|NULL $xml The XML element we should load.
     */
    public function __construct(DOMElement $xml = NULL)
    {
        if ($xml === NULL) {
            return;
        }

        if ($xml->hasAttribute('SPNameQualifier')) {
            $this->SPNameQualifier = $xml->getAttribute('SPNameQualifier');
        }

        if ($xml->hasAttribute('NameQualifier')) {
            $this->NameQualifier = $xml->getAttribute('NameQualifier');
        }

        if ($xml->hasAttribute('Format')) {
            $this->Format = $xml->getAttribute('Format');
        }

        if ($xml->hasAttribute('SPProvidedID')) {
            $this->SPProvidedID = $xml->getAttribute('SPProvidedID');
        }

        $this->value = trim($xml->textContent);
    }

    /**
     * Convert this NameID to XML.
     *
     * @param  DOMElement|NULL $parent The element we should append to.
     * @return DOMElement      This AdditionalMetadataLocation-element.
     */
    public function toXML(DOMElement $parent = NULL)
    {
        assert('is_string($this->NameQualifier) || is_null($this->NameQualifier)');
        assert('is_string($this->SPNameQualifier) || is_null($this->SPNameQualifier)');
        assert('is_string($this->Format) || is_null($this->Format)');
        assert('is_string($this->SPProvidedID) || is_null($this->SPProvidedID)');
        assert('is_string($this->value)');

        if ($parent === NULL) {
            $parent = SAML2_DOMDocumentFactory::create();
            $doc = $parent;
        } else {
            $doc = $parent->ownerDocument;
        }
        $e = $doc->createElementNS(SAML2_Const::NS_SAML, 'saml:NameID');
        $parent->appendChild($e);

        if ($this->NameQualifier !== NULL) {
            $e->setAttribute('NameQualifier', $this->NameQualifier);
        }

        if ($this->SPNameQualifier !== NULL) {
            $e->setAttribute('SPNameQualifier', $this->SPNameQualifier);
        }

        if ($this->Format !== NULL) {
            $e->setAttribute('Format', $this->Format);
        }

        if ($this->SPProvidedID !== NULL) {
            $e->setAttribute('SPProvidedID', $this->SPProvidedID);
        }

        $t = $doc->createTextNode($this->value);
        $e->appendChild($t);

        return $e;
    }

}
