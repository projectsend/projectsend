<?php

/**
 * Class SAML2_XML_md_EndpointType
 */
class SAML2_XML_md_EndpointTypeTest extends PHPUnit_Framework_TestCase
{
    public function testMarshalling()
    {
        $endpointType = new SAML2_XML_md_EndpointType();
        $endpointType->Binding = 'TestBinding';
        $endpointType->Location = 'TestLocation';

        $document = SAML2_DOMDocumentFactory::fromString('<root />');
        $endpointTypeElement = $endpointType->toXML($document->firstChild, 'md:Test');

        $endpointTypeElements = SAML2_Utils::xpQuery($endpointTypeElement, '/root/saml_metadata:Test');
        $this->assertCount(1, $endpointTypeElements);
        $endpointTypeElement = $endpointTypeElements[0];

        $this->assertEquals('TestBinding', $endpointTypeElement->getAttribute('Binding'));
        $this->assertEquals('TestLocation', $endpointTypeElement->getAttribute('Location'));
        $this->assertFalse($endpointTypeElement->hasAttribute('ResponseLocation'));

        $endpointType->ResponseLocation = 'TestResponseLocation';

        $document->loadXML('<root />');
        $endpointTypeElement = $endpointType->toXML($document->firstChild, 'md:Test');

        $endpointTypeElement = SAML2_Utils::xpQuery($endpointTypeElement, '/root/saml_metadata:Test');
        $this->assertCount(1, $endpointTypeElement);
        $endpointTypeElement = $endpointTypeElement[0];

        $this->assertEquals('TestResponseLocation', $endpointTypeElement->getAttribute('ResponseLocation'));
    }

    public function testUnmarshalling()
    {
        $mdNamespace = SAML2_Const::NS_MD;
        $document = SAML2_DOMDocumentFactory::fromString(
<<<XML
<md:Test xmlns:md="{$mdNamespace}" Binding="urn:something" Location="https://whatever/" xmlns:test="urn:test" test:attr="value" />
XML
        );
        $endpointType = new SAML2_XML_md_EndpointType($document->firstChild);
        $this->assertEquals(TRUE, $endpointType->hasAttributeNS('urn:test', 'attr'));
        $this->assertEquals('value', $endpointType->getAttributeNS('urn:test', 'attr'));
        $this->assertEquals(FALSE, $endpointType->hasAttributeNS('urn:test', 'invalid'));
        $this->assertEquals('', $endpointType->getAttributeNS('urn:test', 'invalid'));

        $endpointType->removeAttributeNS('urn:test', 'attr');
        $this->assertEquals(FALSE, $endpointType->hasAttributeNS('urn:test', 'attr'));
        $this->assertEquals('', $endpointType->getAttributeNS('urn:test', 'attr'));

        $endpointType->setAttributeNS('urn:test2', 'test2:attr2', 'value2');
        $this->assertEquals('value2', $endpointType->getAttributeNS('urn:test2', 'attr2'));

        $document->loadXML('<root />');
        $endpointTypeElement = $endpointType->toXML($document->firstChild, 'md:Test');
        $endpointTypeElements = SAML2_Utils::xpQuery($endpointTypeElement, '/root/saml_metadata:Test');
        $this->assertCount(1, $endpointTypeElements);
        $endpointTypeElement = $endpointTypeElements[0];

        $this->assertEquals('value2', $endpointTypeElement->getAttributeNS('urn:test2', 'attr2'));
        $this->assertEquals(FALSE, $endpointTypeElement->hasAttributeNS('urn:test', 'attr'));

    }
}
