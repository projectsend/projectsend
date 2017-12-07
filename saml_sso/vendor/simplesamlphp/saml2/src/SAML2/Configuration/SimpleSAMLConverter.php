<?php

/**
 * Backwards compatibility helper for SimpleSAMLphp
 */
class SAML2_Configuration_SimpleSAMLConverter
{
    /**
     * @param SimpleSAML_Configuration $configuration
     * @param string                   $certificatePrefix
     *
     * @return SAML2_Configuration_IdentityProvider
     */
    public static function convertToIdentityProvider(
        SimpleSAML_Configuration $configuration,
        $certificatePrefix = ''
    ) {
        $pluckedConfiguration = static::pluckConfiguration($configuration, $certificatePrefix);
        static::enrichForDecryptionProvider($configuration, $pluckedConfiguration);
        static::enrichForIdentityProvider($configuration, $pluckedConfiguration);

        return new SAML2_Configuration_IdentityProvider($pluckedConfiguration);
    }

    /**
     * @param SimpleSAML_Configuration $configuration
     * @param string                   $certificatePrefix
     *
     * @return SAML2_Configuration_ServiceProvider
     */
    public static function convertToServiceProvider(
        SimpleSAML_Configuration $configuration,
        $certificatePrefix = ''
    ) {
        $pluckedConfiguration = static::pluckConfiguration($configuration, $certificatePrefix);
        static::enrichForServiceProvider($configuration, $pluckedConfiguration);
        static::enrichForDecryptionProvider($configuration, $pluckedConfiguration);

        return new SAML2_Configuration_ServiceProvider($pluckedConfiguration);
    }

    /**
     * @param SimpleSAML_Configuration $configuration
     * @param string                   $prefix
     *
     * @return array
     */
    private static function pluckConfiguration(SimpleSAML_Configuration $configuration, $prefix = '')
    {
        $extracted = array();

        // ported from
        // https://github.com/simplesamlphp/simplesamlphp/blob/3d735912342767d391297cc5e13272a76730aca0/lib/SimpleSAML/Configuration.php#L1092
        if ($configuration->hasValue($prefix . 'keys')) {
            $extracted['keys'] = $configuration->getArray($prefix . 'keys');
        }

        // ported from
        // https://github.com/simplesamlphp/simplesamlphp/blob/3d735912342767d391297cc5e13272a76730aca0/lib/SimpleSAML/Configuration.php#L1108
        if ($configuration->hasValue($prefix . 'certData')) {
            $extracted['certificateData'] = $configuration->getString($prefix . 'certData');
        }

        // ported from
        // https://github.com/simplesamlphp/simplesamlphp/blob/3d735912342767d391297cc5e13272a76730aca0/lib/SimpleSAML/Configuration.php#L1119
        if ($configuration->hasValue($prefix . 'certificate')) {
            $extracted['certificateData'] = $configuration->getString($prefix . 'certificate');
        }

        // ported from
        // https://github.com/simplesamlphp/simplesamlphp/blob/3d735912342767d391297cc5e13272a76730aca0/modules/saml/lib/Message.php#L161
        if ($configuration->hasValue($prefix . 'certFingerprint')) {
            $extracted['certificateFingerprint'] = $configuration->getArrayizeString('certFingerprint');
        }

        $extracted['assertionEncryptionEnabled'] = $configuration->getBoolean('assertion.encryption', FALSE);

        if ($configuration->has('sharedKey')) {
            $extracted['sharedKey'] = $configuration->getString('sharedKey');
        }

        return $extracted;
    }

    private static function enrichForIdentityProvider(SimpleSAML_Configuration $configuration, &$baseConfiguration)
    {
        $baseConfiguration['base64EncodedAttributes'] = $configuration->getBoolean('base64attributes', FALSE);
        $baseConfiguration['entityId'] = $configuration->getString('entityid');
    }

    private static function enrichForServiceProvider(SimpleSAML_Configuration $configuration, &$baseConfiguration)
    {
        $baseConfiguration['entityId'] = $configuration->getString('entityid');
    }

    private static function enrichForDecryptionProvider(
        SimpleSAML_Configuration $configuration,
        array &$baseConfiguration
    ) {
        if ($configuration->has('sharedKey')) {
            $baseConfiguration['sharedKey'] = $configuration->getString('sharedKey', NULL);
        }

        if ($configuration->has('new_privatekey')) {
            $baseConfiguration['privateKeys'][] = new SAML2_Configuration_PrivateKey(
                $configuration->getString('new_privatekey'),
                SAML2_Configuration_PrivateKey::NAME_NEW,
                $configuration->getString('new_privatekey_pass', NULL)
            );
        }

        if ($configuration->getBoolean('assertion.encryption', FALSE)) {
            $baseConfiguration['privateKeys'][] = new SAML2_Configuration_PrivateKey(
                $configuration->getString('privatekey'),
                SAML2_Configuration_PrivateKey::NAME_DEFAULT,
                $configuration->getString('privatekey_pass', NULL)
            );

            if ($configuration->has('encryption.blacklisted-algorithms')) {
                $baseConfiguration['blacklistedEncryptionAlgorithms'] = $configuration
                    ->get('encryption.blacklisted-algorithms');
            }
        }
    }
}
