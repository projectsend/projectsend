<?php

/**
 * Class SAML2_XML_saml_SubjectConfirmationDataTest
 */
class SAML2_XML_saml_SubjectConfirmationDataTest extends \PHPUnit_Framework_TestCase
{
    public function testMarshalling()
    {
        $subjectConfirmationData = new SAML2_XML_saml_SubjectConfirmationData();
        $subjectConfirmationData->NotBefore = 987654321;
        $subjectConfirmationData->NotOnOrAfter = 1234567890;
        $subjectConfirmationData->Recipient = 'https://sp.example.org/asdf';
        $subjectConfirmationData->InResponseTo = 'SomeRequestID';
        $subjectConfirmationData->Address = '127.0.0.1';

        $document = SAML2_DOMDocumentFactory::fromString('<root />');
        $subjectConfirmationDataElement = $subjectConfirmationData->toXML($document->firstChild);

        $subjectConfirmationDataElements = SAML2_Utils::xpQuery(
            $subjectConfirmationDataElement,
            '//saml_assertion:SubjectConfirmationData'
        );
        $this->assertCount(1, $subjectConfirmationDataElements);
        $subjectConfirmationDataElement = $subjectConfirmationDataElements[0];

        $this->assertEquals('2001-04-19T04:25:21Z', $subjectConfirmationDataElement->getAttribute("NotBefore"));
        $this->assertEquals('2009-02-13T23:31:30Z', $subjectConfirmationDataElement->getAttribute("NotOnOrAfter"));
        $this->assertEquals('https://sp.example.org/asdf', $subjectConfirmationDataElement->getAttribute("Recipient"));
        $this->assertEquals('SomeRequestID', $subjectConfirmationDataElement->getAttribute("InResponseTo"));
        $this->assertEquals('127.0.0.1', $subjectConfirmationDataElement->getAttribute("Address"));
    }

    public function testUnmarshalling()
    {
        $samlNamespace = SAML2_Const::NS_SAML;
        $document = SAML2_DOMDocumentFactory::fromString(<<<XML
<saml:SubjectConfirmationData
    xmlns:saml="{$samlNamespace}"
    NotBefore="2001-04-19T04:25:21Z"
    NotOnOrAfter="2009-02-13T23:31:30Z"
    Recipient="https://sp.example.org/asdf"
    InResponseTo="SomeRequestID"
    Address="127.0.0.1"
    />
XML
        );

        $subjectConfirmationData = new SAML2_XML_saml_SubjectConfirmationData($document->firstChild);
        $this->assertEquals(987654321, $subjectConfirmationData->NotBefore);
        $this->assertEquals(1234567890, $subjectConfirmationData->NotOnOrAfter);
        $this->assertEquals('https://sp.example.org/asdf', $subjectConfirmationData->Recipient);
        $this->assertEquals('SomeRequestID', $subjectConfirmationData->InResponseTo);
        $this->assertEquals('127.0.0.1', $subjectConfirmationData->Address);
    }
}
