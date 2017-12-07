<?php

/**
 * Test that ensures that either the response or the assertion(s) or both must be signed.
 */
class SAML2_Response_SignatureValidationTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var SAML2_Configuration_IdentityProvider
     */
    private $identityProviderConfiguration;

    /**
     * @var SAML2_Configuration_ServiceProvider
     */
    private $serviceProviderConfiguration;

    /**
     * @var Mockery\MockInterface Mock of SAML2_Assertion_ProcessorBuilder
     */
    private $assertionProcessorBuilder;

    /**
     * @var Mockery\MockInterface Mock of SAML2_Assertion_Processor
     */
    private $assertionProcessor;

    /**
     * @var string
     */
    private $currentDestination = 'http://moodle.bridge.feide.no/simplesaml/saml2/sp/AssertionConsumerService.php';

    /**
     * We mock the actual assertion processing as that is not what we want to test here. Since the assertion processor
     * is created via a static ::build() method we have to mock that, and have to run the tests in separate processes
     */
    public function setUp()
    {
        $this->assertionProcessorBuilder = Mockery::mock('alias:SAML2_Assertion_ProcessorBuilder');
        $this->assertionProcessor = Mockery::mock('SAML2_Assertion_Processor');
        $this->assertionProcessorBuilder
            ->shouldReceive('build')
            ->once()
            ->andReturn($this->assertionProcessor);

        $pattern = SAML2_Utilities_Certificate::CERTIFICATE_PATTERN;
        preg_match($pattern, SAML2_CertificatesMock::PUBLIC_KEY_PEM, $matches);

        $this->identityProviderConfiguration
            = new SAML2_Configuration_IdentityProvider(array('certificateData' => $matches[1]));
        $this->serviceProviderConfiguration
            = new SAML2_Configuration_ServiceProvider(array('entityId' => 'urn:mace:feide.no:services:no.feide.moodle'));
    }

    /**
     * This ensures that the mockery expectations are tested. This cannot be done through the registered listener (See
     * the phpunit.xml in the /tools/phpunit directory) as the tests run in isolation.
     */
    public function tearDown()
    {
        Mockery::close();
    }

    /**
     * @runInSeparateProcess
     */
    public function testThatAnUnsignedResponseWithASignedAssertionCanBeProcessed()
    {
        $this->assertionProcessor->shouldReceive('processAssertions')->once();

        $processor = new SAML2_Response_Processor(new \Psr\Log\NullLogger());

        $processor->process(
            $this->serviceProviderConfiguration,
            $this->identityProviderConfiguration,
            new SAML2_Configuration_Destination($this->currentDestination),
            $this->getUnsignedResponseWithSignedAssertion()
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testThatAnSignedResponseWithAnUnsignedAssertionCanBeProcessed()
    {
        $this->assertionProcessor->shouldReceive('processAssertions')->once();

        $processor = new SAML2_Response_Processor(new \Psr\Log\NullLogger());

        $processor->process(
            $this->serviceProviderConfiguration,
            $this->identityProviderConfiguration,
            new SAML2_Configuration_Destination($this->currentDestination),
            $this->getSignedResponseWithUnsignedAssertion()
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testThatASignedResponseWithASignedAssertionIsValid()
    {
        $this->assertionProcessor->shouldReceive('processAssertions')->once();

        $processor = new SAML2_Response_Processor(new \Psr\Log\NullLogger());

        $processor->process(
            $this->serviceProviderConfiguration,
            $this->identityProviderConfiguration,
            new SAML2_Configuration_Destination($this->currentDestination),
            $this->getSignedResponseWithSignedAssertion()
        );
    }

    /**
     * @expectedException SAML2_Response_Exception_UnsignedResponseException
     * @runInSeparateProcess
     */
    public function testThatAnUnsignedResponseWithNoSignedAssertionsThrowsAnException()
    {
        // here the processAssertions may not be called as it should fail with an exception due to having no signature
        $this->assertionProcessor->shouldReceive('processAssertions')->never();

        $processor = new SAML2_Response_Processor(new \Psr\Log\NullLogger());

        $processor->process(
            new SAML2_Configuration_ServiceProvider(array()),
            new SAML2_Configuration_IdentityProvider(array()),
            new SAML2_Configuration_Destination($this->currentDestination),
            $this->getUnsignedResponseWithUnsignedAssertion()
        );
    }

    /**
     * @return SAML2_Response
     */
    private function getSignedResponseWithUnsignedAssertion()
    {
        $doc = new DOMDocument();
        $doc->load(__DIR__ . '/response.xml');
        $response = new SAML2_Response($doc->firstChild);
        $response->setSignatureKey(SAML2_CertificatesMock::getPrivateKey());
        $response->setCertificates(array(SAML2_CertificatesMock::PUBLIC_KEY_PEM));

        // convert to signed response
        return new SAML2_Response($response->toSignedXML());
    }

    /**
     * @return SAML2_Response
     */
    private function getUnsignedResponseWithSignedAssertion()
    {
        $doc = new DOMDocument();
        $doc->load(__DIR__ . '/response.xml');
        $response = new SAML2_Response($doc->firstChild);

        $assertions = $response->getAssertions();
        $assertion = $assertions[0];
        $assertion->setSignatureKey(SAML2_CertificatesMock::getPrivateKey());
        $assertion->setCertificates(array(SAML2_CertificatesMock::PUBLIC_KEY_PEM));
        $signedAssertion = new SAML2_Assertion($assertion->toXML());

        $response->setAssertions(array($signedAssertion));

        return $response;
    }

    /**
     * @return SAML2_Response
     */
    private function getSignedResponseWithSignedAssertion()
    {
        $doc = new DOMDocument();
        $doc->load(__DIR__ . '/response.xml');
        $response = new SAML2_Response($doc->firstChild);
        $response->setSignatureKey(SAML2_CertificatesMock::getPrivateKey());
        $response->setCertificates(array(SAML2_CertificatesMock::PUBLIC_KEY_PEM));

        $assertions = $response->getAssertions();
        $assertion  = $assertions[0];
        $assertion->setSignatureKey(SAML2_CertificatesMock::getPrivateKey());
        $assertion->setCertificates(array(SAML2_CertificatesMock::PUBLIC_KEY_PEM));

        return new SAML2_Response($response->toSignedXML());
    }

    /**
     * @return SAML2_Response
     */
    private function getUnsignedResponseWithUnsignedAssertion()
    {
        $doc = new DOMDocument();
        $doc->load(__DIR__ . '/response.xml');
        return new SAML2_Response($doc->firstChild);
    }
}
