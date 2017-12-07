<?php

use \Mockery as m;

/**
 * Because we're mocking a static call, we have to run it in separate processes so as to no contaminate the other
 * tests.
 */
class SAML2_Assertion_Validation_ConstraintValidator_SpIsValidAudienceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Mockery\MockInterface
     */
    private $assertion;

    /**
     * @var \Mockery\MockInterface
     */
    private $serviceProvider;

    public function setUp()
    {
        parent::setUp();
        $this->assertion = m::mock('SAML2_Assertion');
        $this->serviceProvider = m::mock('SAML2_Configuration_ServiceProvider');
    }

    /**
     * @group assertion-validation
     * @test
     */
    public function when_no_valid_adiences_are_given_the_assertion_is_valid()
    {
        $this->assertion->shouldReceive('getValidAudiences')->andReturn(null);
        $this->serviceProvider->shouldReceive('getEntityId')->andReturn('entityId');

        $validator = new SAML2_Assertion_Validation_ConstraintValidator_SpIsValidAudience();
        $validator->setServiceProvider($this->serviceProvider);
        $result    = new SAML2_Assertion_Validation_Result();

        $validator->validate($this->assertion, $result);

        $this->assertTrue($result->isValid());
    }

    /**
     * @group assertion-validation
     * @test
     */
    public function if_the_sp_entity_id_is_not_in_the_valid_audiences_the_assertion_is_invalid()
    {
        $this->assertion->shouldReceive('getValidAudiences')->andReturn(array('someEntityId'));
        $this->serviceProvider->shouldReceive('getEntityId')->andReturn('anotherEntityId');

        $validator = new SAML2_Assertion_Validation_ConstraintValidator_SpIsValidAudience();
        $validator->setServiceProvider($this->serviceProvider);
        $result    = new SAML2_Assertion_Validation_Result();

        $validator->validate($this->assertion, $result);

        $this->assertFalse($result->isValid());
        $this->assertCount(1, $result->getErrors());
    }

    /**
     * @group assertion-validation
     * @test
     */
    public function the_assertion_is_valid_when_the_current_sp_entity_id_is_a_valid_audience()
    {
        $this->assertion->shouldReceive('getValidAudiences')->andReturn(array('foo', 'bar', 'validEntityId', 'baz'));
        $this->serviceProvider->shouldReceive('getEntityId')->andReturn('validEntityId');

        $validator = new SAML2_Assertion_Validation_ConstraintValidator_SpIsValidAudience();
        $validator->setServiceProvider($this->serviceProvider);
        $result    = new SAML2_Assertion_Validation_Result();

        $validator->validate($this->assertion, $result);

        $this->assertTrue($result->isValid());
    }
}
