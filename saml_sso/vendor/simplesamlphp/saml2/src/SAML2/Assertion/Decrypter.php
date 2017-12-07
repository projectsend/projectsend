<?php

class SAML2_Assertion_Decrypter
{
    /**
     * @var SAML2_Configuration_IdentityProvider
     */
    private $identityProvider;

    /**
     * @var SAML2_Configuration_ServiceProvider
     */
    private $serviceProvider;

    /**
     * @var SAML2_Certificate_PrivateKeyLoader
     */
    private $privateKeyLoader;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        SAML2_Configuration_IdentityProvider $identityProvider,
        SAML2_Configuration_ServiceProvider $serviceProvider,
        SAML2_Certificate_PrivateKeyLoader $privateKeyLoader
    ) {
        $this->logger = $logger;
        $this->identityProvider = $identityProvider;
        $this->serviceProvider = $serviceProvider;
        $this->privateKeyLoader = $privateKeyLoader;
    }

    /**
     * Allows for checking whether either the SP or the IdP requires assertion encryption
     */
    public function isEncryptionRequired()
    {
        return $this->identityProvider->isAssertionEncryptionRequired()
            || $this->serviceProvider->isAssertionEncryptionRequired();
    }

    /**
     * @param SAML2_EncryptedAssertion $assertion
     *
     * @return SAML2_Assertion
     */
    public function decrypt(SAML2_EncryptedAssertion $assertion)
    {
        $decryptionKeys = $this->privateKeyLoader->loadDecryptionKeys($this->identityProvider, $this->serviceProvider);
        $blacklistedKeys = $this->identityProvider->getBlacklistedAlgorithms();
        if (is_null($blacklistedKeys)) {
            $blacklistedKeys = $this->serviceProvider->getBlacklistedAlgorithms();
        }

        // reflects the simplesamlphp behaviour for BC, see
        // https://github.com/simplesamlphp/simplesamlphp/blob/3d735912342767d391297cc5e13272a76730aca0/modules/saml/lib/Message.php#L369
        foreach ($decryptionKeys as $index => $key) {
            try {
                $decryptedAssertion = $assertion->getAssertion($key, $blacklistedKeys);
                $this->logger->debug(sprintf('Decrypted Assertion with key "#%d"', $index));

                return $decryptedAssertion;

            } catch (Exception $e) {
                $this->logger->debug(sprintf(
                    'Could not decrypt assertion with key "#%d", "%s" thrown: "%s"',
                    $index,
                    get_class($e),
                    $e->getMessage()
                ));
            }
        }

        throw new SAML2_Assertion_Exception_NotDecryptedException(sprintf(
            'Could not decrypt the assertion, tried with "%d" keys. See the debug log for more information',
            count($decryptionKeys)
        ));
    }
}
