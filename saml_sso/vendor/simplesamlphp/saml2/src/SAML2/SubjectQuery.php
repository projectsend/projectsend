<?php

/**
 * Base class for SAML 2 subject query messages.
 *
 * This base class can be used for various requests which ask for
 * information about a particular subject.
 *
 * Note that this class currently only handles the simple case - where the
 * subject doesn't contain any sort of subject confirmation requirements.
 *
 * @package SimpleSAMLphp
 */
abstract class SAML2_SubjectQuery extends SAML2_Request
{
    /**
     * The NameId of the subject in the query.
     *
     * @var array
     */
    private $nameId;


    /**
     * Constructor for SAML 2 subject query messages.
     *
     * @param string          $tagName The tag name of the root element.
     * @param DOMElement|NULL $xml     The input message.
     */
    protected function __construct($tagName, DOMElement $xml = NULL)
    {
        parent::__construct($tagName, $xml);

        if ($xml === NULL) {
            return;
        }

        $this->parseSubject($xml);
    }


    /**
     * Parse subject in query.
     *
     * @param DOMElement $xml The SubjectQuery XML element.
     * @throws Exception
     */
    private function parseSubject(DOMElement $xml)
    {
        $subject = SAML2_Utils::xpQuery($xml, './saml_assertion:Subject');
        if (empty($subject)) {
            /* No Subject node. */
            throw new Exception('Missing subject in subject query.');
        } elseif (count($subject) > 1) {
            throw new Exception('More than one <saml:Subject> in <saml:Assertion>.');
        }
        $subject = $subject[0];

        $nameId = SAML2_Utils::xpQuery($subject, './saml_assertion:NameID');
        if (empty($nameId)) {
            throw new Exception('Missing <saml:NameID> in <saml:Subject>.');
        } elseif (count($nameId) > 1) {
            throw new Exception('More than one <saml:NameID> in <saml:Subject>.');
        }
        $nameId = $nameId[0];
        $this->nameId = SAML2_Utils::parseNameId($nameId);
    }


    /**
     * Retrieve the NameId of the subject in the query.
     *
     * The returned NameId is in the format used by SAML2_Utils::addNameId().
     *
     * @see SAML2_Utils::addNameId()
     * @return array|NULL The name identifier of the assertion.
     */
    public function getNameId()
    {
        return $this->nameId;
    }


    /**
     * Set the NameId of the subject in the query.
     *
     * The NameId must be in the format accepted by SAML2_Utils::addNameId().
     *
     * @see SAML2_Utils::addNameId()
     * @param array|NULL $nameId The name identifier of the assertion.
     */
    public function setNameId($nameId)
    {
        assert('is_array($nameId) || is_null($nameId)');

        $this->nameId = $nameId;
    }


    /**
     * Convert subject query message to an XML element.
     *
     * @return DOMElement This subject query.
     */
    public function toUnsignedXML()
    {
        $root = parent::toUnsignedXML();

        $subject = $root->ownerDocument->createElementNS(SAML2_Const::NS_SAML, 'saml:Subject');
        $root->appendChild($subject);

        SAML2_Utils::addNameId($subject, $this->nameId);

        return $root;
    }

}
