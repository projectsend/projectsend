<?php

class SAML2_Certificate_KeyTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @group certificate
     *
     * @test
     * @expectedException SAML2_Certificate_Exception_InvalidKeyUsageException
     */
    public function invalid_key_usage_should_throw_an_exception()
    {
        $key = new SAML2_Certificate_Key(array(SAML2_Certificate_Key::USAGE_SIGNING => true));

        $key->canBeUsedFor('foo');
    }

    /**
     * @group certificate
     *
     * @test
     */
    public function assert_that_key_usage_check_works_correctly()
    {
        $key = new SAML2_Certificate_Key(array(SAML2_Certificate_Key::USAGE_SIGNING => true));

        $this->assertTrue($key->canBeUsedFor(SAML2_Certificate_Key::USAGE_SIGNING));
        $this->assertFalse($key->canBeUsedFor(SAML2_Certificate_Key::USAGE_ENCRYPTION));

        $key[SAML2_Certificate_Key::USAGE_ENCRYPTION] = false;
        $this->assertFalse($key->canBeUsedFor(SAML2_Certificate_Key::USAGE_ENCRYPTION));
    }
}
