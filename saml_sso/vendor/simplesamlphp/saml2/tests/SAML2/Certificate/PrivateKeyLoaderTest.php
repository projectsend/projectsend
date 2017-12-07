<?php

class SAML2_Certificate_PrivateKeyLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var SAML2_Certificate_PrivateKeyLoader
     */
    private $privateKeyLoader;

    public function setUp()
    {
        $this->privateKeyLoader = new SAML2_Certificate_PrivateKeyLoader();
    }

    /**
     * @group        certificate
     * @test
     * @dataProvider privateKeyTestProvider
     *
     * @param SAML2_Configuration_PrivateKey $configuredKey
     */
    public function loading_a_configured_private_key_returns_a_certificate_private_key(
        SAML2_Configuration_PrivateKey $configuredKey
    ) {
        $resultingKey = $this->privateKeyLoader->loadPrivateKey($configuredKey);

        $this->assertInstanceOf('SAML2_Certificate_PrivateKey', $resultingKey);
        $this->assertEquals($resultingKey->getKeyAsString(), "This would normally contain the private key data.\n");
        $this->assertEquals($resultingKey->getPassphrase(), $configuredKey->getPassPhrase());
    }

    /**
     * Dataprovider for 'loading_a_configured_private_key_returns_a_certificate_private_key'
     *
     * @return array
     */
    public function privateKeyTestProvider()
    {
        return array(
            'no passphrase'   => array(
                new SAML2_Configuration_PrivateKey(
                    dirname(__FILE__) . '/File/a_fake_private_key_file.pem',
                    SAML2_Configuration_PrivateKey::NAME_DEFAULT
                )
            ),
            'with passphrase' => array(
                new SAML2_Configuration_PrivateKey(
                    dirname(__FILE__) . '/File/a_fake_private_key_file.pem',
                    SAML2_Configuration_PrivateKey::NAME_DEFAULT,
                    'foo bar baz'
                )
            ),
        );
    }
}
