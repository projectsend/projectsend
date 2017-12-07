<?php


/**
 * Test for the core:PHP filter.
 */
class Test_Core_Auth_Process_PHP extends PHPUnit_Framework_TestCase
{

    /**
     * Helper function to run the filter with a given configuration.
     *
     * @param array $config The filter configuration.
     * @param array $request The request state.
     *
     * @return array The state array after processing.
     */
    private static function processFilter(array $config, array $request)
    {
        $filter = new sspmod_core_Auth_Process_PHP($config, null);
        @$filter->process($request);
        return $request;
    }


    /**
     * Test the configuration of the filter.
     *
     * @expectedException SimpleSAML_Error_Exception
     */
    public function testInvalidConfiguration()
    {
        $config = array();
        new sspmod_core_Auth_Process_PHP($config, null);
    }


    /**
     * Check that defining the code works as expected.
     */
    public function testCodeDefined()
    {
        $config = array(
            'code' => '
                $attributes["key"] = "value";
            ',
        );
        $request = array('Attributes' => array());
        $expected = array(
            'Attributes' => array(
                'key' => 'value',
            ),
        );

        $this->assertEquals($expected, $this->processFilter($config, $request));
    }
}
