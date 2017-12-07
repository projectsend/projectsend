<?php

class SAML2_Signature_FingerprintValidatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Mockery\MockInterface
     */
    private $mockSignedElement;

    /**
     * @var \Mockery\MockInterface
     */
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
    public function it_cannot_validate_when_no_fingerprint_is_configured()
    {
        $this->mockConfiguration->shouldReceive('getCertificateFingerprints')->once()->andReturn(null);

        $validator = new SAML2_Signature_FingerprintValidator(
            new SAML2_SimpleTestLogger(),
            new SAML2_Certificate_FingerprintLoader()
        );

        $this->assertFalse($validator->canValidate($this->mockSignedElement, $this->mockConfiguration));
    }

    /**
     * @test
     * @group signature
     */
    public function it_cannot_validate_when_no_certificates_are_found()
    {
        $this->mockConfiguration->shouldReceive('getCertificateFingerprints')->once()->andReturn(array());
        $this->mockSignedElement->shouldReceive('getCertificates')->once()->andReturn(array());

        $validator = new SAML2_Signature_FingerprintValidator(
            new SAML2_SimpleTestLogger(),
            new SAML2_Certificate_FingerprintLoader()
        );

        $this->assertFalse($validator->canValidate($this->mockSignedElement, $this->mockConfiguration));
    }

    /**
     * @test
     * @group signature
     */
    public function signed_message_with_valid_signature_is_validated_correctly()
    {
        $pattern = SAML2_Utilities_Certificate::CERTIFICATE_PATTERN;
        preg_match($pattern, SAML2_CertificatesMock::PUBLIC_KEY_PEM, $matches);
        $certdata = SAML2_Certificate_X509::createFromCertificateData($matches[1]);
        $fingerprint = $certdata->getFingerprint();
        $fingerprint_retry = $certdata->getFingerprint();
        $this->assertTrue($fingerprint->equals($fingerprint_retry), 'Cached fingerprint does not match original');

        $config    = new SAML2_Configuration_IdentityProvider(array('certificateFingerprints' => array($fingerprint->getRaw())));
        $validator = new SAML2_Signature_FingerprintValidator(
            new SAML2_SimpleTestLogger(),
            new SAML2_Certificate_FingerprintLoader()
        );

        $doc = SAML2_DOMDocumentFactory::fromFile(__DIR__ . '/response.xml');
        $response = new SAML2_Response($doc->firstChild);
        $response->setSignatureKey(SAML2_CertificatesMock::getPrivateKey());
        $response->setCertificates(array(SAML2_CertificatesMock::PUBLIC_KEY_PEM));

        // convert to signed response
        $response = new SAML2_Response($response->toSignedXML());

        $this->assertTrue($validator->canValidate($response, $config), 'Cannot validate the element');
        $this->assertTrue($validator->hasValidSignature($response, $config), 'The signature is not valid');
    }
}
