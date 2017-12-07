<?php

/**
 * Class for handling the Keywords metadata extensions for login and discovery user interface
 *
 * @link: http://docs.oasis-open.org/security/saml/Post2.0/sstc-saml-metadata-ui/v1.0/sstc-saml-metadata-ui-v1.0.pdf
 * @package SimpleSAMLphp
 */
class SAML2_XML_mdui_Keywords
{
    /**
     * The keywords of this item.
     *
     * @var string
     */
    public $Keywords;

    /**
     * The language of this item.
     *
     * @var string
     */
    public $lang;

    /**
     * Initialize a Keywords.
     *
     * @param DOMElement|NULL $xml The XML element we should load.
     * @throws Exception
     */
    public function __construct(DOMElement $xml = NULL)
    {
        if ($xml === NULL) {
            return;
        }

        if (!$xml->hasAttribute('xml:lang')) {
            throw new Exception('Missing lang on Keywords.');
        }
        if (!is_string($xml->textContent) || !strlen($xml->textContent)) {
            throw new Exception('Missing value for Keywords.');
        }
        $this->Keywords = array();
        foreach (explode(' ', $xml->textContent) as $keyword) {
            $this->Keywords[] = str_replace('+', ' ', $keyword);
        }
        $this->lang = $xml->getAttribute('xml:lang');
    }

    /**
     * Convert this Keywords to XML.
     *
     * @param DOMElement $parent The element we should append this Keywords to.
     * @return DOMElement
     * @throws Exception
     */
    public function toXML(DOMElement $parent)
    {
        assert('is_string($this->lang)');
        assert('is_array($this->Keywords)');

        $doc = $parent->ownerDocument;

        $e = $doc->createElementNS(SAML2_XML_mdui_UIInfo::NS, 'mdui:Keywords');
        $e->setAttribute('xml:lang', $this->lang);
        $value = '';
        foreach ($this->Keywords as $keyword) {
            if (strpos($keyword, "+") !== FALSE) {
                throw new Exception('Keywords may not contain a "+" character.');
            }
            $value .= str_replace(' ', '+', $keyword) . ' ';
        }
        $value = rtrim($value);
        $e->appendChild($doc->createTextNode($value));
        $parent->appendChild($e);

        return $e;
    }

}
