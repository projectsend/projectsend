<?php

/**
 * Class SAML2_AuthnRequestTest
 */
class SAML2_AuthnRequestTest extends PHPUnit_Framework_TestCase
{
    public function testUnmarshalling()
    {
        $authnRequest = new SAML2_AuthnRequest();
        $authnRequest->setRequestedAuthnContext(array(
            'AuthnContextClassRef' => array(
                'accr1',
                'accr2',
            ),
            'Comparison' => 'better',
        ));

        $authnRequestElement = $authnRequest->toUnsignedXML();

        $requestedAuthnContextElements = SAML2_Utils::xpQuery(
            $authnRequestElement,
            './saml_protocol:RequestedAuthnContext'
        );
        $this->assertCount(1, $requestedAuthnContextElements);

        $requestedAuthnConextElement = $requestedAuthnContextElements[0];
        $this->assertEquals('better', $requestedAuthnConextElement->getAttribute("Comparison"));

        $authnContextClassRefElements = SAML2_Utils::xpQuery(
            $requestedAuthnConextElement,
            './saml_assertion:AuthnContextClassRef'
        );
        $this->assertCount(2, $authnContextClassRefElements);
        $this->assertEquals('accr1', $authnContextClassRefElements[0]->textContent);
        $this->assertEquals('accr2', $authnContextClassRefElements[1]->textContent);
    }

    public function testMarshallingOfSimpleRequest()
    {
        $xml = <<<AUTHNREQUEST
<samlp:AuthnRequest
  xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
  xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
  ID="_306f8ec5b618f361c70b6ffb1480eade"
  Version="2.0"
  IssueInstant="2004-12-05T09:21:59Z"
  Destination="https://idp.example.org/SAML2/SSO/Artifact"
  ProtocolBinding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact"
  AssertionConsumerServiceURL="https://sp.example.com/SAML2/SSO/Artifact">
    <saml:Issuer>https://sp.example.com/SAML2</saml:Issuer>
</samlp:AuthnRequest>
AUTHNREQUEST;

        $authnRequest = new SAML2_AuthnRequest(SAML2_DOMDocumentFactory::fromString($xml)->documentElement);

        $expectedIssueInstant = SAML2_Utils::xsDateTimeToTimestamp('2004-12-05T09:21:59Z');
        $this->assertEquals($expectedIssueInstant, $authnRequest->getIssueInstant());
        $this->assertEquals('https://idp.example.org/SAML2/SSO/Artifact', $authnRequest->getDestination());
        $this->assertEquals('urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact', $authnRequest->getProtocolBinding());
        $this->assertEquals(
            'https://sp.example.com/SAML2/SSO/Artifact',
            $authnRequest->getAssertionConsumerServiceURL()
        );
        $this->assertEquals('https://sp.example.com/SAML2', $authnRequest->getIssuer());
    }

    /**
     * Test unmarshalling / marshalling of XML with Extensions element
     */
    public function testExtensionOrdering()
    {
        $xml = <<<AUTHNREQUEST
<samlp:AuthnRequest
  xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
  xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
  ID="_306f8ec5b618f361c70b6ffb1480eade"
  Version="2.0"
  IssueInstant="2004-12-05T09:21:59Z"
  Destination="https://idp.example.org/SAML2/SSO/Artifact"
  ProtocolBinding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact"
  AssertionConsumerServiceURL="https://sp.example.com/SAML2/SSO/Artifact">
  <saml:Issuer>https://sp.example.com/SAML2</saml:Issuer>
  <samlp:Extensions>
      <myns:AttributeList xmlns:myns="urn:mynamespace">
          <myns:Attribute name="UserName" value=""/>
      </myns:AttributeList>
  </samlp:Extensions>
  <saml:Subject>
        <saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified">user@example.org</saml:NameID>
  </saml:Subject>
  <samlp:NameIDPolicy
    AllowCreate="true"
    Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress"/>
</samlp:AuthnRequest>
AUTHNREQUEST;

        $document     = SAML2_DOMDocumentFactory::fromString($xml);
        $authnRequest = new SAML2_AuthnRequest($document->documentElement);

        $this->assertXmlStringEqualsXmlString($document->C14N(), $authnRequest->toUnsignedXML()->C14N());
    }

    public function testThatTheSubjectIsCorrectlyRead()
    {
        $xml = <<<AUTHNREQUEST
<samlp:AuthnRequest
    xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
    AssertionConsumerServiceIndex="1"
    Destination="https://tiqr.stepup.org/idp/profile/saml2/Redirect/SSO"
    ID="_2b0226190ca1c22de6f66e85f5c95158"
    IssueInstant="2014-09-22T13:42:00Z"
    Version="2.0">
  <saml:Issuer>https://gateway.stepup.org/saml20/sp/metadata</saml:Issuer>
  <saml:Subject>
        <saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified">user@example.org</saml:NameID>
  </saml:Subject>
</samlp:AuthnRequest>
AUTHNREQUEST;

        $authnRequest = new SAML2_AuthnRequest(SAML2_DOMDocumentFactory::fromString($xml)->documentElement);

        $expectedNameId = array(
            'Value'  => "user@example.org",
            'Format' => "urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified"
        );
        $this->assertEquals($expectedNameId, $authnRequest->getNameId());
    }

    public function testThatTheSubjectCanBeSetBySettingTheNameId()
    {
        $request = new SAML2_AuthnRequest();
        $request->setNameId(array('Value' => 'user@example.org', 'Format' => SAML2_Const::NAMEID_UNSPECIFIED));

        $requestAsXML = $request->toUnsignedXML()->ownerDocument->saveXML();
        $expected = '<saml:Subject><saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified">user@example.org</saml:NameID></saml:Subject>';
        $this->assertContains($expected, $requestAsXML);
    }

    public function testThatAnEncryptedNameIdCanBeDecrypted()
    {
        $xml = <<<AUTHNREQUEST
<samlp:AuthnRequest
    xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
    ID="123"
    Version="2.0"
    IssueInstant="2015-05-11T09:02:36Z"
    Destination="https://tiqr.stepup.org/idp/profile/saml2/Redirect/SSO">
    <saml:Issuer>https://gateway.stepup.org/saml20/sp/metadata</saml:Issuer>
    <saml:Subject>
        <saml:EncryptedID xmlns:xenc="http://www.w3.org/2001/04/xmlenc#" xmlns:dsig="http://www.w3.org/2000/09/xmldsig#">
            <xenc:EncryptedData xmlns:xenc="http://www.w3.org/2001/04/xmlenc#" xmlns:dsig="http://www.w3.org/2000/09/xmldsig#" Type="http://www.w3.org/2001/04/xmlenc#Element">
                <xenc:EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes128-cbc"/>
                <dsig:KeyInfo xmlns:dsig="http://www.w3.org/2000/09/xmldsig#">
                    <xenc:EncryptedKey>
                        <xenc:EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#rsa-1_5"/>
                        <xenc:CipherData>
                            <xenc:CipherValue>Kzb231F/6iLrDG9KP99h1C08eV2WfRqasU0c3y9AG+nb0JFdQgqip5+5FN+ypi1zPz4FIdoPufXdQDIRi4tm1UMyaiA5MBHjk2GOw5GDc6idnzFAoy4uWlofELeeT2ftcP4c6ETDsu++iANi5XUU1A+WPxxel2NMss6F6MjOuCg=</xenc:CipherValue>
                        </xenc:CipherData>
                    </xenc:EncryptedKey>
                </dsig:KeyInfo>
                <xenc:CipherData>
                    <xenc:CipherValue>EHj4x8ZwXvxIHFo4uenQcXZsUnS0VPyhevIMwE6YfejFwW0V3vUImCVKfdEtMJgNS/suukvc/HmF2wHptBqk3yjwbRfdFX2axO7UPqyThiGkVTkccOpIv7RzN8mkiDe9cjOztIQYd1DfKrjgh+FFL10o08W+HSZFgp4XQGOAruLj+JVyoDlx6FMyTIRgeLxlW4K2G1++Xmp8wyLyoMCccdDRzX3KT/Ph2RVIDpE/XLznpQd19sgwaEguUerqdHwo</xenc:CipherValue>
                </xenc:CipherData>
            </xenc:EncryptedData>
        </saml:EncryptedID>
    </saml:Subject>
</samlp:AuthnRequest>
AUTHNREQUEST;

        $authnRequest = new SAML2_AuthnRequest(SAML2_DOMDocumentFactory::fromString($xml)->documentElement);

        $key = SAML2_CertificatesMock::getPrivateKey();
        $authnRequest->decryptNameId($key);

        $expectedNameId = array('Value' => md5('Arthur Dent'), 'Format' => SAML2_Const::NAMEID_ENCRYPTED);

        $this->assertEquals($expectedNameId, $authnRequest->getNameId());
    }

    /**
     * Due to the fact that the symmetric key is generated each time, we cannot test whether or not the resulting XML
     * matches a specific XML, but we can test whether or not the resulting structure is actually correct, conveying
     * all information required to decrypt the NameId.
     */
    public function testThatAnEncryptedNameIdResultsInTheCorrectXmlStructure()
    {
        // the NameID we're going to encrypt
        $nameId = array('Value' => md5('Arthur Dent'), 'Format' => SAML2_Const::NAMEID_ENCRYPTED);

        // basic AuthnRequest
        $request = new SAML2_AuthnRequest();
        $request->setIssuer('https://gateway.stepup.org/saml20/sp/metadata');
        $request->setDestination('https://tiqr.stepup.org/idp/profile/saml2/Redirect/SSO');
        $request->setNameId($nameId);

        // encrypt the NameID
        $key = SAML2_CertificatesMock::getPublicKey();
        $request->encryptNameId($key);

        $expectedXml = <<<AUTHNREQUEST
<samlp:AuthnRequest
    xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
    ID=""
    Version=""
    IssueInstant=""
    Destination="">
    <saml:Issuer></saml:Issuer>
    <saml:Subject>
        <saml:EncryptedID xmlns:xenc="http://www.w3.org/2001/04/xmlenc#" xmlns:dsig="http://www.w3.org/2000/09/xmldsig#">
            <xenc:EncryptedData xmlns:xenc="http://www.w3.org/2001/04/xmlenc#" xmlns:dsig="http://www.w3.org/2000/09/xmldsig#" Type="http://www.w3.org/2001/04/xmlenc#Element">
                <xenc:EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes128-cbc"/>
                <dsig:KeyInfo xmlns:dsig="http://www.w3.org/2000/09/xmldsig#">
                    <xenc:EncryptedKey>
                        <xenc:EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#rsa-1_5"/>
                        <xenc:CipherData>
                            <xenc:CipherValue></xenc:CipherValue>
                        </xenc:CipherData>
                    </xenc:EncryptedKey>
                </dsig:KeyInfo>
                <xenc:CipherData>
                    <xenc:CipherValue></xenc:CipherValue>
                </xenc:CipherData>
            </xenc:EncryptedData>
        </saml:EncryptedID>
    </saml:Subject>
</samlp:AuthnRequest>
AUTHNREQUEST;

        $expectedStructure = SAML2_DOMDocumentFactory::fromString($expectedXml)->documentElement;
        $requestStructure = $request->toUnsignedXML();

        $this->assertEqualXMLStructure($expectedStructure, $requestStructure);
    }

    /**
     * Test for setting IDPEntry values via setIDPList.
     * Tests legacy support (single string), array of attributes, and skipping of unknown attributes.
     */
    public function testIDPlistAttributes()
    {
        // basic AuthnRequest
        $request = new SAML2_AuthnRequest();
        $request->setIssuer('https://gateway.example.org/saml20/sp/metadata');
        $request->setDestination('https://tiqr.example.org/idp/profile/saml2/Redirect/SSO');
        $request->setIDPList(array(
            'Legacy1',
            array('ProviderID' => 'http://example.org/AAP', 'Name' => 'N00T', 'Loc' => 'https://mies'),
            array('ProviderID' => 'urn:example:1', 'Name' => 'Voorbeeld', 'Something' => 'Else')
        ));

        $expectedStructureDocument = <<<AUTHNREQUEST
<samlp:AuthnRequest
    xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
    ID=""
    Version=""
    IssueInstant=""
    Destination="">
    <saml:Issuer></saml:Issuer>
    <samlp:Scoping><samlp:IDPList>
        <samlp:IDPEntry ProviderID="Legacy1"/>
        <samlp:IDPEntry ProviderID="http://example.org/AAP" Name="N00T" Loc="https://mies"/>
        <samlp:IDPEntry ProviderID="urn:example:1" Name="Voorbeeld"/>
    </samlp:IDPList></samlp:Scoping>
</samlp:AuthnRequest>
AUTHNREQUEST;

        $expectedStructure = SAML2_DOMDocumentFactory::fromString($expectedStructureDocument)->documentElement;
        $requestStructure = $request->toUnsignedXML();

        $this->assertEqualXMLStructure($expectedStructure, $requestStructure);
    }

    /**
     * Test setting a requesterID.
     */
    public function testRequesterIdIsAddedCorrectly()
    {
        // basic AuthnRequest
        $request = new SAML2_AuthnRequest();
        $request->setIssuer('https://gateway.example.org/saml20/sp/metadata');
        $request->setDestination('https://tiqr.example.org/idp/profile/saml2/Redirect/SSO');
        $request->setRequesterID(array(
            'https://engine.demo.openconext.org/authentication/sp/metadata',
            'https://shib.example.edu/SSO/Metadata',
        ));

        $expectedStructureDocument = <<<AUTHNREQUEST
<samlp:AuthnRequest
    xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
    ID=""
    Version=""
    IssueInstant=""
    Destination="">
    <saml:Issuer></saml:Issuer>
    <samlp:Scoping>
        <samlp:RequesterID>https://engine.demo.openconext.org/authentication/sp/metadata</samlp:RequesterID>
        <samlp:RequesterID>https://shib.example.edu/SSO/Metadata</samlp:RequesterID>
    </samlp:Scoping>
</samlp:AuthnRequest>
AUTHNREQUEST;

        $expectedStructure = SAML2_DOMDocumentFactory::fromString($expectedStructureDocument)->documentElement;
        $requestStructure = $request->toUnsignedXML();

        $this->assertEqualXMLStructure($expectedStructure, $requestStructure);
    }

    /**
     * Test setting a requesterID.
     */
    public function testRequesterIdIsReadCorrectly()
    {
        $requesterId = array(
            'https://engine.demo.openconext.org/authentication/sp/metadata',
            'https://shib.example.edu/SSO/Metadata',
        );

        $xmlRequest = <<<AUTHNREQUEST
<samlp:AuthnRequest
    xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
    ID="_1234567890abvdefghijkl"
    Version="2.0"
    IssueInstant="2015-05-11T09:02:36Z"
    Destination="https://some.sp.invalid/acs">
    <saml:Issuer>https://some.sp.invalid/metadata</saml:Issuer>
    <samlp:Scoping>
        <samlp:RequesterID>https://engine.demo.openconext.org/authentication/sp/metadata</samlp:RequesterID>
        <samlp:RequesterID>https://shib.example.edu/SSO/Metadata</samlp:RequesterID>
    </samlp:Scoping>
</samlp:AuthnRequest>
AUTHNREQUEST;

        $authnRequest = new SAML2_AuthnRequest(SAML2_DOMDocumentFactory::fromString($xmlRequest)->firstChild);

        $this->assertEquals($requesterId, $authnRequest->getRequesterID());
    }
}
