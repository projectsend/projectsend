<?php

class SAML2_Assertion_Transformer_NameIdDecryptionTransformer implements
    SAML2_Assertion_Transformer_Transformer,
    SAML2_Configuration_IdentityProviderAware,
    SAML2_Configuration_ServiceProviderAware
{
    /**
     * @var SAML2_Certificate_PrivateKeyLoader
     */
    private $privateKeyLoader;

    /**
     * @var SAML2_Configuration_IdentityProvider
     */
    private $identityProvider;

    /**
     * @var SAML2_Configuration_ServiceProvider
     */
    private $serviceProvider;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        SAML2_Certificate_PrivateKeyLoader $privateKeyLoader
    ) {
        $this->privateKeyLoader = $privateKeyLoader;
    }

    public function transform(SAML2_Assertion $assertion)
    {
        if (!$assertion->isNameIdEncrypted()) {
            return $assertion;
        }

        $decryptionKeys  = $this->privateKeyLoader->loadDecryptionKeys($this->identityProvider, $this->serviceProvider);
        $blacklistedKeys = $this->identityProvider->getBlacklistedAlgorithms();
        if (is_null($blacklistedKeys)) {
            $blacklistedKeys = $this->serviceProvider->getBlacklistedAlgorithms();
        }

        foreach ($decryptionKeys as $index => $key) {
            try {
                $assertion->decryptNameId($key, $blacklistedKeys);
                $this->logger->debug(sprintf('Decrypted assertion NameId with key "#%d"', $index));
            } catch (Exception $e) {
                $this->logger->debug(sprintf(
                    'Decrypting assertion NameId with key "#%d" failed, "%s" thrown: "%s"',
                    $index,
                    get_class($e),
                    $e->getMessage()
                ));
            }
        }

        if ($assertion->isNameIdEncrypted()) {
            throw new SAML2_Assertion_Exception_NotDecryptedException(
                'Could not decrypt the assertion NameId with the configured keys, see the debug log for information'
            );
        }

        return $assertion;
    }

    public function setIdentityProvider(SAML2_Configuration_IdentityProvider $identityProvider)
    {
        $this->identityProvider = $identityProvider;
    }

    public function setServiceProvider(SAML2_Configuration_ServiceProvider $serviceProvider)
    {
        $this->serviceProvider = $serviceProvider;
    }
}
