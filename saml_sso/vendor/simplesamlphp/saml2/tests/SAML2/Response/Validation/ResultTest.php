<?php

class SAML2_Response_Validation_ResultTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @group response-validation
     * @test
     */
    public function added_errors_can_be_retrieved()
    {
        $error = 'This would be an error message';
        $result = new SAML2_Response_Validation_Result();

        $result->addError($error);
        $errors = $result->getErrors();

        $this->assertCount(1, $errors);
        $this->assertEquals($error, $errors[0]);
    }

    /**
     * @group response-validation
     * @test
     *
     * @expectedException SAML2_Exception_InvalidArgumentException
     */
    public function an_exception_is_thrown_when_trying_to_add_an_invalid_error()
    {
        $result = new SAML2_Response_Validation_Result();

        $result->addError(123);
    }

    /**
     * @group response-validation
     * @test
     */
    public function the_result_correctly_reports_whether_or_not_it_is_valid()
    {
        $result = new SAML2_Response_Validation_Result();

        $this->assertTrue($result->isValid());
        $this->assertCount(0, $result->getErrors());

        $result->addError('Oh noooos!');

        $this->assertFalse($result->isValid());
        $this->assertCount(1, $result->getErrors());
    }
}
