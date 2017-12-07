<?php

class SAML2_Response_XmlSignatureWrappingTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var SAML2_Signature_Validator
     */
    private $signatureValidator;

    /**
     * @var SAML2_Configuration_IdentityProvider
     */
    private $identityProviderConfiguration;

    public function setUp()
    {
        $this->signatureValidator = new SAML2_Signature_Validator(new \Psr\Log\NullLogger());

        $pattern = SAML2_Utilities_Certificate::CERTIFICATE_PATTERN;
        preg_match($pattern, SAML2_CertificatesMock::PUBLIC_KEY_PEM, $matches);

        $this->identityProviderConfiguration = new SAML2_Configuration_IdentityProvider(
            array('certificateData' => $matches[1])
        );
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Reference validation failed
     */
    public function testThatASignatureReferencingAnEmbeddedAssertionIsNotValid()
    {
        $assertion = $this->getSignedAssertionWithEmbeddedAssertionReferencedInSignature();

        $this->signatureValidator->hasValidSignature($assertion, $this->identityProviderConfiguration);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Reference validation failed
     */
    public function testThatASignatureReferencingAnotherAssertionIsNotValid()
    {
        $assertion = $this->getSignedAssertionWithSignatureThatReferencesAnotherAssertion();

        $this->signatureValidator->hasValidSignature($assertion, $this->identityProviderConfiguration);
    }

    private function getSignedAssertionWithSignatureThatReferencesAnotherAssertion()
    {
        $doc = SAML2_DOMDocumentFactory::fromFile(__DIR__ . '/signedAssertionWithInvalidReferencedId.xml');
        $assertion = new SAML2_Assertion($doc->firstChild);

        return $assertion;
    }

    private function getSignedAssertionWithEmbeddedAssertionReferencedInSignature()
    {
        $document = SAML2_DOMDocumentFactory::fromFile(__DIR__ . '/signedAssertionReferencedEmbeddedAssertion.xml');
        $assertion = new SAML2_Assertion($document->firstChild);

        return $assertion;
    }
}
