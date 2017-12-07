<?php

/**
 * Class SAML2_XML_md_NameIDTest
 */
class SAML2_XML_md_NameIDTest extends \PHPUnit_Framework_TestCase
{
    public function testMarshalling()
    {
        $nameId = new SAML2_XML_saml_NameID();
        $nameId->NameQualifier = 'TheNameQualifier';
        $nameId->SPNameQualifier = 'TheSPNameQualifier';
        $nameId->Format = 'TheFormat';
        $nameId->SPProvidedID = 'TheSPProvidedID';
        $nameId->value = 'TheNameIDValue';
        $nameIdElement = $nameId->toXML();

        $nameIdElements = SAML2_Utils::xpQuery($nameIdElement, '/saml_assertion:NameID');
        $this->assertCount(1, $nameIdElements);
        $nameIdElement = $nameIdElements[0];

        $this->assertEquals('TheNameQualifier', $nameIdElement->getAttribute("NameQualifier"));
        $this->assertEquals('TheSPNameQualifier', $nameIdElement->getAttribute("SPNameQualifier"));
        $this->assertEquals('TheFormat', $nameIdElement->getAttribute("Format"));
        $this->assertEquals('TheSPProvidedID', $nameIdElement->getAttribute("SPProvidedID"));
        $this->assertEquals('TheNameIDValue', $nameIdElement->textContent);
    }

    public function testUnmarshalling()
    {
        $samlNamespace = SAML2_Const::NS_SAML;
        $document = SAML2_DOMDocumentFactory::fromString(<<<XML
<saml:NameID xmlns:saml="{$samlNamespace}" NameQualifier="TheNameQualifier" SPNameQualifier="TheSPNameQualifier" Format="TheFormat" SPProvidedID="TheSPProvidedID">TheNameIDValue</saml:NameID>
XML
        );

        $nameId = new SAML2_XML_saml_NameID($document->firstChild);
        $this->assertEquals('TheNameQualifier', $nameId->NameQualifier);
        $this->assertEquals('TheSPNameQualifier', $nameId->SPNameQualifier);
        $this->assertEquals('TheFormat', $nameId->Format);
        $this->assertEquals('TheSPProvidedID', $nameId->SPProvidedID);
        $this->assertEquals('TheNameIDValue', $nameId->value);
    }
}




