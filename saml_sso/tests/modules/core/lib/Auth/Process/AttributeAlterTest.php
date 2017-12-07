<?php

/**
 * Test for the core:AttributeAlter filter.
 */
class Test_Core_Auth_Process_AttributeAlter extends PHPUnit_Framework_TestCase
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
        $filter = new sspmod_core_Auth_Process_AttributeAlter($config, NULL);
        $filter->process($request);
        return $request;
    }

    /**
     * Test the most basic functionality.
     */
    public function testBasic()
    {
        $config = array(
            'subject' => 'test',
            'pattern' => '/wrong/',
            'replacement' => 'right',
        );

        $request = array(
            'Attributes' => array(
                 'test' => array('somethingiswrong'),
             ),
        );

        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertArrayHasKey('test', $attributes);
        $this->assertEquals($attributes['test'], array('somethingisright'));
    }

    /**
     * Test the most basic functionality.
     */
    public function testWithTarget()
    {
        $config = array(
            'subject' => 'test',
            'target' => 'test2',
            'pattern' => '/wrong/',
            'replacement' => 'right',
        );

        $request = array(
            'Attributes' => array(
                 'something' => array('somethingelse'),
                 'test' => array('wrong'),
             ),
        );

        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertArrayHasKey('test2', $attributes);
        $this->assertEquals($attributes['test'], array('wrong'));
        $this->assertEquals($attributes['test2'], array('right'));
    }

    /**
     * Module is a no op if subject attribute is not present.
     */
    public function testNomatch()
    {
        $config = array(
            'subject' => 'test',
            'pattern' => '/wrong/',
            'replacement' => 'right',
        );

        $request = array(
            'Attributes' => array(
                 'something' => array('somevalue'),
                 'somethingelse' => array('someothervalue'),
             ),
        );

        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertEquals($attributes,
            array('something' => array('somevalue'),
            'somethingelse' => array('someothervalue')));
    }

    /**
     * Test replacing attribute value.
     */
    public function testReplaceMatch()
    {
        $config = array(
            'subject' => 'source',
            'pattern' => '/wrong/',
            'replacement' => 'right',
            '%replace',
        );
        $request = array(
            'Attributes' => array(
                'source' => array('wrongthing'),
            ),
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertEquals($attributes['source'], array('right'));
    }

    /**
     * Test replacing attribute value.
     */
    public function testReplaceMatchWithTarget()
    {
        $config = array(
            'subject' => 'source',
            'pattern' => '/wrong/',
            'replacement' => 'right',
            'target' => 'test',
            '%replace',
        );
        $request = array(
            'Attributes' => array(
                'source' => array('wrong'),
                'test'   => array('wrong'),
            ),
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertEquals($attributes['test'], array('right'));
    }

    /**
     * Test replacing attribute values.
     */
    public function testReplaceNoMatch()
    {
        $config = array(
            'subject' => 'test',
            'pattern' => '/doink/',
            'replacement' => 'wrong',
            'target' => 'test',
            '%replace',
        );
        $request = array(
            'Attributes' => array(
                'source' => array('wrong'),
                'test'   => array('right'),
            ),
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertEquals($attributes['test'], array('right'));
    }

    /**
     * Test removing attribute values.
     * Note that removing a value does not renumber the attributes array.
     * Also ensure unrelated attributes are not touched.
     */
    public function testRemoveMatch()
    {
        $config = array(
            'subject' => 'eduPersonAffiliation',
            'pattern' => '/^emper/',
            '%remove',
        );
        $request = array(
            'Attributes' => array(
                'displayName' => array('emperor kuzco'),
                'eduPersonAffiliation' => array('member', 'emperor', 'staff'),
            ),
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertEquals($attributes['displayName'], array('emperor kuzco'));
        $this->assertEquals($attributes['eduPersonAffiliation'], array(0 => 'member', 2 => 'staff'));
    }

    /**
     * Test removing attribute values, resulting in an empty attribute.
     */
    public function testRemoveMatchAll()
    {
        $config = array(
            'subject' => 'eduPersonAffiliation',
            'pattern' => '/^emper/',
            '%remove',
        );
        $request = array(
            'Attributes' => array(
                'displayName' => array('emperor kuzco'),
                'eduPersonAffiliation' => array('emperess', 'emperor'),
            ),
        );
        $result = self::processFilter($config, $request);
        $attributes = $result['Attributes'];
        $this->assertArrayNotHasKey('eduPersonAffiliation', $attributes);
    }

    /**
     * Test for exception with illegal config.
     *
     * @expectedException Exception
     */
    public function testWrongConfig()
    {
        $config = array(
            'subject' => 'eduPersonAffiliation',
            'pattern' => '/^emper/',
            '%dwiw',
        );
        $request = array(
            'Attributes' => array(
                'eduPersonAffiliation' => array('emperess', 'emperor'),
            ),
        );
        $result = self::processFilter($config, $request);
    }

    /**
     * Test for exception with illegal config.
     *
     * @expectedException Exception
     */
    public function testIncompleteConfig()
    {
        $config = array(
            'subject' => 'eduPersonAffiliation',
        );
        $request = array(
            'Attributes' => array(
                'eduPersonAffiliation' => array('emperess', 'emperor'),
            ),
        );
        $result = self::processFilter($config, $request);
    }

    /**
     * Test for exception with illegal config.
     *
     * @expectedException Exception
     */
    public function testIncompleteConfig2()
    {
        $config = array(
            'subject' => 'test',
            'pattern' => '/wrong/',
        );

        $request = array(
            'Attributes' => array(
                 'test' => array('somethingiswrong'),
             ),
        );

        $request = array(
            'Attributes' => array(
                'eduPersonAffiliation' => array('emperess', 'emperor'),
            ),
        );
        $result = self::processFilter($config, $request);
    }

    /**
     * Test for exception with illegal config.
     *
     * @expectedException Exception
     */
    public function testIncompleteConfig3()
    {
        $config = array(
            'subject' => 'test',
            'pattern' => '/wrong/',
            '%replace',
            '%remove',
        );

        $request = array(
            'Attributes' => array(
                 'test' => array('somethingiswrong'),
             ),
        );

        $request = array(
            'Attributes' => array(
                'eduPersonAffiliation' => array('emperess', 'emperor'),
            ),
        );
        $result = self::processFilter($config, $request);
    }

    /**
     * Test for exception with illegal config.
     *
     * @expectedException Exception
     */
    public function testIncompleteConfig4()
    {
        $config = array(
            'subject' => 'test',
            'pattern' => '/wrong/',
            'target' => 'test2',
            '%remove',
        );

        $request = array(
            'Attributes' => array(
                 'test' => array('somethingiswrong'),
             ),
        );

        $request = array(
            'Attributes' => array(
                'eduPersonAffiliation' => array('emperess', 'emperor'),
            ),
        );
        $result = self::processFilter($config, $request);
    }


    /**
     * Test for exception with illegal config.
     *
     * @expectedException Exception
     */
    public function testIncompleteConfig5()
    {
        $config = array(
            'subject' => 'test',
            'pattern' => '/wrong/',
            'replacement' => null,
        );

        $request = array(
            'Attributes' => array(
                 'test' => array('somethingiswrong'),
             ),
        );

        $request = array(
            'Attributes' => array(
                'eduPersonAffiliation' => array('emperess', 'emperor'),
            ),
        );
        $result = self::processFilter($config, $request);
    }
}

