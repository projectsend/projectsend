<?php

/**
 * Simple DTO wrapper for (X509) keys. Implements ArrayAccess
 * for easier backwards compatibility.
 */
class SAML2_Certificate_Key implements ArrayAccess
{
    // Possible key usages
    const USAGE_SIGNING = 'signing';
    const USAGE_ENCRYPTION = 'encryption';

    /**
     * @var array
     */
    protected $keyData = array();

    /**
     * @param array $keyData
     */
    public function __construct(array $keyData)
    {
        // forcing usage of offsetSet
        foreach ($keyData as $property => $value) {
            $this->offsetSet($property, $value);
        }
    }

    /**
     * Whether or not the key is configured to be used for usage given
     *
     * @param  string $usage
     * @return bool
     */
    public function canBeUsedFor($usage)
    {
        if (!in_array($usage, static::getValidKeyUsages())) {
            throw new SAML2_Certificate_Exception_InvalidKeyUsageException($usage);
        }

        return isset($this->keyData[$usage]) && $this->keyData[$usage];
    }

    /**
     * Returns the list of valid key usage options
     * @return array
     */
    public static function getValidKeyUsages()
    {
        return array(
            self::USAGE_ENCRYPTION,
            self::USAGE_SIGNING
        );
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->keyData);
    }

    public function offsetGet($offset)
    {
        $this->assertIsString($offset);

        return $this->keyData[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->assertIsString($offset);

        $this->keyData[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        $this->assertIsString($offset);

        unset($this->keyData[$offset]);
    }

    /**
     * Asserts that the parameter is of type string
     * @param mixed $test
     *
     * @throws Exception
     */
    protected function assertIsString($test)
    {
        if (!is_string($test)) {
            throw SAML2_Exception_InvalidArgumentException::invalidType('string', $test);
        }
    }
}
