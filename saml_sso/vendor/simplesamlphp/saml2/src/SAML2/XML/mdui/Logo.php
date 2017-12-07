<?php

/**
 * Class for handling the Logo metadata extensions for login and discovery user interface
 *
 * @link: http://docs.oasis-open.org/security/saml/Post2.0/sstc-saml-metadata-ui/v1.0/sstc-saml-metadata-ui-v1.0.pdf
 * @package SimpleSAMLphp
 */
class SAML2_XML_mdui_Logo
{
    /**
     * The url of this logo.
     *
     * @var string
     */
    public $url;

    /**
     * The width of this logo.
     *
     * @var string
     */
    public $width;

    /**
     * The height of this logo.
     *
     * @var string
     */
    public $height;

    /**
     * The language of this item.
     *
     * @var string
     */
    public $lang;

    /**
     * Initialize a Logo.
     *
     * @param DOMElement|NULL $xml The XML element we should load.
     * @throws Exception
     */
    public function __construct(DOMElement $xml = NULL)
    {
        if ($xml === NULL) {
            return;
        }

        if (!$xml->hasAttribute('width')) {
            throw new Exception('Missing width of Logo.');
        }
        if (!$xml->hasAttribute('height')) {
            throw new Exception('Missing height of Logo.');
        }
        if (!is_string($xml->textContent) || !strlen($xml->textContent)) {
            throw new Exception('Missing url value for Logo.');
        }
        $this->url = $xml->textContent;
        $this->width = (int) $xml->getAttribute('width');
        $this->height = (int) $xml->getAttribute('height');
        $this->lang = $xml->hasAttribute('xml:lang') ? $xml->getAttribute('xml:lang') : NULL;
    }

    /**
     * Convert this Logo to XML.
     *
     * @param DOMElement $parent The element we should append this Logo to.
     * @return DOMElement
     */
    public function toXML(DOMElement $parent)
    {
        assert('is_int($this->width)');
        assert('is_int($this->height)');
        assert('is_string($this->url)');

        $doc = $parent->ownerDocument;

        $e = $doc->createElementNS(SAML2_XML_mdui_UIInfo::NS, 'mdui:Logo');
        $e->appendChild($doc->createTextNode($this->url));
        $e->setAttribute('width', (int) $this->width);
        $e->setAttribute('height', (int) $this->height);
        if (isset($this->lang)) {
            $e->setAttribute('xml:lang', $this->lang);
        }
        $parent->appendChild($e);

        return $e;
    }

}
