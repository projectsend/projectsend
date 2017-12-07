<?php

/**
 * Class for handling SAML2 metadata extensions.
 *
 * @package SimpleSAMLphp
 */
class SAML2_XML_md_Extensions
{
    /**
     * Get a list of Extensions in the given element.
     *
     * @param  DOMElement $parent The element that may contain the md:Extensions element.
     * @return SAML2_XML_Chunk[]  Array of extensions.
     */
    public static function getList(DOMElement $parent)
    {
        $ret = array();
        foreach (SAML2_Utils::xpQuery($parent, './saml_metadata:Extensions/*') as $node) {
            if ($node->namespaceURI === SAML2_XML_shibmd_Scope::NS && $node->localName === 'Scope') {
                $ret[] = new SAML2_XML_shibmd_Scope($node);
            } elseif ($node->namespaceURI === SAML2_XML_mdattr_EntityAttributes::NS && $node->localName === 'EntityAttributes') {
                $ret[] = new SAML2_XML_mdattr_EntityAttributes($node);
            } elseif ($node->namespaceURI === SAML2_XML_mdrpi_Common::NS_MDRPI && $node->localName === 'RegistrationInfo') {
                $ret[] = new SAML2_XML_mdrpi_RegistrationInfo($node);
            } elseif ($node->namespaceURI === SAML2_XML_mdrpi_Common::NS_MDRPI && $node->localName === 'PublicationInfo') {
                $ret[] = new SAML2_XML_mdrpi_PublicationInfo($node);
            } elseif ($node->namespaceURI === SAML2_XML_mdui_UIInfo::NS && $node->localName === 'UIInfo') {
                $ret[] = new SAML2_XML_mdui_UIInfo($node);
            } elseif ($node->namespaceURI === SAML2_XML_mdui_DiscoHints::NS && $node->localName === 'DiscoHints') {
                $ret[] = new SAML2_XML_mdui_DiscoHints($node);
            } else {
                $ret[] = new SAML2_XML_Chunk($node);
            }
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

        $extElement = $parent->ownerDocument->createElementNS(SAML2_Const::NS_MD, 'md:Extensions');
        $parent->appendChild($extElement);

        foreach ($extensions as $ext) {
            $ext->toXML($extElement);
        }
    }

}
