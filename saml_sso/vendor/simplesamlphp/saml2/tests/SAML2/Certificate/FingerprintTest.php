<?php

class SAML2_Certificate_FingerprintTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var SAML2_Certificate_Fingerprint
     */
    private $fingerprint;

    /**
     * @group certificate
     * @test
     *
     * @expectedException SAML2_Exception_InvalidArgumentException
     */
    public function fails_on_invalid_fingerprint_data()
    {
        $this->fingerprint = new SAML2_Certificate_Fingerprint(NULL);
    }
}
