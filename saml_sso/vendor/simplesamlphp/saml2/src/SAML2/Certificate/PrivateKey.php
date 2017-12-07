<?php

class SAML2_Certificate_PrivateKey extends SAML2_Certificate_Key
{
    public static function create($keyContents, $passphrase = NULL)
    {
        if (!is_string($keyContents)) {
            throw SAML2_Exception_InvalidArgumentException::invalidType('string', $keyContents);
        }

        if ($passphrase && !is_string($passphrase)) {
            throw SAML2_Exception_InvalidArgumentException::invalidType('string', $passphrase);
        }

        $keyData = array ('PEM' => $keyContents, self::USAGE_ENCRYPTION => TRUE);
        if ($passphrase) {
            $keyData['passphrase'] = $passphrase;
        }

        return new self($keyData);
    }

    public function getKeyAsString()
    {
        return $this->keyData['PEM'];
    }

    public function getPassphrase()
    {
        return isset($this->keyData['passphrase']) ? $this->keyData['passphrase'] : NULL;
    }
}
