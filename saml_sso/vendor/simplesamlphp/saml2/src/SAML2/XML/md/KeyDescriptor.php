<?php

/**
 * Class representing a KeyDescriptor element.
 *
 * @package SimpleSAMLphp
 */
class SAML2_XML_md_KeyDescriptor
{
    /**
     * What this key can be used for.
     *
     * 'encryption', 'signing' or NULL.
     *
     * @var string|NULL
     */
    public $use;

    /**
     * The KeyInfo for this key.
     *
     * @var SAML2_XML_ds_KeyInfo
     */
    public $KeyInfo;

    /**
     * Supported EncryptionMethods.
     *
     * Array of SAML2_XML_Chunk objects.
     *
     * @var SAML2_XML_Chunk[]
     */
    public $EncryptionMethod = array();

    /**
     * Initialize an KeyDescriptor.
     *
     * @param DOMElement|NULL $xml The XML element we should load.
     * @throws Exception
     */
    public function __construct(DOMElement $xml = NULL)
    {
        if ($xml === NULL) {
            return;
        }

        if ($xml->hasAttribute('use')) {
            $this->use = $xml->getAttribute('use');
        }

        $keyInfo = SAML2_Utils::xpQuery($xml, './ds:KeyInfo');
        if (count($keyInfo) > 1) {
            throw new Exception('More than one ds:KeyInfo in the KeyDescriptor.');
        } elseif (empty($keyInfo)) {
            throw new Exception('No ds:KeyInfo in the KeyDescriptor.');
        }
        $this->KeyInfo = new SAML2_XML_ds_KeyInfo($keyInfo[0]);

        foreach (SAML2_Utils::xpQuery($xml, './saml_metadata:EncryptionMethod') as $em) {
            $this->EncryptionMethod[] = new SAML2_XML_Chunk($em);
        }

    }

    /**
     * Convert this KeyDescriptor to XML.
     *
     * @param DOMElement $parent The element we should append this KeyDescriptor to.
     * @return DOMElement
     */
    public function toXML(DOMElement $parent)
    {
        assert('is_null($this->use) || is_string($this->use)');
        assert('$this->KeyInfo instanceof SAML2_XML_ds_KeyInfo');
        assert('is_array($this->EncryptionMethod)');

        $doc = $parent->ownerDocument;

        $e = $doc->createElementNS(SAML2_Const::NS_MD, 'md:KeyDescriptor');
        $parent->appendChild($e);

        if (isset($this->use)) {
            $e->setAttribute('use', $this->use);
        }

        $this->KeyInfo->toXML($e);

        foreach ($this->EncryptionMethod as $em) {
            $em->toXML($e);
        }

        return $e;
    }

}
