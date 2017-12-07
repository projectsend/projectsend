<?php

class SAML2_DOMDocumentFactoryTest extends PHPUnit_Framework_TestCase
{
    /**
     * @param mixed $argument
     *
     * @group domdocument
     * @dataProvider nonStringProvider
     * @expectedException SAML2_Exception_InvalidArgumentException
     */
    public function testOnlyAStringIsAcceptedByFronString($argument)
    {
        SAML2_DOMDocumentFactory::fromString($argument);
    }

    /**
     * @group domdocument
     * @expectedException SAML2_Exception_RuntimeException
     */
    public function testNotXmlStringRaisesAnException()
    {
        SAML2_DOMDocumentFactory::fromString('this is not xml');
    }

    /**
     * @group domdocument
     */
    public function testXmlStringIsCorrectlyLoaded()
    {
        $xml = '<root/>';

        $document = SAML2_DOMDocumentFactory::fromString($xml);

        $this->assertXmlStringEqualsXmlString($xml, $document->saveXML());
    }

    /**
     * @param mixed $argument
     *
     * @group        domdocument
     * @dataProvider nonStringProvider
     * @expectedException SAML2_Exception_InvalidArgumentException
     */
    public function testOnlyAStringIsAcceptedByFromFile($argument)
    {
        SAML2_DOMDocumentFactory::fromString($argument);
    }

    /**
     * @group        domdocument
     * @dataProvider nonStringProvider
     * @expectedException SAML2_Exception_InvalidArgumentException
     */
    public function testFileThatDoesNotExistIsNotAccepted()
    {
        $filename = 'DoesNotExist.ext';

        SAML2_DOMDocumentFactory::fromFile($filename);
    }

    /**
     * @group domdocument
     * @expectedException SAML2_Exception_RuntimeException
     */
    public function testFileThatDoesNotContainXMLCannotBeLoaded()
    {
        $file = realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'domdocument_invalid_xml.xml';

        SAML2_DOMDocumentFactory::fromFile($file);
    }

    /**
     * @group domdocument
     */
    public function testFileWithValidXMLCanBeLoaded()
    {
        $file = realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'domdocument_valid_xml.xml';

        $document = SAML2_DOMDocumentFactory::fromFile($file);

        $this->assertXmlStringEqualsXmlFile($file, $document->saveXML());
    }

    /**
     * @group                    domdocument
     * @expectedException        SAML2_Exception_RuntimeException
     * @expectedExceptionMessage Dangerous XML detected, DOCTYPE nodes are not allowed in the XML body
     */
    public function testFileThatContainsDocTypeIsNotAccepted()
    {
        $file = realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'domdocument_doctype.xml';
        SAML2_DOMDocumentFactory::fromFile($file);
    }

    /**
     * @group                    domdocument
     * @expectedException        SAML2_Exception_RuntimeException
     * @expectedExceptionMessage Dangerous XML detected, DOCTYPE nodes are not allowed in the XML body
     */
    public function testStringThatContainsDocTypeIsNotAccepted()
    {
        $xml = '<!DOCTYPE foo [<!ELEMENT foo ANY > <!ENTITY xxe SYSTEM "file:///dev/random" >]><foo />';
        SAML2_DOMDocumentFactory::fromString($xml);
    }

    /**
     * @group                    domdocument
     * @expectedException        SAML2_Exception_RuntimeException
     * @expectedExceptionMessage does not have content
     */
    public function testEmptyFileIsNotValid()
    {
        $file = realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'domdocument_empty.xml';
        SAML2_DOMDocumentFactory::fromFile($file);
    }

    /**
     * @group                    domdocument
     * @expectedException        SAML2_Exception_InvalidArgumentException
     * @expectedExceptionMessage Invalid Argument type: "non-empty string" expected, "string" given
     */
    public function testEmptyStringIsNotValid()
    {
        SAML2_DOMDocumentFactory::fromString("");
    }

    /**
     * @return array
     */
    public function nonStringProvider()
    {
        return array(
            'integer' => array(1),
            'float'   => array(1.234),
            'object'  => array(new stdClass()),
            'null'    => array(NULL),
            'boolean' => array(FALSE),
            'array'   => array(array()),
        );
    }
}
