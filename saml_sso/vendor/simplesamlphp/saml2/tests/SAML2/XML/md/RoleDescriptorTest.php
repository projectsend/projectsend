<?php

require 'RoleDescriptorMock.php';

class SAML2_XML_md_RoleDescriptorTest extends \PHPUnit_Framework_TestCase
{
    public function testMarshalling()
    {
        $roleDescriptor = new SAML2_XML_md_RoleDescriptorMock();
        $roleDescriptor->ID = 'SomeID';
        $roleDescriptor->validUntil = 1234567890;
        $roleDescriptor->cacheDuration = 'PT5000S';
        $roleDescriptor->protocolSupportEnumeration = array(
            'protocol1',
            'protocol2',
        );
        $roleDescriptor->errorURL = 'https://example.org/error';

        $document = SAML2_DOMDocumentFactory::fromString('<root />');
        $roleDescriptorElement = $roleDescriptor->toXML($document->firstChild);

        $roleDescriptorElement = SAML2_Utils::xpQuery($roleDescriptorElement, '/root/md:RoleDescriptor');
        $this->assertCount(1, $roleDescriptorElement);
        $roleDescriptorElement = $roleDescriptorElement[0];

        $this->assertEquals('SomeID', $roleDescriptorElement->getAttribute("ID"));
        $this->assertEquals('2009-02-13T23:31:30Z', $roleDescriptorElement->getAttribute("validUntil"));
        $this->assertEquals('PT5000S', $roleDescriptorElement->getAttribute("cacheDuration"));
        $this->assertEquals('protocol1 protocol2', $roleDescriptorElement->getAttribute("protocolSupportEnumeration"));
        $this->assertEquals('myns:MyElement', $roleDescriptorElement->getAttributeNS(SAML2_Const::NS_XSI, "type"));
        $this->assertEquals('http://example.org/mynsdefinition', $roleDescriptorElement->lookupNamespaceURI("myns"));
    }
}
