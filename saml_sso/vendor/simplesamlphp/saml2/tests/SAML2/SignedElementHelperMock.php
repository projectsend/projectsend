<?php

/**
 * Class SAML2_SignedElementHelperMock
 */
class SAML2_SignedElementHelperMock extends SAML2_SignedElementHelper
{
    /**
     * @param DOMElement $xml
     */
    public function __construct(DOMElement $xml = NULL)
    {
        parent::__construct($xml);
    }

    /**
     * @return DOMElement
     */
    public function toSignedXML()
    {
        $doc = SAML2_DOMDocumentFactory::create();
        $root = $doc->createElement('root');
        $doc->appendChild($root);

        $child = $doc->createElement('child');
        $root->appendChild($child);

        $txt = $doc->createTextNode('sometext');
        $child->appendChild($txt);

        $this->signElement($root, $child);

        return $root;
    }
}
