<?php

class SAML2_Response_Validation_ConstraintValidator_DestinationMatchesTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Mockery\MockInterface
     */
    private $response;

    public function setUp()
    {
        $this->response = \Mockery::mock('SAML2_Response');
    }

    /**
     * @group response-validation
     * @test
     */
    public function a_response_is_valid_when_the_destinations_match()
    {
        $expectedDestination = new SAML2_Configuration_Destination('VALID DESTINATION');
        $this->response->shouldReceive('getDestination')->once()->andReturn('VALID DESTINATION');
        $validator = new SAML2_Response_Validation_ConstraintValidator_DestinationMatches($expectedDestination);
        $result    = new SAML2_Response_Validation_Result();

        $validator->validate($this->response, $result);

        $this->assertTrue($result->isValid());
    }

    /**
     * @group response-validation
     * @test
     */
    public function a_response_is_not_valid_when_the_destinations_are_not_equal()
    {
        $this->response->shouldReceive('getDestination')->once()->andReturn('FOO');
        $validator = new SAML2_Response_Validation_ConstraintValidator_DestinationMatches(
            new SAML2_Configuration_Destination('BAR')
        );
        $result = new SAML2_Response_Validation_Result();

        $validator->validate($this->response, $result);
        $errors = $result->getErrors();

        $this->assertFalse($result->isValid());
        $this->assertCount(1, $errors);
        $this->assertEquals('Destination in response "FOO" does not match the expected destination "BAR"', $errors[0]);
    }
}
