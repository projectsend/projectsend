<?php

/**
 * Class SAML2_StatusResponseTest
 */
class SAML2_StatusResponseTest extends PHPUnit_Framework_TestCase
{
    public function testMarshalling()
    {
        $response = new SAML2_Response();
        $response->setStatus(array(
            'Code' => 'OurStatusCode',
            'SubCode' => 'OurSubStatusCode',
            'Message' => 'OurMessageText',
        ));

        $responseElement = $response->toUnsignedXML();

        $statusElements = SAML2_Utils::xpQuery($responseElement, './saml_protocol:Status');
        $this->assertCount(1, $statusElements);

        $statusCodeElements = SAML2_Utils::xpQuery($statusElements[0], './saml_protocol:StatusCode');
        $this->assertCount(1, $statusCodeElements);
        $this->assertEquals('OurStatusCode', $statusCodeElements[0]->getAttribute("Value"));

        $nestedStatusCodeElements = SAML2_Utils::xpQuery($statusCodeElements[0], './saml_protocol:StatusCode');
        $this->assertCount(1, $nestedStatusCodeElements);
        $this->assertEquals('OurSubStatusCode', $nestedStatusCodeElements[0]->getAttribute("Value"));

        $statusMessageElements = SAML2_Utils::xpQuery($statusElements[0], './saml_protocol:StatusMessage');
        $this->assertCount(1, $statusMessageElements);
        $this->assertEquals('OurMessageText', $statusMessageElements[0]->textContent);
    }
}
