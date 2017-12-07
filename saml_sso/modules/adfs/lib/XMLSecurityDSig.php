<?php

/**
 * This class should be considered a temporary workaround to
 * solve the lack of custom formatting in XMLSecurityDSig
 * (xmlseclibs). It should be possible to either configure
 * the original class to avoid formatting, or to use a custom
 * template for the signature.
 *
 * @todo Move this functionality to xmlseclibs.
 *
 * @author Daniel Tsosie
 * @package SimpleSAMLphp
 */
class sspmod_adfs_XMLSecurityDSig extends XMLSecurityDSig {

    function __construct($metaxml) {
        $template = '';

        if (strpos("\n", $metaxml) === FALSE) {
            foreach (explode("\n", self::template) as $line)
                $template .= trim($line);
        } else {
            $template = self::template;
        }

        $sigdoc = SAML2_DOMDocumentFactory::fromString($template);
        $this->sigNode = $sigdoc->documentElement;
    }
}
