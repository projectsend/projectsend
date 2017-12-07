<?php

/**
 * Test for the core:AttributeRealm filter.
 */
class Test_Core_Auth_Process_AttributeRealm extends PHPUnit_Framework_TestCase
{

    /**
     * Helper function to run the filter with a given configuration.
     *
     * @param array $config  The filter configuration.
     * @param array $request  The request state.
     * @return array  The state array after processing.
     */
    private static function processFilter(array $config, array $request)
    {
        $filter = new sspmod_core_Auth_Process_AttributeRealm($config, NULL);
        $filter->process($request);
        return $request;
    }

    /**
     * Test the most basic functionality.
     */
    public function testBasic()
    {
        $config = array(
        );
        $request = array(
            'Attributes' => array(),
            'UserID' => 'user2@example.org',
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertArrayHasKey('realm', $attributes);
        $this->assertEquals($attributes['realm'], array('example.org'));
    }

    /**
     * Test no userid set
     *
     * @expectedException Exception
     */
    public function testNoUserID()
    {
        $config = array(
        );
        $request = array(
            'Attributes' => array(),
        );
        $result = self::processFilter($config, $request);
    }

    /**
     * Test with configuration.
     */
    public function testAttributeNameConfig()
    {
        $config = array(
            'attributename' => 'schacHomeOrganization',
        );
        $request = array(
            'Attributes' => array(
                'displayName' => 'Joe User',
                'schacGender' => 9,
            ),
            'UserID' => 'user2@example.org',
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertArrayHasKey('schacHomeOrganization', $attributes);
        $this->assertArrayHasKey('displayName', $attributes);
        $this->assertEquals($attributes['schacHomeOrganization'], array('example.org'));
    }

    /**
     * When target attribute exists it will be overwritten
     */
    public function testTargetAttributeOverwritten()
    {
        $config = array(
            'attributename' => 'schacHomeOrganization',
        );
        $request = array(
            'Attributes' => array(
                'displayName' => 'Joe User',
                'schacGender' => 9,
                'schacHomeOrganization' => 'example.com',
            ),
            'UserID' => 'user2@example.org',
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertArrayHasKey('schacHomeOrganization', $attributes);
        $this->assertEquals($attributes['schacHomeOrganization'], array('example.org'));
    }

    /**
     * When source attribute has no "@" no realm is added
     */
    public function testNoAtisNoOp()
    {
        $config = array();
        $request = array(
            'Attributes' => array(
                'displayName' => 'Joe User',
            ),
            'UserID' => 'user2',
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertArrayNotHasKey('realm', $attributes);
    }

    /**
     * When source attribute has more than one "@" no realm is added
     */
    public function testMultiAtisNoOp()
    {
        $config = array();
        $request = array(
            'Attributes' => array(
                'displayName' => 'Joe User',
            ),
            'UserID' => 'user2@home@example.org',
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertArrayNotHasKey('realm', $attributes);
    }
}
