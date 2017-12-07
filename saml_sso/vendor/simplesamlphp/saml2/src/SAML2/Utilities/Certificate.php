<?php

/**
 * Collection of Utility functions specifically for certificates
 */
class SAML2_Utilities_Certificate
{
    /**
     * The pattern that the contents of a certificate should adhere to
     */
    const CERTIFICATE_PATTERN = '/^-----BEGIN CERTIFICATE-----([^-]*)^-----END CERTIFICATE-----/m';

    /**
     * @param  $certificate
     *
     * @return bool
     */
    public static function hasValidStructure($certificate)
    {
        return !!preg_match(self::CERTIFICATE_PATTERN, $certificate);
    }

    /**
     * @param string $X509CertificateContents
     *
     * @return string
     */
    public static function convertToCertificate($X509CertificateContents)
    {
        return "-----BEGIN CERTIFICATE-----\n"
                . chunk_split($X509CertificateContents, 64)
                . "-----END CERTIFICATE-----\n";
    }
}
