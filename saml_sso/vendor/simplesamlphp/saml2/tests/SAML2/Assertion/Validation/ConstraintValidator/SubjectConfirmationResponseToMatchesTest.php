<?php

use \Mockery as m;

class SAML2_Assertion_Validation_ConstraintValidator_SubjectConfirmationResponseToMatchesTest extends
    \PHPUnit_Framework_TestCase
{
    /**
     * @var \Mockery\MockInterface
     */
    private $subjectConfirmation;

    /**
     * @var \Mockery\MockInterface
     */
    private $subjectConfirmationData;

    /**
     * @var \Mockery\MockInterface
     */
    private $response;

    public function setUp()
    {
        parent::setUp();
        $this->subjectConfirmation                          = m::mock('SAML2_XML_saml_SubjectConfirmation');
        $this->subjectConfirmationData                      = m::mock('SAML2_XML_saml_SubjectConfirmationData');
        $this->subjectConfirmation->SubjectConfirmationData = $this->subjectConfirmationData;
        $this->response                                     = m::mock('SAML2_Response');
    }

    /**
     * @group assertion-validation
     * @test
     */
    public function when_the_response_responseto_is_null_the_subject_confirmation_is_valid()
    {
        $this->response->shouldReceive('getInResponseTo')->andReturnNull();
        $this->subjectConfirmationData->InResponseTo = 'someValue';

        $validator = new SAML2_Assertion_Validation_ConstraintValidator_SubjectConfirmationResponseToMatches(
            $this->response
        );
        $result    = new SAML2_Assertion_Validation_Result();

        $validator->validate($this->subjectConfirmation, $result);

        $this->assertTrue($result->isValid());
    }

    /**
     * @group assertion-validation
     * @test
     */
    public function when_the_subjectconfirmation_responseto_is_null_the_subjectconfirmation_is_valid()
    {
        $this->response->shouldReceive('getInResponseTo')->andReturn('someValue');
        $this->subjectConfirmationData->InResponseTo = null;

        $validator = new SAML2_Assertion_Validation_ConstraintValidator_SubjectConfirmationResponseToMatches(
            $this->response
        );
        $result    = new SAML2_Assertion_Validation_Result();

        $validator->validate($this->subjectConfirmation, $result);

        $this->assertTrue($result->isValid());
    }

    /**
     * @group assertion-validation
     * @test
     */
    public function when_the_subjectconfirmation_and_response_responseto_are_null_the_subjectconfirmation_is_valid()
    {
        $this->response->shouldReceive('getInResponseTo')->andReturnNull();
        $this->subjectConfirmationData->InResponseTo = null;

        $validator = new SAML2_Assertion_Validation_ConstraintValidator_SubjectConfirmationResponseToMatches(
            $this->response
        );
        $result    = new SAML2_Assertion_Validation_Result();

        $validator->validate($this->subjectConfirmation, $result);

        $this->assertTrue($result->isValid());
    }

    /**
     * @group assertion-validation
     * @test
     */
    public function when_the_subjectconfirmation_and_response_responseto_are_equal_the_subjectconfirmation_is_valid()
    {
        $this->response->shouldReceive('getInResponseTo')->andReturn('theSameValue');
        $this->subjectConfirmationData->InResponseTo = 'theSameValue';

        $validator = new SAML2_Assertion_Validation_ConstraintValidator_SubjectConfirmationResponseToMatches(
            $this->response
        );
        $result    = new SAML2_Assertion_Validation_Result();

        $validator->validate($this->subjectConfirmation, $result);

        $this->assertTrue($result->isValid());
    }

    /**
     * @group assertion-validation
     * @test
     */
    public function when_the_subjectconfirmation_and_response_responseto_differ_the_subjectconfirmation_is_invalid()
    {
        $this->response->shouldReceive('getInResponseTo')->andReturn('someValue');
        $this->subjectConfirmationData->InResponseTo = 'anotherValue';

        $validator = new SAML2_Assertion_Validation_ConstraintValidator_SubjectConfirmationResponseToMatches(
            $this->response
        );
        $result    = new SAML2_Assertion_Validation_Result();

        $validator->validate($this->subjectConfirmation, $result);

        $this->assertFalse($result->isValid());
        $this->assertCount(1, $result->getErrors());
    }
}
