<?php

use \Mockery as m;

/**
 * Because we're mocking a static call, we have to run it in separate processes so as to no contaminate the other
 * tests.
 *
 * @runTestsInSeparateProcesses
 */
class SAML2_Assertion_Validation_ConstraintValidator_SessionNotOnOrAfterTest extends SAML2_ControlledTimeTest
{
    /**
     * @var \Mockery\MockInterface
     */
    private $assertion;

    public function setUp()
    {
        parent::setUp();
        $this->assertion = m::mock('SAML2_Assertion');
    }

    /**
     * @group assertion-validation
     * @test
     */
    public function timestamp_in_the_past_before_graceperiod_is_not_valid()
    {
        $this->assertion->shouldReceive('getSessionNotOnOrAfter')->andReturn($this->currentTime - 60);

        $validator = new SAML2_Assertion_Validation_ConstraintValidator_SessionNotOnOrAfter();
        $result    = new SAML2_Assertion_Validation_Result();

        $validator->validate($this->assertion, $result);

        $this->assertFalse($result->isValid());
        $this->assertCount(1, $result->getErrors());
    }

    /**
     * @group assertion-validation
     * @test
     */
    public function time_within_graceperiod_is_valid()
    {
        $this->assertion->shouldReceive('getSessionNotOnOrAfter')->andReturn($this->currentTime - 59);

        $validator = new SAML2_Assertion_Validation_ConstraintValidator_SessionNotOnOrAfter();
        $result    = new SAML2_Assertion_Validation_Result();

        $validator->validate($this->assertion, $result);

        $this->assertTrue($result->isValid());
    }

    /**
     * @group assertion-validation
     * @test
     */
    public function current_time_is_valid()
    {
        $this->assertion->shouldReceive('getSessionNotOnOrAfter')->andReturn($this->currentTime);

        $validator = new SAML2_Assertion_Validation_ConstraintValidator_SessionNotOnOrAfter();
        $result    = new SAML2_Assertion_Validation_Result();

        $validator->validate($this->assertion, $result);

        $this->assertTrue($result->isValid());
    }
}
