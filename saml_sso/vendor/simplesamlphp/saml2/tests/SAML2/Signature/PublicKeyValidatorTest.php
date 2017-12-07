<?php

class SAML2_Signature_PublicKeyValidatorTest extends \PHPUnit_Framework_TestCase
{
    private $mockSignedElement;
    private $mockConfiguration;

    public function setUp()
    {
        $this->mockConfiguration = \Mockery::mock('SAML2_Configuration_CertificateProvider');
        $this->mockSignedElement = \Mockery::mock('SAML2_SignedElement');
    }

    /**
     * @test
     * @group signature
     */
    public function it_cannot_validate_if_no_keys_can_be_loaded()
    {
        $keyloaderMock = $this->prepareKeyLoader(new SAML2_Certificate_KeyCollection());
        $validator = new SAML2_Signature_PublicKeyValidator(new \Psr\Log\NullLogger(), $keyloaderMock);

        $this->assertFalse($validator->canValidate($this->mockSignedElement, $this->mockConfiguration));
    }

    /**
     * @test
     * @group signature
     */
    public function it_will_validate_when_keys_can_be_loaded()
    {
        $keyloaderMock = $this->prepareKeyLoader(new SAML2_Certificate_KeyCollection(array(1, 2)));
        $validator = new SAML2_Signature_PublicKeyValidator(new \Psr\Log\NullLogger(), $keyloaderMock);

        $this->assertTrue($validator->canValidate($this->mockSignedElement, $this->mockConfiguration));
    }

    /**
     * @test
     * @group signature
     */
    public function non_X509_keys_are_not_used_for_validation()
    {
        $controlledCollection = new SAML2_Certificate_KeyCollection(array(
            new SAML2_Certificate_Key(array('type' => 'not_X509')),
            new SAML2_Certificate_Key(array('type' => 'again_not_X509'))
        ));

        $keyloaderMock = $this->prepareKeyLoader($controlledCollection);
        $logger = new SAML2_SimpleTestLogger();
        $validator = new SAML2_Signature_PublicKeyValidator($logger, $keyloaderMock);

        $this->assertTrue($validator->canValidate($this->mockSignedElement, $this->mockConfiguration));
        $this->assertFalse($validator->hasValidSignature($this->mockSignedElement, $this->mockConfiguration));
        $this->assertTrue($logger->hasMessage('Skipping unknown key type: "not_X509"'));
        $this->assertTrue($logger->hasMessage('Skipping unknown key type: "again_not_X509"'));
        $this->assertTrue($logger->hasMessage('No configured X509 certificate found to verify the signature with'));
    }

    /**
     * @test
     * @group signature
     */
    public function signed_message_with_valid_signature_is_validated_correctly()
    {
        $pattern = SAML2_Utilities_Certificate::CERTIFICATE_PATTERN;
        preg_match($pattern, SAML2_CertificatesMock::PUBLIC_KEY_PEM, $matches);

        $config = new SAML2_Configuration_IdentityProvider(array('certificateData' => $matches[1]));
        $validator = new SAML2_Signature_PublicKeyValidator(new SAML2_SimpleTestLogger(), new SAML2_Certificate_KeyLoader());

        $doc = SAML2_DOMDocumentFactory::fromFile(__DIR__ . '/response.xml');
        $response = new SAML2_Response($doc->firstChild);
        $response->setSignatureKey(SAML2_CertificatesMock::getPrivateKey());
        $response->setCertificates(array(SAML2_CertificatesMock::PUBLIC_KEY_PEM));

        // convert to signed response
        $response = new SAML2_Response($response->toSignedXML());

        $this->assertTrue($validator->canValidate($response, $config), 'Cannot validate the element');
        $this->assertTrue($validator->hasValidSignature($response, $config), 'The signature is not valid');
    }

    private function prepareKeyLoader($returnValue)
    {
        return \Mockery::mock('SAML2_Certificate_KeyLoader')
            ->shouldReceive('extractPublicKeys')
            ->andReturn($returnValue)
            ->getMock();
    }
}
