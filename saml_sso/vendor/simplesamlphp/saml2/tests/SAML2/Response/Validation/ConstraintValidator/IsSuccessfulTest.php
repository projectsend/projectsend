<?php

class SAML2_Response_Validation_ConstraintValidator_IsSuccessfulTest extends \PHPUnit_Framework_TestCase
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
    public function validating_a_successful_response_gives_a_valid_validation_result()
    {
        $this->response->shouldReceive('isSuccess')->once()->andReturn(true);

        $validator = new SAML2_Response_Validation_ConstraintValidator_IsSuccessful();
        $result    = new SAML2_Response_Validation_Result();

        $validator->validate($this->response, $result);

        $this->assertTrue($result->isValid());
    }

    /**
     * @group response-validation
     * @test
     */
    public function an_unsuccessful_response_is_not_valid_and_generates_a_proper_error_message()
    {
        $responseStatus = array(
            'Code'    => 'foo',
            'SubCode' => SAML2_Const::STATUS_PREFIX . 'bar',
            'Message' => 'this is a test message'
        );
        $this->response->shouldReceive('isSuccess')->once()->andReturn(false);
        $this->response->shouldReceive('getStatus')->once()->andReturn($responseStatus);

        $validator = new SAML2_Response_Validation_ConstraintValidator_IsSuccessful();
        $result    = new SAML2_Response_Validation_Result();

        $validator->validate($this->response, $result);
        $errors = $result->getErrors();

        $this->assertFalse($result->isValid());
        $this->assertCount(1, $errors);
        $this->assertEquals('foo/bar this is a test message', $errors[0]);
    }

}
