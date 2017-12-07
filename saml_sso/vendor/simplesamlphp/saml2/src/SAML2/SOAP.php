<?php

/**
 * Class which implements the SOAP binding.
 *
 * @package SimpleSAMLphp
 */
class SAML2_SOAP extends SAML2_Binding
{
    /**
     * Send a SAML 2 message using the SOAP binding.
     *
     * Note: This function never returns.
     *
     * @param SAML2_Message $message The message we should send.
     */
    public function send(SAML2_Message $message)
    {
        header('Content-Type: text/xml', TRUE);
        $outputFromIdp = '<?xml version="1.0" encoding="UTF-8"?>';
        $outputFromIdp .= '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">';
        $outputFromIdp .= '<SOAP-ENV:Body>';
        $xmlMessage = $message->toSignedXML();
        SAML2_Utils::getContainer()->debugMessage($xmlMessage, 'out');
        $tempOutputFromIdp = $xmlMessage->ownerDocument->saveXML($xmlMessage);
        $outputFromIdp .= $tempOutputFromIdp;
        $outputFromIdp .= '</SOAP-ENV:Body>';
        $outputFromIdp .= '</SOAP-ENV:Envelope>';
        print($outputFromIdp);
        exit(0);
    }

    /**
     * Receive a SAML 2 message sent using the HTTP-POST binding.
     *
     * Throws an exception if it is unable receive the message.
     *
     * @return SAML2_Message The received message.
     * @throws Exception
     */
    public function receive()
    {
        $postText = file_get_contents('php://input');

        if (empty($postText)) {
            throw new Exception('Invalid message received to AssertionConsumerService endpoint.');
        }

        $document = SAML2_DOMDocumentFactory::fromString($postText);
        $xml = $document->firstChild;
        SAML2_Utils::getContainer()->debugMessage($xml, 'in');
        $results = SAML2_Utils::xpQuery($xml, '/soap-env:Envelope/soap-env:Body/*[1]');

        return SAML2_Message::fromXML($results[0]);
    }

}
