<?php

/**
 * Class SAML2_XML_md_AttributeTest
 */
class SAML2_XML_md_AttributeTest extends \PHPUnit_Framework_TestCase
{
    public function testMarshalling()
    {
        $attribute = new SAML2_XML_saml_Attribute();
        $attribute->Name = 'TheName';
        $attribute->NameFormat = 'TheNameFormat';
        $attribute->FriendlyName = 'TheFriendlyName';
        $attribute->AttributeValue = array(
            new SAML2_XML_saml_AttributeValue('FirstValue'),
            new SAML2_XML_saml_AttributeValue('SecondValue'),
        );

        $document = SAML2_DOMDocumentFactory::fromString('<root />');
        $attributeElement = $attribute->toXML($document->firstChild);

        $attributeElements = SAML2_Utils::xpQuery($attributeElement, '/root/saml_assertion:Attribute');
        $this->assertCount(1, $attributeElements);
        $attributeElement = $attributeElements[0];

        $this->assertEquals('TheName', $attributeElement->getAttribute('Name'));
        $this->assertEquals('TheNameFormat', $attributeElement->getAttribute('NameFormat'));
        $this->assertEquals('TheFriendlyName', $attributeElement->getAttribute('FriendlyName'));
    }

    public function testUnmarshalling()
    {
        $samlNamespace = SAML2_Const::NS_SAML;
        $document = SAML2_DOMDocumentFactory::fromString(<<<XML
<saml:Attribute xmlns:saml="{$samlNamespace}" Name="TheName" NameFormat="TheNameFormat" FriendlyName="TheFriendlyName">
    <saml:AttributeValue>FirstValue</saml:AttributeValue>
    <saml:AttributeValue>SecondValue</saml:AttributeValue>
</saml:Attribute>
XML
        );

        $attribute = new SAML2_XML_saml_Attribute($document->firstChild);
        $this->assertEquals('TheName', $attribute->Name);
        $this->assertEquals('TheNameFormat', $attribute->NameFormat);
        $this->assertEquals('TheFriendlyName', $attribute->FriendlyName);
        $this->assertCount(2, $attribute->AttributeValue);
        $this->assertEquals('FirstValue', (string)$attribute->AttributeValue[0]);
        $this->assertEquals('SecondValue', (string)$attribute->AttributeValue[1]);

    }

    public function testUnmarshallingFailure()
    {
        $samlNamespace = SAML2_Const::NS_SAML;
        $document = SAML2_DOMDocumentFactory::fromString(<<<XML
<saml:Attribute xmlns:saml="{$samlNamespace}" NameFormat="TheNameFormat" FriendlyName="TheFriendlyName">
    <saml:AttributeValue>FirstValue</saml:AttributeValue>
    <saml:AttributeValue>SecondValue</saml:AttributeValue>
</saml:Attribute>
XML
        );
        $this->setExpectedException('Exception', 'Missing Name on Attribute.');
        new SAML2_XML_saml_Attribute($document->firstChild);
    }
}
