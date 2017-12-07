<?php

require_once 'CertificatesMock.php';

/**
 * Class LogoutRequestTest
 */
class LogoutRequestTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var DOMElement
     */
    private $logoutRequestElement;

    /**
     * Load a fixture.
     */
    public function setUp()
    {
        $xml = <<<XML
<samlp:LogoutRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="SomeIDValue" Version="2.0" IssueInstant="2010-07-22T11:30:19Z">
  <saml:Issuer>TheIssuer</saml:Issuer>
  <saml:EncryptedID>
    <xenc:EncryptedData xmlns:xenc="http://www.w3.org/2001/04/xmlenc#" xmlns:dsig="http://www.w3.org/2000/09/xmldsig#" Type="http://www.w3.org/2001/04/xmlenc#Element">
      <xenc:EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes128-cbc"/>
      <dsig:KeyInfo xmlns:dsig="http://www.w3.org/2000/09/xmldsig#">
        <xenc:EncryptedKey>
          <xenc:EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#rsa-oaep-mgf1p"/>
          <xenc:CipherData>
            <xenc:CipherValue>j7t37UjyQ9zgu+zcCDH8v0IaXP2aRSm/XuAW5p5dzeFKf9PZnh7n8977cmex6SCl9SQrJOlqw/GRa342MKFVEl2VmEY9Q+br0ypAZueLwe/z1x3NWzN1ZKwNteWrM7jMdoesjV55PWIWmnuBoDBebuKB7+zS83WN2plV/geSLDg=</xenc:CipherValue>
          </xenc:CipherData>
        </xenc:EncryptedKey>
      </dsig:KeyInfo>
      <xenc:CipherData>
        <xenc:CipherValue>rwUZFd0oNzJnvqliCntg8IBx1rulZD4Dopz1LNzx2GbqMln4vxtHi+tzmM9iZ/70zO3n83YXk61JwRzEwvmu7OEZERkjL3cQAEDEws/s4Ibc16pR0irorZy1FYqi9DR1dzDLI2Hbfdrg5oHviyPXtw==</xenc:CipherValue>
      </xenc:CipherData>
    </xenc:EncryptedData>
  </saml:EncryptedID>
  <samlp:SessionIndex>SomeSessionIndex1</samlp:SessionIndex>
  <samlp:SessionIndex>SomeSessionIndex2</samlp:SessionIndex>
</samlp:LogoutRequest>
XML;
        $document = SAML2_DOMDocumentFactory::fromString($xml);
        $this->logoutRequestElement = $document->firstChild;
    }

    public function testMarshalling()
    {
        $logoutRequest = new SAML2_LogoutRequest();
        $logoutRequest->setNameID(array('Value' => 'NameIDValue'));
        $logoutRequest->setSessionIndex('SessionIndexValue');

        $logoutRequestElement = $logoutRequest->toUnsignedXML();
        $this->assertEquals('LogoutRequest', $logoutRequestElement->localName);
        $this->assertEquals(SAML2_Const::NS_SAMLP, $logoutRequestElement->namespaceURI);

        $nameIdElements = SAML2_Utils::xpQuery($logoutRequestElement, './saml_assertion:NameID');
        $this->assertCount(1, $nameIdElements);
        $nameIdElements = $nameIdElements[0];
        $this->assertEquals('NameIDValue', $nameIdElements->textContent);

        $sessionIndexElements = SAML2_Utils::xpQuery($logoutRequestElement, './saml_protocol:SessionIndex');
        $this->assertCount(1, $sessionIndexElements);
        $this->assertEquals('SessionIndexValue', $sessionIndexElements[0]->textContent);

        $logoutRequest = new SAML2_LogoutRequest();
        $logoutRequest->setNameID(array('Value' => 'NameIDValue'));
        $logoutRequest->setSessionIndexes(array('SessionIndexValue1', 'SessionIndexValue2'));
        $logoutRequestElement = $logoutRequest->toUnsignedXML();

        $sessionIndexElements = SAML2_Utils::xpQuery($logoutRequestElement, './saml_protocol:SessionIndex');
        $this->assertCount(2, $sessionIndexElements);
        $this->assertEquals('SessionIndexValue1', $sessionIndexElements[0]->textContent);
        $this->assertEquals('SessionIndexValue2', $sessionIndexElements[1]->textContent);
    }

    public function testUnmarshalling()
    {
        $logoutRequest = new SAML2_LogoutRequest($this->logoutRequestElement);
        $this->assertEquals('TheIssuer', $logoutRequest->getIssuer());
        $this->assertTrue($logoutRequest->isNameIdEncrypted());

        $sessionIndexElements = $logoutRequest->getSessionIndexes();
        $this->assertCount(2, $sessionIndexElements);
        $this->assertEquals('SomeSessionIndex1', $sessionIndexElements[0]);
        $this->assertEquals('SomeSessionIndex2', $sessionIndexElements[1]);

        $logoutRequest->decryptNameId(SAML2_CertificatesMock::getPrivateKey());

        $nameId = $logoutRequest->getNameId();
        $this->assertEquals('TheNameIDValue', $nameId['Value']);
    }

    public function testEncryptedNameId()
    {
        $logoutRequest = new SAML2_LogoutRequest();
        $logoutRequest->setNameID(array('Value' => 'NameIDValue'));
        $logoutRequest->encryptNameId(SAML2_CertificatesMock::getPublicKey());

        $logoutRequestElement = $logoutRequest->toUnsignedXML();
        $this->assertCount(
            1,
            SAML2_Utils::xpQuery($logoutRequestElement, './saml_assertion:EncryptedID/xenc:EncryptedData')
        );
    }

    public function testDecryptingNameId()
    {
        $logoutRequest = new SAML2_LogoutRequest($this->logoutRequestElement);
        $this->assertTrue($logoutRequest->isNameIdEncrypted());

        $logoutRequest->decryptNameId(SAML2_CertificatesMock::getPrivateKey());
        $nameId = $logoutRequest->getNameId();
        $this->assertEquals('TheNameIDValue', $nameId['Value']);
    }
}
