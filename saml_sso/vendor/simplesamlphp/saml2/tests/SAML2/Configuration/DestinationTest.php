<?php

class SAML2_Configuration_DestinationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @group configuration
     * @test
     */
    public function two_destinations_with_the_same_value_are_equal()
    {
        $destinationOne = new SAML2_Configuration_Destination('a');
        $destinationTwo = new SAML2_Configuration_Destination('a');

        $this->assertTrue($destinationOne->equals($destinationTwo));
    }

    /**
     * @group configuration
     * @test
     */
    public function two_destinations_with_the_different_values_are_not_equal()
    {
        $destinationOne = new SAML2_Configuration_Destination('a');
        $destinationTwo = new SAML2_Configuration_Destination('a');

        $this->assertTrue($destinationOne->equals($destinationTwo));
    }

    /**
     * @group configuration
     * @test
     * @dataProvider nonStringValueProvider
     * @expectedException SAML2_Exception_InvalidArgumentException
     */
    public function a_destination_cannot_be_created_with_a_non_string_value($value)
    {
        $destination = new SAML2_Configuration_Destination($value);
    }

    /**
     * data-provider for a_destination_cannot_be_created_with_a_non_string_value
     */
    public function nonStringValueProvider()
    {
        return array(
            'array'  => array(array()),
            'object' => array(new \StdClass()),
            'int'    => array(1),
            'float'  => array(1.2323),
            'bool'   => array(false)
        );
    }
}
