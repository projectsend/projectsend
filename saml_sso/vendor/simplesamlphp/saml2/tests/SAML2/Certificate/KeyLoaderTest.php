<?php

class SAML2_Certificate_KeyLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var SAML2_Certificate_KeyLoader
     */
    private $keyLoader;

    /**
     * Known to be valid certificate string
     *
     * @var string
     */
    private $certificate = "-----BEGIN CERTIFICATE-----\nMIICgTCCAeoCCQCbOlrWDdX7FTANBgkqhkiG9w0BAQUFADCBhDELMAkGA1UEBhMC\nTk8xGDAWBgNVBAgTD0FuZHJlYXMgU29sYmVyZzEMMAoGA1UEBxMDRm9vMRAwDgYD\nVQQKEwdVTklORVRUMRgwFgYDVQQDEw9mZWlkZS5lcmxhbmcubm8xITAfBgkqhkiG\n9w0BCQEWEmFuZHJlYXNAdW5pbmV0dC5ubzAeFw0wNzA2MTUxMjAxMzVaFw0wNzA4\nMTQxMjAxMzVaMIGEMQswCQYDVQQGEwJOTzEYMBYGA1UECBMPQW5kcmVhcyBTb2xi\nZXJnMQwwCgYDVQQHEwNGb28xEDAOBgNVBAoTB1VOSU5FVFQxGDAWBgNVBAMTD2Zl\naWRlLmVybGFuZy5ubzEhMB8GCSqGSIb3DQEJARYSYW5kcmVhc0B1bmluZXR0Lm5v\nMIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDivbhR7P516x/S3BqKxupQe0LO\nNoliupiBOesCO3SHbDrl3+q9IbfnfmE04rNuMcPsIxB161TdDpIesLCn7c8aPHIS\nKOtPlAeTZSnb8QAu7aRjZq3+PbrP5uW3TcfCGPtKTytHOge/OlJbo078dVhXQ14d\n1EDwXJW1rRXuUt4C8QIDAQABMA0GCSqGSIb3DQEBBQUAA4GBACDVfp86HObqY+e8\nBUoWQ9+VMQx1ASDohBjwOsg2WykUqRXF+dLfcUH9dWR63CtZIKFDbStNomPnQz7n\nbK+onygwBspVEbnHuUihZq3ZUdmumQqCw4Uvs/1Uvq3orOo/WJVhTyvLgFVK2Qar\nQ4/67OZfHd7R+POBXhophSMv1ZOo\n-----END CERTIFICATE-----\n";

    /**
     * @var \Mockery\MockInterface
     */
    private $configurationMock;

    public function setUp()
    {
        $this->keyLoader = new SAML2_Certificate_KeyLoader();
        $this->configurationMock = \Mockery::mock('SAML2_Configuration_CertificateProvider');
    }

    /**
     * @group certificate
     *
     * @test
     */
    public function load_keys_checks_for_usage_of_key()
    {
        $signing = array(SAML2_Certificate_Key::USAGE_SIGNING => true);
        $encryption = array(SAML2_Certificate_Key::USAGE_ENCRYPTION => true);

        $keys = array($signing, $encryption);

        $this->keyLoader->loadKeys($keys, SAML2_Certificate_Key::USAGE_SIGNING);
        $loadedKeys = $this->keyLoader->getKeys();

        $this->assertCount(1, $loadedKeys, 'Amount of keys that have been loaded does not match the expected amount');
        $this->assertTrue($loadedKeys->get(0)->canBeUsedFor(SAML2_Certificate_Key::USAGE_SIGNING));
    }

    /**
     * @group certificate
     *
     * @test
     * @expectedException SAML2_Exception_InvalidArgumentException
     */
    public function certificate_data_with_invalid_format_throws_an_exception()
    {
        $this->keyLoader->loadCertificateData(array());
    }

    /**
     * @group certificate
     *
     * @test
     */
    public function certificate_data_is_loaded_as_key()
    {
        $this->keyLoader->loadCertificateData($this->certificate);

        $loadedKeys = $this->keyLoader->getKeys();
        $loadedKey = $loadedKeys->get(0);

        $this->assertTrue($this->keyLoader->hasKeys());
        $this->assertCount(1, $loadedKeys);

        $this->assertEquals(preg_replace('~\s+~', '', $this->certificate), $loadedKey['X509Certificate']);
    }

    /**
     * @group certificate
     *
     * @test
     * @expectedException SAML2_Certificate_Exception_InvalidCertificateStructureException
     */
    public function loading_a_file_with_the_wrong_format_throws_an_exception()
    {
        $filePath = dirname(__FILE__) . '/File/';
        $this->keyLoader->loadCertificateFile($filePath . 'not_a_key.crt');
    }

    /**
     * @group certificate
     *
     * @test
     */
    public function loading_a_certificate_from_file_creates_a_key()
    {
        $file = dirname(__FILE__) . '/File/example.org.crt';
        $this->keyLoader->loadCertificateFile($file);

        $loadedKeys = $this->keyLoader->getKeys();
        $loadedKey = $loadedKeys->get(0);
        $fileContents = file_get_contents($file);
        preg_match(SAML2_Utilities_Certificate::CERTIFICATE_PATTERN, $fileContents, $matches);
        $expected = preg_replace('~\s+~', '', $matches[1]);

        $this->assertTrue($this->keyLoader->hasKeys());
        $this->assertCount(1, $loadedKeys);
        $this->assertEquals($expected, $loadedKey['X509Certificate']);
    }

    /**
     * @group certificate
     *
     * @test
     * @expectedException SAML2_Certificate_Exception_NoKeysFoundException
     */
    public function loading_a_required_certificate_from_an_empty_configuration_throws_an_exception()
    {
        $this->configurationMock
            ->shouldReceive('getKeys')
            ->once()
            ->andReturnNull()
            ->shouldReceive('getCertificateData')
            ->once()
            ->andReturnNull()
            ->shouldReceive('getCertificateFile')
            ->once()
            ->andReturnNull();

        $this->keyLoader->loadKeysFromConfiguration($this->configurationMock, null, true);
    }

    /**
     * @group certificate
     *
     * @test
     */
    public function loading_a_certificate_file_from_configuration_creates_key()
    {
        $file = dirname(__FILE__) . '/File/example.org.crt';
        $this->configurationMock
            ->shouldReceive('getKeys')
            ->atMost()
            ->once()
            ->andReturnNull()
            ->shouldReceive('getCertificateData')
            ->atMost()
            ->once()
            ->andReturnNull()
            ->shouldReceive('getCertificateFile')
            ->once()
            ->andReturn($file);

        $loadedKeys = $this->keyLoader->loadKeysFromConfiguration($this->configurationMock);

        $this->assertCount(1, $loadedKeys);
    }

    /**
     * @group certificate
     *
     * @test
     * @expectedException SAML2_Certificate_Exception_InvalidCertificateStructureException
     */
    public function loading_an_invalid_certificate_file_from_configuration_throws_exception()
    {
        $file = dirname(__FILE__) . '/File/not_a_key.crt';
        $this->configurationMock
            ->shouldReceive('getKeys')
            ->atMost()
            ->once()
            ->andReturnNull()
            ->shouldReceive('getCertificateData')
            ->atMost()
            ->once()
            ->andReturnNull()
            ->shouldReceive('getCertificateFile')
            ->once()
            ->andReturn($file);

        $loadedKeys = $this->keyLoader->loadKeysFromConfiguration($this->configurationMock);
    }
}
