<?php

class SAML2_Certificate_X509Test extends PHPUnit_Framework_TestCase
{
    /**
     * @group certificate
     *
     * @test
     */
    public function x509_certificate_contents_must_be_stripped_of_whitespace()
    {
        $toTest = array(
            'X509Certificate' => ' Should   No Longer  Have Whitespaces'
        );

        $viaConstructor                = new SAML2_Certificate_X509($toTest);
        $viaSetting                    = new SAML2_Certificate_X509(array());
        $viaSetting['X509Certificate'] = $toTest['X509Certificate'];
        $viaFactory                    = SAML2_Certificate_X509::createFromCertificateData($toTest['X509Certificate']);

        $this->assertEquals($viaConstructor['X509Certificate'], 'ShouldNoLongerHaveWhitespaces');
        $this->assertEquals($viaSetting['X509Certificate'], 'ShouldNoLongerHaveWhitespaces');
        $this->assertEquals($viaFactory['X509Certificate'], 'ShouldNoLongerHaveWhitespaces');
    }
}
