<?php

/**
 * Class for handling SAML2 extensions.
 *
 * @package SimpleSAMLphp
 */
class SAML2_XML_samlp_Extensions
{
    /**
     * Get a list of Extensions in the given element.
     *
     * @param  DOMElement $parent The element that may contain the samlp:Extensions element.
     * @return array      Array of extensions.
     */
    public static function getList(DOMElement $parent)
    {
        $ret = array();
        foreach (SAML2_Utils::xpQuery($parent, './saml_protocol:Extensions/*') as $node) {
            $ret[] = new SAML2_XML_Chunk($node);
        }

        return $ret;
    }

    /**
     * Add a list of Extensions to the given element.
     *
     * @param DOMElement        $parent     The element we should add the extensions to.
     * @param SAML2_XML_Chunk[] $extensions List of extension objects.
     */
    public static function addList(DOMElement $parent, array $extensions)
    {
        if (empty($extensions)) {
            return;
        }

        $extElement = $parent->ownerDocument->createElementNS(SAML2_Const::NS_SAMLP, 'samlp:Extensions');
        $parent->appendChild($extElement);

        foreach ($extensions as $ext) {
            $ext->toXML($extElement);
        }
    }

}
