<?php

/**
 * Test for the core:ScopeAttribute filter.
 */
class Test_Core_Auth_Process_ScopeAttribute extends PHPUnit_Framework_TestCase
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
        $filter = new sspmod_core_Auth_Process_ScopeAttribute($config, NULL);
        $filter->process($request);
        return $request;
    }

    /*
     * Test the most basic functionality.
     */
    public function testBasic()
    {
        $config = array(
            'scopeAttribute' => 'eduPersonPrincipalName',
            'sourceAttribute' => 'eduPersonAffiliation',
            'targetAttribute' => 'eduPersonScopedAffiliation',
        );
        $request = array(
            'Attributes' => array(
                'eduPersonPrincipalName' => array('jdoe@example.com'),
                'eduPersonAffiliation' => array('member'),
            )
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertArrayHasKey('eduPersonScopedAffiliation', $attributes);
        $this->assertEquals($attributes['eduPersonScopedAffiliation'], array('member@example.com'));
    }

    /*
     * If target attribute already set, module must add, not overwrite.
     */
    public function testNoOverwrite()
    {
        $config = array(
            'scopeAttribute' => 'eduPersonPrincipalName',
            'sourceAttribute' => 'eduPersonAffiliation',
            'targetAttribute' => 'eduPersonScopedAffiliation',
        );
        $request = array(
            'Attributes' => array(
                'eduPersonPrincipalName' => array('jdoe@example.com'),
                'eduPersonAffiliation' => array('member'),
                'eduPersonScopedAffiliation' => array('library-walk-in@example.edu'),
            )
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertEquals($attributes['eduPersonScopedAffiliation'], array('library-walk-in@example.edu', 'member@example.com'));
    }

    /*
     * If same scope already set, module must do nothing, not duplicate value.
     */
    public function testNoDuplication()
    {
        $config = array(
            'scopeAttribute' => 'eduPersonPrincipalName',
            'sourceAttribute' => 'eduPersonAffiliation',
            'targetAttribute' => 'eduPersonScopedAffiliation',
        );
        $request = array(
            'Attributes' => array(
                'eduPersonPrincipalName' => array('jdoe@example.com'),
                'eduPersonAffiliation' => array('member'),
                'eduPersonScopedAffiliation' => array('member@example.com'),
            )
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertEquals($attributes['eduPersonScopedAffiliation'], array('member@example.com'));
    }


    /*
     * If source attribute not set, nothing happens
     */
    public function testNoSourceAttribute()
    {
        $config = array(
            'scopeAttribute' => 'eduPersonPrincipalName',
            'sourceAttribute' => 'eduPersonAffiliation',
            'targetAttribute' => 'eduPersonScopedAffiliation',
        );
        $request = array(
            'Attributes' => array(
                'mail' => array('j.doe@example.edu', 'john@example.org'),
                'eduPersonAffiliation' => array('member'),
                'eduPersonScopedAffiliation' => array('library-walk-in@example.edu'),
            )
        );
        $result = self::processFilter($config, $request);
        $this->assertEquals($request['Attributes'], $result['Attributes']);
    }

    /*
     * If scope attribute not set, nothing happens
     */
    public function testNoScopeAttribute()
    {
        $config = array(
            'scopeAttribute' => 'eduPersonPrincipalName',
            'sourceAttribute' => 'eduPersonAffiliation',
            'targetAttribute' => 'eduPersonScopedAffiliation',
        );
        $request = array(
            'Attributes' => array(
                'mail' => array('j.doe@example.edu', 'john@example.org'),
                'eduPersonScopedAffiliation' => array('library-walk-in@example.edu'),
                'eduPersonPrincipalName' => array('jdoe@example.com'),
            )
        );
        $result = self::processFilter($config, $request);
        $this->assertEquals($request['Attributes'], $result['Attributes']);
    }

    /*
     * When multiple @ signs in attribute, will use the first one.
     */
    public function testMultiAt()
    {
        $config = array(
            'scopeAttribute' => 'eduPersonPrincipalName',
            'sourceAttribute' => 'eduPersonAffiliation',
            'targetAttribute' => 'eduPersonScopedAffiliation',
        );
        $request = array(
            'Attributes' => array(
                'eduPersonPrincipalName' => array('john@doe@example.com'),
                'eduPersonAffiliation' => array('member'),
            )
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertEquals($attributes['eduPersonScopedAffiliation'], array('member@doe@example.com'));
    }

    /*
     * When multiple values in source attribute, should render multiple targets.
     */
    public function testMultivaluedSource()
    {
        $config = array(
            'scopeAttribute' => 'eduPersonPrincipalName',
            'sourceAttribute' => 'eduPersonAffiliation',
            'targetAttribute' => 'eduPersonScopedAffiliation',
        );
        $request = array(
            'Attributes' => array(
                'eduPersonPrincipalName' => array('jdoe@example.com'),
                'eduPersonAffiliation' => array('member','staff','faculty'),
            )
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertEquals($attributes['eduPersonScopedAffiliation'], array('member@example.com','staff@example.com','faculty@example.com'));
    }

    /*
     * When the source attribute doesn't have a scope, the entire value is used.
     */
    public function testNoAt()
    {
        $config = array(
            'scopeAttribute' => 'schacHomeOrganization',
            'sourceAttribute' => 'eduPersonAffiliation',
            'targetAttribute' => 'eduPersonScopedAffiliation',
        );
        $request = array(
            'Attributes' => array(
                'schacHomeOrganization' => array('example.org'),
                'eduPersonAffiliation' => array('student'),
            )
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertEquals($attributes['eduPersonScopedAffiliation'], array('student@example.org'));
    }
}
