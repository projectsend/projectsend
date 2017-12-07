<?php

class SAML2_Certificate_FingerprintLoaderTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var SAML2_Certificate_FingerprintLoader
     */
    private $fingerprintLoader;

    /**
     * @var \Mockery\MockInterface
     */
    private $configurationMock;

    public function setUp()
    {
        $this->fingerprintLoader = new SAML2_Certificate_FingerprintLoader();
        $this->configurationMock = \Mockery::mock('SAML2_Configuration_CertificateProvider');
    }

    /**
     * @group certificate
     * @test
     *
     * @dataProvider invalidConfigurationProvider
     * @expectedException SAML2_Exception_InvalidArgumentException
     */
    public function it_does_not_accept_invalid_configuration_values($configurationValue)
    {
        $this->configurationMock
            ->shouldReceive('getCertificateFingerprints')
            ->once()
            ->andReturn($configurationValue);

        $this->fingerprintLoader->loadFingerprints($this->configurationMock);
    }

    /**
     * @group        certificate
     * @test
     *
     * @dataProvider invalidConfigurationProvider
     * @expectedException SAML2_Exception_InvalidArgumentException
     */
    public function it_correctly_parses_arrays_and_traversables($configurationValue)
    {
        $this->configurationMock
            ->shouldReceive('getCertificateFingerprints')
            ->once()
            ->andReturn($configurationValue);

        $result = $this->fingerprintLoader->loadFingerprints($this->configurationMock);
        $this->assertInstanceOf('SAML2_Certificate_FingerprintCollection', $result);
        $this->assertCount(count($configurationValue), $result);
    }

    public function invalidConfigurationProvider()
    {
        return array(
            'string'                             => array(''),
            'null value'                         => array(null),
            'non traversable'                    => array(new \StdClass()),
            'traversable with non string values' => array(new SAML2_Configuration_ArrayAdapter(array('a', 1, null))),
            'array with non string value'        => array(array('b', true, false))
        );
    }

    public function validConfigurationProvider()
    {
        return array(
            'array of strings'  => array(
                array('a', 'b', 'c')
            ),
            'mixed array'       => array(
                array(
                    'a',
                    new SAML2_Certificate_Stub_ImplementsToString('b'),
                    'c',
                )
            ),
            'mixed traversable' => array(
                new SAML2_Configuration_ArrayAdapter(array(
                    'a',
                    'b',
                    new SAML2_Certificate_Stub_ImplementsToString('c')
                ))
            ),
        );
    }
}
