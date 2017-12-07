<?php

/**
 * Value Object representing the current destination
 */
class SAML2_Configuration_Destination
{
    /**
     * @var string
     */
    private $destination;

    /**
     * @param string $destination
     */
    public function __construct($destination)
    {
        if (!is_string($destination)) {
            throw SAML2_Exception_InvalidArgumentException::invalidType('string', $destination);
        }

        $this->destination = $destination;
    }

    /**
     * @param SAML2_Configuration_Destination $otherDestination
     *
     * @return bool
     */
    public function equals(SAML2_Configuration_Destination $otherDestination)
    {
        return $this->destination === $otherDestination->destination;
    }

    public function __toString()
    {
        return $this->destination;
    }
}
