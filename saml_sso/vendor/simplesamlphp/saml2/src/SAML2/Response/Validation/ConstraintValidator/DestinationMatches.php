<?php

class SAML2_Response_Validation_ConstraintValidator_DestinationMatches implements
    SAML2_Response_Validation_ConstraintValidator
{
    /**
     * @var SAML2_Configuration_Destination
     */
    private $expectedDestination;

    public function __construct(SAML2_Configuration_Destination $destination)
    {
        $this->expectedDestination = $destination;
    }

    public function validate(SAML2_Response $response, SAML2_Response_Validation_Result $result)
    {
        $destination = $response->getDestination();
        if (!$this->expectedDestination->equals(new SAML2_Configuration_Destination($destination))) {
            $result->addError(sprintf(
                'Destination in response "%s" does not match the expected destination "%s"',
                $destination,
                $this->expectedDestination
            ));
        }
    }
}
