<?php

/**
 * KeyLoader
 */
class SAML2_Certificate_KeyLoader
{
    /**
     * @var SAML2_Certificate_KeyCollection
     */
    private $loadedKeys;

    public function __construct()
    {
        $this->loadedKeys = new SAML2_Certificate_KeyCollection();
    }

    /**
     * Extracts the public keys given by the configuration. Mainly exists for BC purposes.
     * Prioritisation order is keys > certData > certificate
     *
     * @param SAML2_Configuration_CertificateProvider $config
     * @param null                                    $usage
     * @param bool                                    $required
     * @param string                                  $prefix
     *
     * @return SAML2_Certificate_KeyCollection
     */
    public static function extractPublicKeys(
        SAML2_Configuration_CertificateProvider $config,
        $usage = NULL,
        $required = FALSE,
        $prefix = ''
    ) {
        $keyLoader = new self();

        return $keyLoader->loadKeysFromConfiguration($config, $usage, $required, $prefix, $keyLoader);
    }

    /**
     * @param SAML2_Configuration_CertificateProvider $config
     * @param NULL|string                             $usage
     * @param bool                                    $required
     *
     * @return SAML2_Certificate_KeyCollection
     */
    public function loadKeysFromConfiguration(
        SAML2_Configuration_CertificateProvider $config,
        $usage = NULL,
        $required = FALSE
    ) {
        $keys = $config->getKeys();
        $certificateData = $config->getCertificateData();
        $certificateFile = $config->getCertificateFile();

        if ($keys) {
            $this->loadKeys($keys, $usage);
        } elseif ($certificateData) {
            $this->loadCertificateData($certificateData);
        } elseif ($certificateFile) {
            $this->loadCertificateFile($certificateFile);
        }

        if ($required && !$this->hasKeys()) {
            throw new SAML2_Certificate_Exception_NoKeysFoundException(
                'No keys found in configured metadata, please ensure that either the "keys", "certData" or '
                . '"certificate" entries is available.'
            );
        }

        return $this->getKeys();
    }

    /**
     * Loads the keys given, optionally excluding keys when a usage is given and they
     * are not configured to be used with the usage given
     *
     * @param array $configuredKeys
     * @param       $usage
     */
    public function loadKeys(array $configuredKeys, $usage)
    {
        foreach ($configuredKeys as $keyData) {
            if (isset($key['X509Certificate'])) {
                $key = new SAML2_Certificate_X509($keyData);
            } else {
                $key = new SAML2_Certificate_Key($keyData);
            }

            if ($usage && !$key->canBeUsedFor($usage)) {
                continue;
            }

            $this->loadedKeys->add($key);
        }
    }

    /**
     * Attempts to load a key based on the given certificateData
     *
     * @param string $certificateData
     */
    public function loadCertificateData($certificateData)
    {
        if (!is_string($certificateData)) {
            throw SAML2_Exception_InvalidArgumentException::invalidType('string', $certificateData);
        }

        $this->loadedKeys->add(SAML2_Certificate_X509::createFromCertificateData($certificateData));
    }

    /**
     * Loads the certificate in the file given
     *
     * @param string $certificateFile the full path to the cert file.
     */
    public function loadCertificateFile($certificateFile)
    {
        $certificate = SAML2_Utilities_File::getFileContents($certificateFile);

        if (!SAML2_Utilities_Certificate::hasValidStructure($certificate)) {
            throw new SAML2_Certificate_Exception_InvalidCertificateStructureException(sprintf(
                'Could not find PEM encoded certificate in "%s"',
                $certificateFile
            ));
        }

        // capture the certificate contents without the delimiters
        preg_match(SAML2_Utilities_Certificate::CERTIFICATE_PATTERN, $certificate, $matches);
        $this->loadedKeys->add(SAML2_Certificate_X509::createFromCertificateData($matches[1]));
    }

    /**
     * @return SAML2_Certificate_KeyCollection
     */
    public function getKeys()
    {
        return $this->loadedKeys;
    }

    /**
     * @return bool
     */
    public function hasKeys()
    {
        return !!count($this->loadedKeys);
    }
}
