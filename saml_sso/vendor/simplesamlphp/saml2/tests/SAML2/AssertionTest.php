<?php

/**
 * Class SAML2_AssertionTest
 */
class SAML2_AssertionTest extends \PHPUnit_Framework_TestCase
{
    public function testMarshalling()
    {
        // Create an assertion
        $assertion = new \SAML2_Assertion();
        $assertion->setIssuer('testIssuer');
        $assertion->setValidAudiences(array('audience1', 'audience2'));
        $assertion->setAuthnContext('someAuthnContext');

        // Marshall it to a DOMElement
        $assertionElement = $assertion->toXML();

        // Test for an Issuer
        $issuerElements = \SAML2_Utils::xpQuery($assertionElement, './saml_assertion:Issuer');
        $this->assertCount(1, $issuerElements);
        $this->assertEquals('testIssuer', $issuerElements[0]->textContent);

        // Test for an AudienceRestriction
        $audienceElements = \SAML2_Utils::xpQuery(
            $assertionElement,
            './saml_assertion:Conditions/saml_assertion:AudienceRestriction/saml_assertion:Audience'
        );
        $this->assertCount(2, $audienceElements);
        $this->assertEquals('audience1', $audienceElements[0]->textContent);
        $this->assertEquals('audience2', $audienceElements[1]->textContent);

        // Test for an Authentication Context
        $authnContextElements = \SAML2_Utils::xpQuery(
            $assertionElement,
            './saml_assertion:AuthnStatement/saml_assertion:AuthnContext/saml_assertion:AuthnContextClassRef'
        );
        $this->assertCount(1, $authnContextElements);
        $this->assertEquals('someAuthnContext', $authnContextElements[0]->textContent);

    }

    public function testUnmarshalling()
    {
        // Unmarshall an assertion
        $xml = <<<XML
<saml:Assertion xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                ID="_593e33ddf86449ce4d4c22b60ac48e067d98a0b2bf"
                Version="2.0"
                IssueInstant="2010-03-05T13:34:28Z"
>
  <saml:Issuer>testIssuer</saml:Issuer>
  <saml:Conditions>
    <saml:AudienceRestriction>
      <saml:Audience>audience1</saml:Audience>
      <saml:Audience>audience2</saml:Audience>
    </saml:AudienceRestriction>
  </saml:Conditions>
  <saml:AuthnStatement AuthnInstant="2010-03-05T13:34:28Z">
    <saml:AuthnContext>
      <saml:AuthnContextClassRef>someAuthnContext</saml:AuthnContextClassRef>
      <saml:AuthenticatingAuthority>someIdP1</saml:AuthenticatingAuthority>
      <saml:AuthenticatingAuthority>someIdP2</saml:AuthenticatingAuthority>
    </saml:AuthnContext>
  </saml:AuthnStatement>
</saml:Assertion>
XML;
        $document  = SAML2_DOMDocumentFactory::fromString($xml);
        $assertion = new \SAML2_Assertion($document->firstChild);

        // Test for valid audiences
        $assertionValidAudiences = $assertion->getValidAudiences();
        $this->assertCount(2, $assertionValidAudiences);
        $this->assertEquals('audience1', $assertionValidAudiences[0]);
        $this->assertEquals('audience2', $assertionValidAudiences[1]);

        // Test for Authenticating Authorities
        $assertionAuthenticatingAuthorities = $assertion->getAuthenticatingAuthority();
        $this->assertCount(2, $assertionAuthenticatingAuthorities);
        $this->assertEquals('someIdP1', $assertionAuthenticatingAuthorities[0]);
        $this->assertEquals('someIdP2', $assertionAuthenticatingAuthorities[1]);
    }

    public function testAuthnContextDeclAndClassRef()
    {
        $xml = <<<XML
<saml:Assertion xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
                ID="_593e33ddf86449ce4d4c22b60ac48e067d98a0b2bf"
                Version="2.0"
                 IssueInstant="2010-03-05T13:34:28Z"
>
  <saml:Issuer>testIssuer</saml:Issuer>
  <saml:AuthnStatement AuthnInstant="2010-03-05T13:34:28Z">
    <saml:AuthnContext>
      <saml:AuthnContextClassRef>someAuthnContext</saml:AuthnContextClassRef>
      <saml:AuthnContextDecl>
        <samlac:AuthenticationContextDeclaration xmlns:samlac="urn:oasis:names:tc:SAML:2.0:ac">
        </samlac:AuthenticationContextDeclaration>
      </saml:AuthnContextDecl>
    </saml:AuthnContext>
  </saml:AuthnStatement>
</saml:Assertion>
XML;

        // Try with unmarshalling
        $document = SAML2_DOMDocumentFactory::fromString($xml);

        $assertion = new \SAML2_Assertion($document->documentElement);
        $authnContextDecl = $assertion->getAuthnContextDecl();
        $this->assertNotEmpty($authnContextDecl);
        $this->assertEquals('AuthnContextDecl', $authnContextDecl->localName);
        $childLocalName = $authnContextDecl->getXML()->childNodes->item(1)->localName;
        $this->assertEquals('AuthenticationContextDeclaration', $childLocalName);

        $this->assertEquals('someAuthnContext', $assertion->getAuthnContextClassRef());
    }

    public function testAuthnContextDeclRefAndClassRef()
    {
        // Try with unmarshalling
        $xml = <<<XML
<saml:Assertion xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
                ID="_593e33ddf86449ce4d4c22b60ac48e067d98a0b2bf"
                Version="2.0"
                 IssueInstant="2010-03-05T13:34:28Z"
>
  <saml:Issuer>testIssuer</saml:Issuer>
  <saml:AuthnStatement AuthnInstant="2010-03-05T13:34:28Z">
    <saml:AuthnContext>
      <saml:AuthnContextClassRef>someAuthnContext</saml:AuthnContextClassRef>
      <saml:AuthnContextDeclRef>/relative/path/to/document.xml</saml:AuthnContextDeclRef>
    </saml:AuthnContext>
  </saml:AuthnStatement>
</saml:Assertion>
XML;

        $document = SAML2_DOMDocumentFactory::fromString($xml);

        $assertion = new \SAML2_Assertion($document->documentElement);
        $this->assertEquals('/relative/path/to/document.xml', $assertion->getAuthnContextDeclRef());
        $this->assertEquals('someAuthnContext', $assertion->getAuthnContextClassRef());
    }

    public function testAuthnContextDeclAndRefConstraint()
    {
        $xml = <<<XML
<samlac:AuthenticationContextDeclaration xmlns:samlac="urn:oasis:names:tc:SAML:2.0:ac">
</samlac:AuthenticationContextDeclaration>
XML;

        $document  = SAML2_DOMDocumentFactory::fromString($xml);
        $assertion = new \SAML2_Assertion();

        $e = null;
        try {
            $assertion->setAuthnContextDecl(new SAML2_XML_Chunk($document->documentElement));
            $assertion->setAuthnContextDeclRef('/relative/path/to/document.xml');
        }
        catch (Exception $e) {}
        $this->assertNotEmpty($e);

        // Try again in reverse order for good measure.
        $assertion = new \SAML2_Assertion();

        $e = null;
        try {
            $assertion->setAuthnContextDeclRef('/relative/path/to/document.xml');
            $assertion->setAuthnContextDecl(new SAML2_XML_Chunk($document->documentElement));
        }
        catch (Exception $e) {}
        $this->assertNotEmpty($e);

        // Try with unmarshalling
        $xml = <<<XML
<saml:Assertion xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
                ID="_593e33ddf86449ce4d4c22b60ac48e067d98a0b2bf"
                Version="2.0"
                 IssueInstant="2010-03-05T13:34:28Z"
>
  <saml:Issuer>testIssuer</saml:Issuer>
  <saml:AuthnStatement AuthnInstant="2010-03-05T13:34:28Z">
    <saml:AuthnContext>
      <saml:AuthnContextDecl>
        <samlac:AuthenticationContextDeclaration xmlns:samlac="urn:oasis:names:tc:SAML:2.0:ac">
        </samlac:AuthenticationContextDeclaration>
      </saml:AuthnContextDecl>
      <saml:AuthnContextDeclRef>/relative/path/to/document.xml</saml:AuthnContextDeclRef>
    </saml:AuthnContext>
  </saml:AuthnStatement>
</saml:Assertion>
XML;

        $document = SAML2_DOMDocumentFactory::fromString($xml);

        $e = null;
        try {
            new \SAML2_Assertion($document->documentElement);
        }
        catch (Exception $e) {}
        $this->assertNotEmpty($e);
    }

    public function testMustHaveClassRefOrDeclOrDeclRef()
    {
        // Unmarshall an assertion
        $document = SAML2_DOMDocumentFactory::fromString(<<<XML
<saml:Assertion xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                ID="_593e33ddf86449ce4d4c22b60ac48e067d98a0b2bf"
                Version="2.0"
                IssueInstant="2010-03-05T13:34:28Z"
>
  <saml:Issuer>testIssuer</saml:Issuer>
  <saml:AuthnStatement AuthnInstant="2010-03-05T13:34:28Z">
    <saml:AuthnContext>
      <saml:AuthenticatingAuthority>someIdP1</saml:AuthenticatingAuthority>
      <saml:AuthenticatingAuthority>someIdP2</saml:AuthenticatingAuthority>
    </saml:AuthnContext>
  </saml:AuthnStatement>
</saml:Assertion>
XML
        );
        $e = null;
        try {
            $assertion = new \SAML2_Assertion($document->firstChild);
        }
        catch (Exception $e) {
        }
        $this->assertNotEmpty($e);
    }

    /**
     * Tests that AuthnContextDeclRef is not mistaken for AuthnContextClassRef.
     *
     * This tests against reintroduction of removed behavior.
     */
    public function testNoAuthnContextDeclRefFallback()
    {
        $authnContextDeclRef = 'relative/url/to/authcontext.xml';

        // Unmarshall an assertion
        $document = SAML2_DOMDocumentFactory::fromString(<<<XML
<saml:Assertion xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
                ID="_593e33ddf86449ce4d4c22b60ac48e067d98a0b2bf"
                Version="2.0"
                 IssueInstant="2010-03-05T13:34:28Z"
>
  <saml:Issuer>testIssuer</saml:Issuer>
  <saml:AuthnStatement AuthnInstant="2010-03-05T13:34:28Z">
    <saml:AuthnContext>
      <saml:AuthnContextDeclRef>$authnContextDeclRef</saml:AuthnContextDeclRef>
    </saml:AuthnContext>
  </saml:AuthnStatement>
</saml:Assertion>
XML
        );
        $assertion = new \SAML2_Assertion($document->firstChild);
        $this->assertEmpty($assertion->getAuthnContextClassRef());
        $this->assertEquals($authnContextDeclRef, $assertion->getAuthnContextDeclRef());
    }

    public function testHasEncryptedAttributes()
    {
        $document = new DOMDocument();
        $document->loadXML(<<<XML
    <saml:Assertion xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                    Version="2.0"
                    ID="_93af655219464fb403b34436cfb0c5cb1d9a5502"
                    IssueInstant="1970-01-01T01:33:31Z">
      <saml:Issuer>Provider</saml:Issuer>
      <saml:Subject>
        <saml:NameID Format="urn:oasis:names:tc:SAML:2.0:nameid-format:persistent">s00000000:123456789</saml:NameID>
        <saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer">
          <saml:SubjectConfirmationData NotOnOrAfter="2011-08-31T08:51:05Z" Recipient="https://sp.example.com/assertion_consumer" InResponseTo="_13603a6565a69297e9809175b052d115965121c8" />
        </saml:SubjectConfirmation>
      </saml:Subject>
      <saml:Conditions NotOnOrAfter="2011-08-31T08:51:05Z" NotBefore="2011-08-31T08:51:05Z">
        <saml:AudienceRestriction>
          <saml:Audience>ServiceProvider</saml:Audience>
        </saml:AudienceRestriction>
      </saml:Conditions>
      <saml:AuthnStatement AuthnInstant="2011-08-31T08:51:05Z" SessionIndex="_93af655219464fb403b34436cfb0c5cb1d9a5502">
        <saml:AuthnContext>
          <saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport</saml:AuthnContextClassRef>
        </saml:AuthnContext>
        <saml:SubjectLocality Address="127.0.0.1"/>
      </saml:AuthnStatement>
      <saml:AttributeStatement>
        <saml:Attribute Name="urn:ServiceID">
          <saml:AttributeValue xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:type="xs:string">1</saml:AttributeValue>
        </saml:Attribute>
        <saml:Attribute Name="urn:EntityConcernedID">
          <saml:AttributeValue xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:type="xs:string">1</saml:AttributeValue>
        </saml:Attribute>
        <saml:Attribute Name="urn:EntityConcernedSubID">
          <saml:AttributeValue xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:type="xs:string">1</saml:AttributeValue>
        </saml:Attribute>
        <saml:EncryptedAttribute xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion">
          <xenc:EncryptedData xmlns:xenc="http://www.w3.org/2001/04/xmlenc#" Type="http://www.w3.org/2001/04/xmlenc#Element" Id="_F39625AF68B4FC078CC7582D28D05D9C">
            <xenc:EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes256-cbc"/>
            <ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
              <xenc:EncryptedKey>
                <xenc:EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#rsa-oaep-mgf1p"/>
                <ds:KeyInfo>
                  <ds:KeyName>62355fbd1f624503c5c9677402ecca00ef1f6277</ds:KeyName>
                </ds:KeyInfo>
                <xenc:CipherData>
                  <xenc:CipherValue>K0mBLxfLziKVUKEAOYe7D6uVSCPy8vyWVh3RecnPES+8QkAhOuRSuE/LQpFr0huI/iCEy9pde1QgjYDLtjHcujKi2xGqW6jkXW/EuKomqWPPA2xYs1fpB1su4aXUOQB6OJ70/oDcOsy834ghFaBWilE8fqyDBUBvW+2IvaMUZabwN/s9mVkWzM3r30tlkhLK7iOrbGAldIHwFU5z7PPR6RO3Y3fIxjHU40OnLsJc3xIqdLH3fXpC0kgi5UspLdq14e5OoXjLoPG3BO3zwOAIJ8XNBWY5uQof6KrKbcvtZSY0fMvPYhYfNjtRFy8y49ovL9fwjCRTDlT5+aHqsCTBrw==</xenc:CipherValue>
                </xenc:CipherData>
              </xenc:EncryptedKey>
            </ds:KeyInfo>
            <xenc:CipherData>
              <xenc:CipherValue>ZzCu6axGgAYZHVf77NX8apZKB/GJDeuV6bFByBS0AIgiXkvDUAmLCpabTAWBM+yz19olA6rryuOfr82ev2bzPNURvm4SYxahvuL4Pibn5wJky0Bl54VqmcU+Aqj0dAvOgqG1y3X4wO9n9bRsTv6921m0eqRAFph8kK8L9hirK1BxYBYj2RyFCoFDPxVZ5wyra3q4qmE4/ELQpFP6mfU8LXb0uoWJUjGUelS2Aa7bZis8zEpwov4CwtlNjltQih4mv7ttCAfYqcQIFzBTB+DAa0+XggxCLcdB3+mQiRcECBfwHHJ7gRmnuBEgeWT3CGKa3Nb7GMXOfuxFKF5pIehWgo3kdNQLalor8RVW6I8P/I8fQ33Fe+NsHVnJ3zwSA//a</xenc:CipherValue>
            </xenc:CipherData>
          </xenc:EncryptedData>
        </saml:EncryptedAttribute>
      </saml:AttributeStatement>
    </saml:Assertion>
XML
        );
        $assertion = new \SAML2_Assertion($document->firstChild);
        $this->assertTrue($assertion->hasEncryptedAttributes());
    }

    /**
     * @group Assertion
     */
    public function testCorrectSignatureMethodCanBeExtracted()
    {
        $document = new \DOMDocument();
        $document->loadXML(<<<XML
    <saml:Assertion xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                    Version="2.0"
                    ID="_93af655219464fb403b34436cfb0c5cb1d9a5502"
                    IssueInstant="1970-01-01T01:33:31Z">
      <saml:Issuer>Provider</saml:Issuer>
      <saml:Subject>
        <saml:NameID Format="urn:oasis:names:tc:SAML:2.0:nameid-format:persistent">s00000000:123456789</saml:NameID>
        <saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer">
          <saml:SubjectConfirmationData NotOnOrAfter="2011-08-31T08:51:05Z" Recipient="https://sp.example.com/assertion_consumer" InResponseTo="_13603a6565a69297e9809175b052d115965121c8" />
        </saml:SubjectConfirmation>
      </saml:Subject>
      <saml:Conditions NotOnOrAfter="2011-08-31T08:51:05Z" NotBefore="2011-08-31T08:51:05Z">
        <saml:AudienceRestriction>
          <saml:Audience>ServiceProvider</saml:Audience>
        </saml:AudienceRestriction>
      </saml:Conditions>
      <saml:AuthnStatement AuthnInstant="2011-08-31T08:51:05Z" SessionIndex="_93af655219464fb403b34436cfb0c5cb1d9a5502">
        <saml:AuthnContext>
          <saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport</saml:AuthnContextClassRef>
        </saml:AuthnContext>
        <saml:SubjectLocality Address="127.0.0.1"/>
      </saml:AuthnStatement>
      <saml:AttributeStatement>
        <saml:Attribute Name="urn:ServiceID">
          <saml:AttributeValue xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:type="xs:string">1</saml:AttributeValue>
        </saml:Attribute>
        <saml:Attribute Name="urn:EntityConcernedID">
          <saml:AttributeValue xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:type="xs:string">1</saml:AttributeValue>
        </saml:Attribute>
        <saml:Attribute Name="urn:EntityConcernedSubID">
          <saml:AttributeValue xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:type="xs:string">1</saml:AttributeValue>
        </saml:Attribute>
      </saml:AttributeStatement>
    </saml:Assertion>
XML
        );

        $privateKey = SAML2_CertificatesMock::getPrivateKey();

        $unsignedAssertion = new SAML2_Assertion($document->firstChild);
        $unsignedAssertion->setSignatureKey($privateKey);
        $unsignedAssertion->setCertificates(array(SAML2_CertificatesMock::PUBLIC_KEY_PEM));

        $signedAssertion = new SAML2_Assertion($unsignedAssertion->toXML());

        $signatureMethod = $signedAssertion->getSignatureMethod();

        $this->assertEquals($privateKey->getAlgorith(), $signatureMethod);
    }

    public function testAttributeValuesWithComplexTypeValuesAreParsedCorrectly()
    {
        $xml = <<<XML
            <saml:Assertion
                    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                    xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
                    xmlns:xs="http://www.w3.org/2001/XMLSchema"
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    Version="2.0"
                    ID="_93af655219464fb403b34436cfb0c5cb1d9a5502"
                    IssueInstant="1970-01-01T01:33:31Z">
      <saml:Issuer>Provider</saml:Issuer>
      <saml:Conditions/>
      <saml:AttributeStatement>
        <saml:Attribute Name="urn:oid:1.3.6.1.4.1.5923.1.1.1.10" NameFormat="urn:oasis:names:tc:SAML:2.0:attrname-format:uri">
            <saml:AttributeValue>
                <saml:NameID Format="urn:oasis:names:tc:SAML:2.0:nameid-format:persistent">abcd-some-value-xyz</saml:NameID>
            </saml:AttributeValue>
        </saml:Attribute>
        <saml:Attribute Name="urn:mace:dir:attribute-def:eduPersonTargetedID" NameFormat="urn:oasis:names:tc:SAML:2.0:attrname-format:uri">
            <saml:AttributeValue>
                <saml:NameID Format="urn:oasis:names:tc:SAML:2.0:nameid-format:persistent">abcd-some-value-xyz</saml:NameID>
            </saml:AttributeValue>
        </saml:Attribute>
        <saml:Attribute Name="urn:EntityConcernedSubID" NameFormat="urn:oasis:names:tc:SAML:2.0:attrname-format:uri">
            <saml:AttributeValue xsi:type="xs:string">string</saml:AttributeValue>
        </saml:Attribute>
      </saml:AttributeStatement>
    </saml:Assertion>
XML;

        $assertion = new \SAML2_Assertion(SAML2_DOMDocumentFactory::fromString($xml)->firstChild);

        $attributes = $assertion->getAttributes();
        $this->assertInstanceOf(
            '\DOMNodeList',
            $attributes['urn:mace:dir:attribute-def:eduPersonTargetedID'][0]
        );
        $this->assertXmlStringEqualsXmlString($xml, $assertion->toXML()->ownerDocument->saveXML());
    }

    public function testTypedAttributeValuesAreParsedCorrectly()
    {
        $xml = <<<XML
            <saml:Assertion
                    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                    xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
                    xmlns:xs="http://www.w3.org/2001/XMLSchema"
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    Version="2.0"
                    ID="_93af655219464fb403b34436cfb0c5cb1d9a5502"
                    IssueInstant="1970-01-01T01:33:31Z">
      <saml:Issuer>Provider</saml:Issuer>
      <saml:Conditions/>
      <saml:AttributeStatement>
        <saml:Attribute Name="urn:some:string">
            <saml:AttributeValue xsi:type="xs:string">string</saml:AttributeValue>
        </saml:Attribute>
        <saml:Attribute Name="urn:some:integer">
            <saml:AttributeValue xsi:type="xs:integer">42</saml:AttributeValue>
        </saml:Attribute>
      </saml:AttributeStatement>
    </saml:Assertion>
XML;

        $assertion = new \SAML2_Assertion(SAML2_DOMDocumentFactory::fromString($xml)->firstChild);

        $attributes = $assertion->getAttributes();
        $this->assertInternalType('int', $attributes['urn:some:integer'][0]);
        $this->assertInternalType('string', $attributes['urn:some:string'][0]);
        $this->assertXmlStringEqualsXmlString($xml, $assertion->toXML()->ownerDocument->saveXML());
    }

    public function testEncryptedAttributeValuesWithComplexTypeValuesAreParsedCorrectly()
    {
        $xml = <<<XML
            <saml:Assertion
                    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                    xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
                    xmlns:xs="http://www.w3.org/2001/XMLSchema"
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    Version="2.0"
                    ID="_93af655219464fb403b34436cfb0c5cb1d9a5502"
                    IssueInstant="1970-01-01T01:33:31Z">
      <saml:Issuer>Provider</saml:Issuer>
      <saml:Conditions/>
      <saml:AttributeStatement>
        <saml:Attribute Name="urn:oid:1.3.6.1.4.1.5923.1.1.1.10" NameFormat="urn:oasis:names:tc:SAML:2.0:attrname-format:uri">
            <saml:AttributeValue>
                <saml:NameID Format="urn:oasis:names:tc:SAML:2.0:nameid-format:persistent">abcd-some-value-xyz</saml:NameID>
            </saml:AttributeValue>
        </saml:Attribute>
        <saml:Attribute Name="urn:mace:dir:attribute-def:eduPersonTargetedID" NameFormat="urn:oasis:names:tc:SAML:2.0:attrname-format:uri">
            <saml:AttributeValue>
                <saml:NameID Format="urn:oasis:names:tc:SAML:2.0:nameid-format:persistent">abcd-some-value-xyz</saml:NameID>
            </saml:AttributeValue>
        </saml:Attribute>
        <saml:Attribute Name="urn:EntityConcernedSubID" NameFormat="urn:oasis:names:tc:SAML:2.0:attrname-format:uri">
            <saml:AttributeValue xsi:type="xs:string">string</saml:AttributeValue>
        </saml:Attribute>
      </saml:AttributeStatement>
    </saml:Assertion>
XML;

        $privateKey = SAML2_CertificatesMock::getPublicKey();

        $assertion = new SAML2_Assertion(SAML2_DOMDocumentFactory::fromString($xml)->firstChild);
        $assertion->setEncryptionKey($privateKey);
        $assertion->setEncryptedAttributes(true);
        $encryptedAssertion = $assertion->toXML()->ownerDocument->saveXML();

        $assertionToVerify = new SAML2_Assertion(SAML2_DOMDocumentFactory::fromString($encryptedAssertion)->firstChild);

        $this->assertTrue($assertionToVerify->hasEncryptedAttributes());

        $assertionToVerify->decryptAttributes(SAML2_CertificatesMock::getPrivateKey());

        $attributes = $assertionToVerify->getAttributes();
        $this->assertInstanceOf(
            '\DOMNodeList',
            $attributes['urn:mace:dir:attribute-def:eduPersonTargetedID'][0]
        );
        $this->assertXmlStringEqualsXmlString($xml, $assertionToVerify->toXML()->ownerDocument->saveXML());
    }

    public function testTypedEncryptedAttributeValuesAreParsedCorrectly()
    {
        $xml = <<<XML
            <saml:Assertion
                    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                    xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
                    xmlns:xs="http://www.w3.org/2001/XMLSchema"
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    Version="2.0"
                    ID="_93af655219464fb403b34436cfb0c5cb1d9a5502"
                    IssueInstant="1970-01-01T01:33:31Z">
      <saml:Issuer>Provider</saml:Issuer>
      <saml:Conditions/>
      <saml:AttributeStatement>
        <saml:Attribute Name="urn:some:string">
            <saml:AttributeValue xsi:type="xs:string">string</saml:AttributeValue>
        </saml:Attribute>
        <saml:Attribute Name="urn:some:integer">
            <saml:AttributeValue xsi:type="xs:integer">42</saml:AttributeValue>
        </saml:Attribute>
      </saml:AttributeStatement>
    </saml:Assertion>
XML;

        $privateKey = SAML2_CertificatesMock::getPublicKey();

        $assertion = new SAML2_Assertion(SAML2_DOMDocumentFactory::fromString($xml)->firstChild);
        $assertion->setEncryptionKey($privateKey);
        $assertion->setEncryptedAttributes(true);
        $encryptedAssertion = $assertion->toXML()->ownerDocument->saveXML();

        $assertionToVerify = new SAML2_Assertion(SAML2_DOMDocumentFactory::fromString($encryptedAssertion)->firstChild);

        $this->assertTrue($assertionToVerify->hasEncryptedAttributes());

        $assertionToVerify->decryptAttributes(SAML2_CertificatesMock::getPrivateKey());
        $attributes = $assertionToVerify->getAttributes();

        $this->assertInternalType('int', $attributes['urn:some:integer'][0]);
        $this->assertInternalType('string', $attributes['urn:some:string'][0]);
        $this->assertXmlStringEqualsXmlString($xml, $assertionToVerify->toXML()->ownerDocument->saveXML());
    }
}

