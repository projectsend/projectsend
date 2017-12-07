<?php

/**
 * Class SAML2_XML_md_AdditionalMetadataLocationTest
 */
class SAML2_XML_md_AdditionalMetadataLocationTest extends PHPUnit_Framework_TestCase
{
    public function testMarshalling()
    {
        $document = SAML2_DOMDocumentFactory::fromString('<root/>');

        $additionalMetadataLocation = new SAML2_XML_md_AdditionalMetadataLocation();
        $additionalMetadataLocation->namespace = 'NamespaceAttribute';
        $additionalMetadataLocation->location = 'TheLocation';
        $additionalMetadataLocationElement = $additionalMetadataLocation->toXML($document->firstChild);

        $additionalMetadataLocationElements = SAML2_Utils::xpQuery(
            $additionalMetadataLocationElement,
            '/root/saml_metadata:AdditionalMetadataLocation'
        );
        $this->assertCount(1, $additionalMetadataLocationElements);
        $additionalMetadataLocationElement = $additionalMetadataLocationElements[0];

        $this->assertEquals('TheLocation', $additionalMetadataLocationElement->textContent);
        $this->assertEquals('NamespaceAttribute', $additionalMetadataLocationElement->getAttribute("namespace"));
    }

    public function testUnmarshalling()
    {
        $document = SAML2_DOMDocumentFactory::fromString(
            '<md:AdditionalMetadataLocation xmlns:md="' . SAML2_Const::NS_MD . '"'.
            ' namespace="TheNamespaceAttribute">LocationText</md:AdditionalMetadataLocation>'
        );
        $additionalMetadataLocation = new SAML2_XML_md_AdditionalMetadataLocation($document->firstChild);
        $this->assertEquals('TheNamespaceAttribute', $additionalMetadataLocation->namespace);
        $this->assertEquals('LocationText', $additionalMetadataLocation->location);

        $document->loadXML(
            '<md:AdditionalMetadataLocation xmlns:md="' . SAML2_Const::NS_MD . '"'.
            '>LocationText</md:AdditionalMetadataLocation>'
        );
        $this->setExpectedException('Exception', 'Missing namespace attribute on AdditionalMetadataLocation element.');
        new SAML2_XML_md_AdditionalMetadataLocation($document->firstChild);
    }
}
