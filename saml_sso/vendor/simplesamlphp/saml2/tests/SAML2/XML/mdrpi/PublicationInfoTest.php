<?php

/**
 * Class SAML2_XML_mdrpi_PublicationInfoTest
 */
class SAML2_XML_mdrpi_PublicationInfoTest extends \PHPUnit_Framework_TestCase
{
    public function testMarshalling()
    {
        $publicationInfo = new SAML2_XML_mdrpi_PublicationInfo();
        $publicationInfo->publisher = 'TestPublisher';
        $publicationInfo->creationInstant = 1234567890;
        $publicationInfo->publicationId = 'PublicationIdValue';
        $publicationInfo->UsagePolicy = array(
            'en' => 'http://EnglishUsagePolicy',
            'no' => 'http://NorwegianUsagePolicy',
        );

        $document = SAML2_DOMDocumentFactory::fromString('<root />');
        $xml = $publicationInfo->toXML($document->firstChild);

        $publicationInfoElements = SAML2_Utils::xpQuery(
            $xml,
            '/root/*[local-name()=\'PublicationInfo\' and namespace-uri()=\'urn:oasis:names:tc:SAML:metadata:rpi\']'
        );
        $this->assertCount(1, $publicationInfoElements);
        $publicationInfoElement = $publicationInfoElements[0];

        $this->assertEquals('TestPublisher', $publicationInfoElement->getAttribute("publisher"));
        $this->assertEquals('2009-02-13T23:31:30Z', $publicationInfoElement->getAttribute("creationInstant"));
        $this->assertEquals('PublicationIdValue', $publicationInfoElement->getAttribute("publicationId"));

        $usagePolicyElements = SAML2_Utils::xpQuery(
            $publicationInfoElement,
            './*[local-name()=\'UsagePolicy\' and namespace-uri()=\'urn:oasis:names:tc:SAML:metadata:rpi\']'
        );
        $this->assertCount(2, $usagePolicyElements);

        $this->assertEquals('en', $usagePolicyElements[0]->getAttributeNS("http://www.w3.org/XML/1998/namespace", "lang"));
        $this->assertEquals('http://EnglishUsagePolicy', $usagePolicyElements[0]->textContent);
        $this->assertEquals('no', $usagePolicyElements[1]->getAttributeNS("http://www.w3.org/XML/1998/namespace", "lang"));
        $this->assertEquals('http://NorwegianUsagePolicy', $usagePolicyElements[1]->textContent);
    }

    public function testUnmarshalling()
    {
        $document = SAML2_DOMDocumentFactory::fromString(<<<XML
<mdrpi:PublicationInfo xmlns:mdrpi="urn:oasis:names:tc:SAML:metadata:rpi"
                       publisher="SomePublisher"
                       creationInstant="2011-01-01T00:00:00Z"
                       publicationId="SomePublicationId">
    <mdrpi:UsagePolicy xml:lang="en">http://TheEnglishUsagePolicy</mdrpi:UsagePolicy>
    <mdrpi:UsagePolicy xml:lang="no">http://TheNorwegianUsagePolicy</mdrpi:UsagePolicy>
</mdrpi:PublicationInfo>
XML
        );

        $publicationInfo = new SAML2_XML_mdrpi_PublicationInfo($document->firstChild);

        $this->assertEquals('SomePublisher', $publicationInfo->publisher);
        $this->assertEquals(1293840000, $publicationInfo->creationInstant);
        $this->assertEquals('SomePublicationId', $publicationInfo->publicationId);
        $this->assertCount(2, $publicationInfo->UsagePolicy);
        $this->assertEquals('http://TheEnglishUsagePolicy', $publicationInfo->UsagePolicy["en"]);
        $this->assertEquals('http://TheNorwegianUsagePolicy', $publicationInfo->UsagePolicy["no"]);
    }
}
