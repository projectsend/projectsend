<?php
/**
 * The Artifact is part of the SAML 2.0 IdP code, and it builds an artifact object.
 * I am using strings, because I find them easier to work with.
 * I want to use this, to be consistent with the other saml2_requests
 *
 * @author Danny Bollaert, UGent AS. <danny.bollaert@ugent.be>
 * @package SimpleSAMLphp
 */
class SAML2_ArtifactResolve extends SAML2_Request
{
    private $artifact;

    public function __construct(DOMElement $xml = NULL)
    {
        parent::__construct('ArtifactResolve', $xml);

        if (!is_null($xml)) {
            $results = SAML2_Utils::xpQuery($xml, './saml_protocol:Artifact');
            $this->artifact = $results[0]->textContent;
        }

    }

    /**
     * Retrieve the Artifact in this response.
     *
     * @return string artifact.
     */
    public function getArtifact()
    {
        return $this->artifact;
    }

    /**
     * Set the artifact that should be included in this response.
     *
     * @param string $artifact
     */
    public function setArtifact($artifact)
    {
        assert('is_string($artifact)');
        $this->artifact = $artifact;
    }

    /**
     * Convert the response message to an XML element.
     *
     * @return DOMElement This response.
     */
    public function toUnsignedXML()
    {
        $root = parent::toUnsignedXML();
        $artifactelement = $this->document->createElementNS(SAML2_Const::NS_SAMLP, 'Artifact', $this->artifact);
        $root->appendChild($artifactelement);

        return $root;
    }

}
