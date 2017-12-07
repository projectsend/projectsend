<?php

use \Mockery as m;

class SAML2_Assertion_Validation_ConstraintValidator_SubjectConfirmationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Mockery\MockInterface
     */
    private $subjectConfirmation;

    public function setUp()
    {
        $this->subjectConfirmation = m::mock('SAML2_XML_saml_SubjectConfirmation');
    }

    /**
     * @group assertion-validation
     * @test
     */
    public function a_subject_confirmation_with_bearer_method_is_valid()
    {
        $this->subjectConfirmation->Method = SAML2_Const::CM_BEARER;

        $validator = new SAML2_Assertion_Validation_ConstraintValidator_SubjectConfirmationMethod();
        $result = new SAML2_Assertion_Validation_Result();

        $validator->validate($this->subjectConfirmation, $result);

        $this->assertTrue($result->isValid());
    }

    /**
     * @group assertion-validation
     * @test
     */
    public function a_subject_confirmation_with_holder_of_key_method_is_not_valid()
    {
        $this->subjectConfirmation->Method = SAML2_Const::CM_HOK;

        $validator = new SAML2_Assertion_Validation_ConstraintValidator_SubjectConfirmationMethod();
        $result    = new SAML2_Assertion_Validation_Result();

        $validator->validate($this->subjectConfirmation, $result);

        $this->assertFalse($result->isValid());
        $this->assertCount(1, $result->getErrors());
    }
}
