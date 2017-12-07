<?php

/**
 * Class SAML2_XML_md_IndexedEndpointTypeTest
 */
class SAML2_XML_md_IndexedEndpointTypeTest extends \PHPUnit_Framework_TestCase
{
    public function testMarshalling()
    {
        $indexedEndpointType = new SAML2_XML_md_IndexedEndpointType();
        $indexedEndpointType->Binding = 'TestBinding';
        $indexedEndpointType->Location = 'TestLocation';
        $indexedEndpointType->index = 42;
        $indexedEndpointType->isDefault = FALSE;

        $document = SAML2_DOMDocumentFactory::fromString('<root />');
        $indexedEndpointTypeElement = $indexedEndpointType->toXML($document->firstChild, 'md:Test');

        $indexedEndpointElements = SAML2_Utils::xpQuery($indexedEndpointTypeElement, '/root/saml_metadata:Test');
        $this->assertCount(1, $indexedEndpointElements);
        $indexedEndpointElement = $indexedEndpointElements[0];

        $this->assertEquals('TestBinding', $indexedEndpointElement->getAttribute('Binding'));
        $this->assertEquals('TestLocation', $indexedEndpointElement->getAttribute('Location'));
        $this->assertEquals('42', $indexedEndpointElement->getAttribute('index'));
        $this->assertEquals('false', $indexedEndpointElement->getAttribute('isDefault'));

        $indexedEndpointType->isDefault = TRUE;
        $document->loadXML('<root />');
        $indexedEndpointTypeElement = $indexedEndpointType->toXML($document->firstChild, 'md:Test');
        $indexedEndpointTypeElement = SAML2_Utils::xpQuery($indexedEndpointTypeElement, '/root/saml_metadata:Test');
        $this->assertCount(1, $indexedEndpointTypeElement);
        $this->assertEquals('true', $indexedEndpointTypeElement[0]->getAttribute('isDefault'));

        $indexedEndpointType->isDefault = NULL;
        $document->loadXML('<root />');
        $indexedEndpointTypeElement = $indexedEndpointType->toXML($document->firstChild, 'md:Test');
        $indexedEndpointTypeElement = SAML2_Utils::xpQuery($indexedEndpointTypeElement, '/root/saml_metadata:Test');
        $this->assertCount(1, $indexedEndpointTypeElement);
        $this->assertTrue(!$indexedEndpointTypeElement[0]->hasAttribute('isDefault'));
    }
}
