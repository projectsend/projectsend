<?php

/**
 * Base class for all SAML 2 response messages.
 *
 * Implements samlp:StatusResponseType. All of the elements in that type is
 * stored in the SAML2_Message class, and this class is therefore more
 * or less empty. It is included mainly to make it easy to separate requests from
 * responses.
 *
 * The status code is represented as an array on the following form:
 * array(
 *   'Code' => '<top-level status code>',
 *   'SubCode' => '<second-level status code>',
 *   'Message' => '<status message>',
 * )
 *
 * Only the 'Code' field is required. The others will be set to NULL if they
 * aren't present.
 *
 * @package SimpleSAMLphp
 */
abstract class SAML2_StatusResponse extends SAML2_Message
{
    /**
     * The ID of the request this is a response to, or NULL if this is an unsolicited response.
     *
     * @var string|NULL
     */
    private $inResponseTo;


    /**
     * The status code of the response.
     *
     * @var array
     */
    private $status;


    /**
     * Constructor for SAML 2 response messages.
     *
     * @param string          $tagName The tag name of the root element.
     * @param DOMElement|NULL $xml     The input message.
     * @throws Exception
     */
    protected function __construct($tagName, DOMElement $xml = NULL)
    {
        parent::__construct($tagName, $xml);

        $this->status = array(
            'Code' => SAML2_Const::STATUS_SUCCESS,
            'SubCode' => NULL,
            'Message' => NULL,
            );

        if ($xml === NULL) {
            return;
        }

        if ($xml->hasAttribute('InResponseTo')) {
            $this->inResponseTo = $xml->getAttribute('InResponseTo');
        }

        $status = SAML2_Utils::xpQuery($xml, './saml_protocol:Status');
        if (empty($status)) {
            throw new Exception('Missing status code on response.');
        }
        $status = $status[0];

        $statusCode = SAML2_Utils::xpQuery($status, './saml_protocol:StatusCode');
        if (empty($statusCode)) {
            throw new Exception('Missing status code in status element.');
        }
        $statusCode = $statusCode[0];

        $this->status['Code'] = $statusCode->getAttribute('Value');

        $subCode = SAML2_Utils::xpQuery($statusCode, './saml_protocol:StatusCode');
        if (!empty($subCode)) {
            $this->status['SubCode'] = $subCode[0]->getAttribute('Value');
        }

        $message = SAML2_Utils::xpQuery($status, './saml_protocol:StatusMessage');
        if (!empty($message)) {
            $this->status['Message'] = trim($message[0]->textContent);
        }
    }


    /**
     * Determine whether this is a successful response.
     *
     * @return boolean TRUE if the status code is success, FALSE if not.
     */
    public function isSuccess()
    {
        assert('array_key_exists("Code", $this->status)');

        if ($this->status['Code'] === SAML2_Const::STATUS_SUCCESS) {
            return TRUE;
        }

        return FALSE;
    }


    /**
     * Retrieve the ID of the request this is a response to.
     *
     * @return string|NULL The ID of the request.
     */
    public function getInResponseTo()
    {
        return $this->inResponseTo;
    }


    /**
     * Set the ID of the request this is a response to.
     *
     * @param string|NULL $inResponseTo The ID of the request.
     */
    public function setInResponseTo($inResponseTo)
    {
        assert('is_string($inResponseTo) || is_null($inResponseTo)');

        $this->inResponseTo = $inResponseTo;
    }


    /**
     * Retrieve the status code.
     *
     * @return array The status code.
     */
    public function getStatus()
    {
        return $this->status;
    }


    /**
     * Set the status code.
     *
     * @param array $status The status code.
     */
    public function setStatus(array $status)
    {
        assert('array_key_exists("Code", $status)');

        $this->status = $status;
        if (!array_key_exists('SubCode', $status)) {
            $this->status['SubCode'] = NULL;
        }
        if (!array_key_exists('Message', $status)) {
            $this->status['Message'] = NULL;
        }
    }


    /**
     * Convert status response message to an XML element.
     *
     * @return DOMElement This status response.
     */
    public function toUnsignedXML()
    {
        $root = parent::toUnsignedXML();

        if ($this->inResponseTo !== NULL) {
            $root->setAttribute('InResponseTo', $this->inResponseTo);
        }

        $status = $this->document->createElementNS(SAML2_Const::NS_SAMLP, 'Status');
        $root->appendChild($status);

        $statusCode = $this->document->createElementNS(SAML2_Const::NS_SAMLP, 'StatusCode');
        $statusCode->setAttribute('Value', $this->status['Code']);
        $status->appendChild($statusCode);

        if (!is_null($this->status['SubCode'])) {
            $subStatusCode = $this->document->createElementNS(SAML2_Const::NS_SAMLP, 'StatusCode');
            $subStatusCode->setAttribute('Value', $this->status['SubCode']);
            $statusCode->appendChild($subStatusCode);
        }

        if (!is_null($this->status['Message'])) {
            SAML2_Utils::addString($status, SAML2_Const::NS_SAMLP, 'StatusMessage', $this->status['Message']);
        }

        return $root;
    }

}
