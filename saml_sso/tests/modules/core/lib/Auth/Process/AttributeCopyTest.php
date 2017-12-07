<?php

/**
 * Test for the core:AttributeCopy filter.
 */
class Test_Core_Auth_Process_AttributeCopy extends PHPUnit_Framework_TestCase
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
        $filter = new sspmod_core_Auth_Process_AttributeCopy($config, NULL);
        $filter->process($request);
        return $request;
    }

    /**
     * Test the most basic functionality.
     */
    public function testBasic()
    {
        $config = array(
            'test' => 'testnew',
        );
        $request = array(
            'Attributes' => array('test' => array('AAP')),
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertArrayHasKey('test', $attributes);
        $this->assertArrayHasKey('testnew', $attributes);
        $this->assertEquals($attributes['testnew'], array('AAP'));
    }

    /**
     * Test that existing attributes are left unmodified.
     */
    public function testExistingNotModified()
    {
        $config = array(
            'test' => 'testnew',
        );
        $request = array(
            'Attributes' => array(
                'test' => array('AAP'),
                'original1' => array('original_value1'),
                'original2' => array('original_value2'),
            ),
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertArrayHasKey('testnew', $attributes);
        $this->assertEquals($attributes['test'], array('AAP'));
        $this->assertArrayHasKey('original1', $attributes);
        $this->assertEquals($attributes['original1'], array('original_value1'));
        $this->assertArrayHasKey('original2', $attributes);
        $this->assertEquals($attributes['original2'], array('original_value2'));
    }

    /**
     * Test copying multiple attributes
     */
    public function testCopyMultiple()
    {
        $config = array(
            'test1' => 'new1',
            'test2' => 'new2',
        );
        $request = array(
            'Attributes' => array('test1' => array('val1'), 'test2' => array('val2.1','val2.2')),
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertArrayHasKey('new1', $attributes);
        $this->assertEquals($attributes['new1'], array('val1'));
        $this->assertArrayHasKey('new2', $attributes);
        $this->assertEquals($attributes['new2'], array('val2.1','val2.2'));
    }

    /**
     * Test behaviour when target attribute exists (should be replaced).
     */
    public function testCopyClash()
    {
        $config = array(
            'test' => 'new1',
        );
        $request = array(
            'Attributes' => array(
                'test' => array('testvalue1'),
                'new1' => array('newvalue1'),
            ),
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertEquals($attributes['new1'], array('testvalue1'));
    }

    /**
     * Test wrong attribute name
     *
     * @expectedException Exception
     */
    public function testWrongAttributeName()
    {
        $config = array(
            array('value2'),
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
            'test' => array('test2'),
        );
        $request = array(
            'Attributes' => array(
                'test' => array('value1'),
            ),
        );
        $result = self::processFilter($config, $request);
    }
}
