<?php

class SAML2_Response_Validation_Validator
{
    /**
     * @var SAML2_Response_Validation_ConstraintValidator[]
     */
    protected $constraints;

    public function addConstraintValidator(SAML2_Response_Validation_ConstraintValidator $constraint)
    {
        $this->constraints[] = $constraint;
    }

    public function validate(SAML2_Response $response)
    {
        $result = new SAML2_Response_Validation_Result();
        foreach ($this->constraints as $validator) {
            $validator->validate($response, $result);
        }

        return $result;
    }
}
