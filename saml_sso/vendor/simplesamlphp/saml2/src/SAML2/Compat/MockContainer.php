<?php

/**
 * Class SAML2_Compat_MockContainer
 */
class SAML2_Compat_MockContainer extends SAML2_Compat_AbstractContainer
{
    /**
     * @var string
     */
    private $id = '123';

    /**
     * @var array
     */
    private $debugMessages = array();

    /**
     * @var string
     */
    private $redirectUrl;

    /**
     * @var array
     */
    private $redirectData;

    /**
     * @var string
     */
    private $postRedirectUrl;

    /**
     * @var array
     */
    private $postRedirectData;

    /**
     * Get a PSR-3 compatible logger.
     * @return Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return new \Psr\Log\NullLogger();
    }

    /**
     * Generate a random identifier for identifying SAML2 documents.
     */
    public function generateId()
    {
        return $this->id;
    }

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
    public function debugMessage($message, $type)
    {
        $this->debugMessages[$type] = $message;
    }

    /**
     * Trigger the user to perform a GET to the given URL with the given data.
     *
     * @param string $url
     * @param array $data
     * @return void
     */
    public function redirect($url, $data = array())
    {
        $this->redirectUrl = $url;
        $this->redirectData = $data;
    }

    /**
     * Trigger the user to perform a POST to the given URL with the given data.
     *
     * @param string $url
     * @param array $data
     * @return void
     */
    public function postRedirect($url, $data = array())
    {
        $this->postRedirectUrl = $url;
        $this->postRedirectData = $data;
    }
}
