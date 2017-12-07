<?php


class SAML2_XML_md_RoleDescriptorMock extends SAML2_XML_md_RoleDescriptor {
    public function __construct(DOMElement $xml = NULL) {
        parent::__construct('md:RoleDescriptor', $xml);
    }

    public function toXML(DOMElement $parent) {
        $xml = parent::toXML($parent);
        $xml->setAttributeNS(SAML2_Const::NS_XSI, 'xsi:type', 'myns:MyElement');
        $xml->setAttributeNS('http://example.org/mynsdefinition', 'myns:tmp', 'tmp');
        $xml->removeAttributeNS('http://example.org/mynsdefinition', 'tmp');
        return $xml;
    }
}
