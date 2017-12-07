<?php

use \Mockery as m;

/**
 * Because we're mocking a static call, we have to run it in separate processes so as to no contaminate the other
 * tests.
 *
 * @runTestsInSeparateProcesses
 */
class SAML2_Assertion_Validation_ConstraintValidator_NotBeforeTest extends SAML2_ControlledTimeTest
{
    /**
     * @var \Mockery\MockInterface
     */
    private $assertion;

    /**
     * @var int
     */
    protected $currentTime = 1;

    public function setUp()
    {
        parent::setUp();
        $this->assertion = m::mock('SAML2_Assertion');
    }

    /**
     * @group assertion-validation
     * @test
     */
    public function timestamp_in_the_future_beyond_graceperiod_is_not_valid()
    {
        $this->assertion->shouldReceive('getNotBefore')->andReturn($this->currentTime + 61);

        $validator = new SAML2_Assertion_Validation_ConstraintValidator_NotBefore();
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
        $this->assertion->shouldReceive('getNotBefore')->andReturn($this->currentTime + 60);

        $validator = new SAML2_Assertion_Validation_ConstraintValidator_NotBefore();
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
        $this->assertion->shouldReceive('getNotBefore')->andReturn($this->currentTime);

        $validator = new SAML2_Assertion_Validation_ConstraintValidator_NotBefore();
        $result    = new SAML2_Assertion_Validation_Result();

        $validator->validate($this->assertion, $result);

        $this->assertTrue($result->isValid());
    }
}
