<?php

/**
 * Test for the core:AttributeAdd filter.
 */
class Test_Core_Auth_Process_AttributeAdd extends PHPUnit_Framework_TestCase
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
        $filter = new sspmod_core_Auth_Process_AttributeAdd($config, NULL);
        $filter->process($request);
        return $request;
    }

    /**
     * Test the most basic functionality.
     */
    public function testBasic()
    {
        $config = array(
            'test' => array('value1', 'value2'),
        );
        $request = array(
            'Attributes' => array(),
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertArrayHasKey('test', $attributes);
        $this->assertEquals($attributes['test'], array('value1', 'value2'));
    }

    /**
     * Test that existing attributes are left unmodified.
     */
    public function testExistingNotModified()
    {
        $config = array(
            'test' => array('value1', 'value2'),
        );
        $request = array(
            'Attributes' => array(
                'original1' => array('original_value1'),
                'original2' => array('original_value2'),
            ),
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertArrayHasKey('test', $attributes);
        $this->assertEquals($attributes['test'], array('value1', 'value2'));
        $this->assertArrayHasKey('original1', $attributes);
        $this->assertEquals($attributes['original1'], array('original_value1'));
        $this->assertArrayHasKey('original2', $attributes);
        $this->assertEquals($attributes['original2'], array('original_value2'));
    }

    /**
     * Test single string as attribute value.
     */
    public function testStringValue()
    {
        $config = array(
            'test' => 'value',
        );
        $request = array(
            'Attributes' => array(),
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertArrayHasKey('test', $attributes);
        $this->assertEquals($attributes['test'], array('value'));
    }

    /**
     * Test adding multiple attributes in one config.
     */
    public function testAddMultiple()
    {
        $config = array(
            'test1' => array('value1'),
            'test2' => array('value2'),
        );
        $request = array(
            'Attributes' => array(),
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertArrayHasKey('test1', $attributes);
        $this->assertEquals($attributes['test1'], array('value1'));
        $this->assertArrayHasKey('test2', $attributes);
        $this->assertEquals($attributes['test2'], array('value2'));
    }

    /**
     * Test behavior when appending attribute values.
     */
    public function testAppend()
    {
        $config = array(
            'test' => array('value2'),
        );
        $request = array(
            'Attributes' => array(
                'test' => array('value1'),
            ),
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertEquals($attributes['test'], array('value1', 'value2'));
    }

    /**
     * Test replacing attribute values.
     */
    public function testReplace()
    {
        $config = array(
            '%replace',
            'test' => array('value2'),
        );
        $request = array(
            'Attributes' => array(
                'test' => array('value1'),
            ),
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertEquals($attributes['test'], array('value2'));
    }

    /**
     * Test wrong usage generates exceptions
     *
     * @expectedException Exception
     */
    public function testWrongFlag()
    {
        $config = array(
            '%nonsense',
            'test' => array('value2'),
        );
        $request = array(
            'Attributes' => array(
                'test' => array('value1'),
            ),
        );
        $result = self::processFilter($config, $request);
    }

    /**
     * Test wrong attribute value
     *
     * @expectedException Exception
     */
    public function testWrongAttributeValue()
    {
        $config = array(
            '%replace',
            'test' => array(true),
        );
        $request = array(
            'Attributes' => array(
                'test' => array('value1'),
            ),
        );
        $result = self::processFilter($config, $request);
    }
}
