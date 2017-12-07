<?php

/**
 * Class for handling the mdrpi:PublicationInfo element.
 *
 * @link: http://docs.oasis-open.org/security/saml/Post2.0/saml-metadata-rpi/v1.0/saml-metadata-rpi-v1.0.pdf
 * @package SimpleSAMLphp
 */
class SAML2_XML_mdrpi_PublicationInfo
{
    /**
     * The identifier of the metadata publisher.
     *
     * @var string
     */
    public $publisher;

    /**
     * The creation timestamp for the metadata, as a UNIX timestamp.
     *
     * @var int|NULL
     */
    public $creationInstant;

    /**
     * Identifier for this metadata publication.
     *
     * @var string|NULL
     */
    public $publicationId;

    /**
     * Link to usage policy for this metadata.
     *
     * This is an associative array with language=>URL.
     *
     * @var array
     */
    public $UsagePolicy = array();

    /**
     * Create/parse a mdrpi:PublicationInfo element.
     *
     * @param DOMElement|NULL $xml The XML element we should load.
     * @throws Exception
     */
    public function __construct(DOMElement $xml = NULL)
    {
        if ($xml === NULL) {
            return;
        }

        if (!$xml->hasAttribute('publisher')) {
            throw new Exception('Missing required attribute "publisher" in mdrpi:PublicationInfo element.');
        }
        $this->publisher = $xml->getAttribute('publisher');

        if ($xml->hasAttribute('creationInstant')) {
            $this->creationInstant = SAML2_Utils::xsDateTimeToTimestamp($xml->getAttribute('creationInstant'));
        }

        if ($xml->hasAttribute('publicationId')) {
            $this->publicationId = $xml->getAttribute('publicationId');
        }

        $this->UsagePolicy = SAML2_Utils::extractLocalizedStrings($xml, SAML2_XML_mdrpi_Common::NS_MDRPI, 'UsagePolicy');
    }

    /**
     * Convert this element to XML.
     *
     * @param DOMElement $parent The element we should append to.
     * @return DOMElement
     */
    public function toXML(DOMElement $parent)
    {
        assert('is_string($this->publisher)');
        assert('is_int($this->creationInstant) || is_null($this->creationInstant)');
        assert('is_string($this->publicationId) || is_null($this->publicationId)');
        assert('is_array($this->UsagePolicy)');

        $doc = $parent->ownerDocument;

        $e = $doc->createElementNS(SAML2_XML_mdrpi_Common::NS_MDRPI, 'mdrpi:PublicationInfo');
        $parent->appendChild($e);

        $e->setAttribute('publisher', $this->publisher);

        if ($this->creationInstant !== NULL) {
            $e->setAttribute('creationInstant', gmdate('Y-m-d\TH:i:s\Z', $this->creationInstant));
        }

        if ($this->publicationId !== NULL) {
            $e->setAttribute('publicationId', $this->publicationId);
        }

        SAML2_Utils::addStrings($e, SAML2_XML_mdrpi_Common::NS_MDRPI, 'mdrpi:UsagePolicy', TRUE, $this->UsagePolicy);

        return $e;
    }

}
