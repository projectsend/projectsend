<?php


/**
 * Tests for SimpleSAML_Auth_State
 */
class Auth_StateTest extends PHPUnit_Framework_TestCase
{


    /**
     * Test the getPersistentAuthData() function.
     */
    public function testGetPersistentAuthData()
    {

        $mandatory = array(
            'Attributes' => array(),
            'Expire' => 1234,
            'LogoutState' => 'logoutState',
            'AuthInstant' => 123456,
            'RememberMe' => true,
            'saml:sp:NameID' => 'nameID',
        );

        // check just mandatory parameters
        $state = $mandatory;
        $expected = $mandatory;
        $this->assertEquals(
            $expected,
            SimpleSAML_Auth_State::getPersistentAuthData($state),
            'Mandatory state attributes did not survive as expected'.print_r($expected, true)
        );

        // check missing mandatory parameters
        unset($state['LogoutState']);
        unset($state['RememberMe']);
        $expected = $state;
        $this->assertEquals(
            $expected,
            SimpleSAML_Auth_State::getPersistentAuthData($state),
            'Some error occurred with missing mandatory parameters'
        );

        // check additional non-persistent parameters
        $additional = array(
            'additional1' => 1,
            'additional2' => 2,
        );
        $state = array_merge($mandatory, $additional);
        $expected = $mandatory;
        $this->assertEquals(
            $expected,
            SimpleSAML_Auth_State::getPersistentAuthData($state),
            'Additional parameters survived'
        );

        // check additional persistent parameters
        $additional['PersistentAuthData'] = array('additional1');
        $state = array_merge($mandatory, $additional);
        $expected = $state;
        unset($expected['additional2']);
        unset($expected['PersistentAuthData']);
        $this->assertEquals(
            $expected,
            SimpleSAML_Auth_State::getPersistentAuthData($state),
            'Some error occurred with additional, persistent parameters'
        );

        // check only additional persistent parameters
        $state = $additional;
        $expected = $state;
        unset($expected['additional2']);
        unset($expected['PersistentAuthData']);
        $this->assertEquals(
            $expected,
            SimpleSAML_Auth_State::getPersistentAuthData($state),
            'Some error occurred with additional, persistent parameters, and no mandatory ones'
        );
    }
}
