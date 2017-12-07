<?php

/**
 * Class SAML2_XML_saml_SubjectConfirmationTest
 */
class SAML2_XML_saml_SubjectConfirmationTest extends \PHPUnit_Framework_TestCase
{
    public function testMarshalling()
    {
        $subjectConfirmation = new SAML2_XML_saml_SubjectConfirmation();
        $subjectConfirmation->Method = 'SomeMethod';
        $subjectConfirmation->NameID = new SAML2_XML_saml_NameID();
        $subjectConfirmation->NameID->value = 'SomeNameIDValue';
        $subjectConfirmation->SubjectConfirmationData = new SAML2_XML_saml_SubjectConfirmationData();

        $document = SAML2_DOMDocumentFactory::fromString('<root />');
        $subjectConfirmationElement = $subjectConfirmation->toXML($document->firstChild);
        $subjectConfirmationElements = SAML2_Utils::xpQuery($subjectConfirmationElement, '//saml_assertion:SubjectConfirmation');
        $this->assertCount(1, $subjectConfirmationElements);
        $subjectConfirmationElement = $subjectConfirmationElements[0];

        $this->assertEquals('SomeMethod', $subjectConfirmationElement->getAttribute("Method"));
        $this->assertCount(1, SAML2_Utils::xpQuery($subjectConfirmationElement, "./saml_assertion:NameID"));
        $this->assertCount(1, SAML2_Utils::xpQuery($subjectConfirmationElement, "./saml_assertion:SubjectConfirmationData"));
    }

    public function testUnmarshalling()
    {
        $samlNamespace = SAML2_Const::NS_SAML;
        $document = SAML2_DOMDocumentFactory::fromString(
<<<XML
<saml:SubjectConfirmation xmlns:saml="{$samlNamespace}" Method="SomeMethod">
  <saml:NameID>SomeNameIDValue</saml:NameID>
  <saml:SubjectConfirmationData/>
</saml:SubjectConfirmation>
XML
        );

        $subjectConfirmation = new SAML2_XML_saml_SubjectConfirmation($document->firstChild);
        $this->assertEquals('SomeMethod', $subjectConfirmation->Method);
        $this->assertTrue($subjectConfirmation->NameID instanceof SAML2_XML_saml_NameID);
        $this->assertEquals('SomeNameIDValue', $subjectConfirmation->NameID->value);
        $this->assertTrue($subjectConfirmation->SubjectConfirmationData instanceof SAML2_XML_saml_SubjectConfirmationData);
    }
}
