<?php

/**
 * Test for the core:ScopeFromAttribute filter.
 */
class Test_Core_Auth_Process_ScopeFromAttribute extends PHPUnit_Framework_TestCase
{

    /*
     * Helper function to run the filter with a given configuration.
     *
     * @param array $config  The filter configuration.
     * @param array $request  The request state.
     * @return array  The state array after processing.
     */
    private static function processFilter(array $config, array $request)
    {
        $filter = new sspmod_core_Auth_Process_ScopeFromAttribute($config, NULL);
        $filter->process($request);
        return $request;
    }

    /*
     * Test the most basic functionality.
     */
    public function testBasic()
    {
        $config = array(
            'sourceAttribute' => 'eduPersonPrincipalName',
            'targetAttribute' => 'scope',
        );
        $request = array(
            'Attributes' => array(
                'eduPersonPrincipalName' => array('jdoe@example.com'),
            )
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertArrayHasKey('scope', $attributes);
        $this->assertEquals($attributes['scope'], array('example.com'));
    }

    /*
     * If scope already set, module must not overwrite.
     */
    public function testNoOverwrite()
    {
        $config = array(
            'sourceAttribute' => 'eduPersonPrincipalName',
            'targetAttribute' => 'scope',
        );
        $request = array(
            'Attributes' => array(
                'eduPersonPrincipalName' => array('jdoe@example.com'),
                'scope' => array('example.edu')
            )
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertEquals($attributes['scope'], array('example.edu'));
    }

    /*
     * If source attribute not set, nothing happens
     */
    public function testNoSourceAttribute()
    {
        $config = array(
            'sourceAttribute' => 'eduPersonPrincipalName',
            'targetAttribute' => 'scope',
        );
        $request = array(
            'Attributes' => array(
                'mail' => array('j.doe@example.edu', 'john@example.org'),
                'scope' => array('example.edu')
            )
        );
        $result = self::processFilter($config, $request);
        $this->assertEquals($request['Attributes'], $result['Attributes']);
    }

    /*
     * When multiple @ signs in attribute, should use last one.
     */
    public function testMultiAt()
    {
        $config = array(
            'sourceAttribute' => 'eduPersonPrincipalName',
            'targetAttribute' => 'scope',
        );
        $request = array(
            'Attributes' => array(
                'eduPersonPrincipalName' => array('john@doe@example.com'),
            )
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertEquals($attributes['scope'], array('example.com'));
    }

    /*
     * When the source attribute doesn't have a scope, a warning is emitted
     * NOTE: currently disabled: this triggers a warning and a warning
     * wants to start a session which we cannot do in phpunit. How to fix?
     */
    public function testNoAt()
    {
        $config = array(
            'sourceAttribute' => 'eduPersonPrincipalName',
            'targetAttribute' => 'scope',
        );
        $request = array(
            'Attributes' => array(
                'eduPersonPrincipalName' => array('johndoe'),
            )
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];

        $this->assertArrayNotHasKey('scope', $attributes);
    }
}
