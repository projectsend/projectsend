<?php

/**
 * Default implementation for configuration
 */
class SAML2_Configuration_ArrayAdapter implements SAML2_Configuration_Queryable
{
    /**
     * @var array
     */
    private $configuration;

    /**
     * @param array $configuration
     */
    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
    }

    public function get($key, $defaultValue = NULL)
    {
        if (!$this->has($key)) {
            return $defaultValue;
        }

        return $this->configuration[$key];
    }

    public function has($key)
    {
        return array_key_exists($key, $this->configuration);
    }
}
