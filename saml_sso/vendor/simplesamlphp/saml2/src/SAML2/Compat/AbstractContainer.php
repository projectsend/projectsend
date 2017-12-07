<?php

abstract class SAML2_Compat_AbstractContainer
{
    /**
     * Get a PSR-3 compatible logger.
     * @return Psr\Log\LoggerInterface
     */
    abstract public function getLogger();

    /**
     * Generate a random identifier for identifying SAML2 documents.
     */
    abstract public function generateId();

    /**
     * Log an incoming message to the debug log.
     *
     * Type can be either:
     * - **in** XML received from third party
     * - **out** XML that will be sent to third party
     * - **encrypt** XML that is about to be encrypted
     * - **decrypt** XML that was just decrypted
     *
     * @param string $message
     * @param string $type
     * @return void
     */
    abstract public function debugMessage($message, $type);

    /**
     * Trigger the user to perform a GET to the given URL with the given data.
     *
     * @param string $url
     * @param array $data
     * @return void
     */
    abstract public function redirect($url, $data = array());

    /**
     * Trigger the user to perform a POST to the given URL with the given data.
     *
     * @param string $url
     * @param array $data
     * @return void
     */
    abstract public function postRedirect($url, $data = array());
}
