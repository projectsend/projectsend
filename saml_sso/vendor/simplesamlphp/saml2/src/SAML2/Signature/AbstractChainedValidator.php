<?php

abstract class SAML2_Signature_AbstractChainedValidator implements SAML2_Signature_ChainedValidator
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * BC compatible version of the signature check
     *
     * @param SAML2_SignedElement      $element
     * @param SAML2_Certificate_X509[] $pemCandidates
     *
     * @throws Exception
     *
     * @return bool
     */
    protected function validateElementWithKeys(SAML2_SignedElement $element, $pemCandidates)
    {
        $lastException = NULL;
        foreach ($pemCandidates as $index => $candidateKey) {
            $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type' => 'public'));
            $key->loadKey($candidateKey->getCertificate());

            try {
                /*
                 * Make sure that we have a valid signature on either the response or the assertion.
                 */
                $result = $element->validate($key);
                if ($result) {
                    $this->logger->debug(sprintf('Validation with key "#%d" succeeded', $index));
                    return TRUE;
                }
                $this->logger->debug(sprintf('Validation with key "#%d" failed without exception.', $index));
            } catch (Exception $e) {
                $this->logger->debug(sprintf(
                    'Validation with key "#%d" failed with exception: %s',
                    $index,
                    $e->getMessage()
                ));

                $lastException = $e;
            }
        }

        if ($lastException !== NULL) {
            throw $lastException;
        } else {
            return FALSE;
        }
    }
}
