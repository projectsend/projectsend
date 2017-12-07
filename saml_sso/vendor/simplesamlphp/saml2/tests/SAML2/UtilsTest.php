<?php

/**
 * Class SAML2_UtilsTest
 */
class SAML2_UtilsTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test querying a SAML XML document.
     */
    public function testXpQuery()
    {
        $aq = new SAML2_AttributeQuery();
        $aq->setNameID(array(
            'Value' => 'NameIDValue',
            'Format' => 'SomeNameIDFormat',
            'NameQualifier' => 'OurNameQualifier',
            'SPNameQualifier' => 'TheSPNameQualifier',
        ));

        $xml = $aq->toUnsignedXML();

        $nameID = SAML2_Utils::xpQuery($xml, './saml_assertion:Subject/saml_assertion:NameID');
        $this->assertTrue(count($nameID) === 1);
        $this->assertEquals('SomeNameIDFormat', $nameID[0]->getAttribute("Format"));
        $this->assertEquals('OurNameQualifier', $nameID[0]->getAttribute("NameQualifier"));
        $this->assertEquals('TheSPNameQualifier', $nameID[0]->getAttribute("SPNameQualifier"));
        $this->assertEquals('NameIDValue', $nameID[0]->textContent);
    }

    /**
     * Test adding an element with a string value.
     */
    public function testAddString()
    {
        $document = SAML2_DOMDocumentFactory::fromString('<root/>');

        SAML2_Utils::addString(
            $document->firstChild,
            'testns',
            'ns:somenode',
            'value'
        );
        $this->assertEquals(
            '<root><ns:somenode xmlns:ns="testns">value</ns:somenode></root>',
            $document->saveXML($document->firstChild)
        );

        $document->loadXML('<ns:root xmlns:ns="testns"/>');
        SAML2_Utils::addString(
            $document->firstChild,
            'testns',
            'ns:somenode',
            'value'
        );
        $this->assertEquals(
            '<ns:root xmlns:ns="testns"><ns:somenode>value</ns:somenode></ns:root>',
            $document->saveXML($document->firstChild)
        );
    }

    /**
     * Test adding multiple elements of a given type with given values.
     */
    public function testGetAddStrings()
    {
        $document = SAML2_DOMDocumentFactory::fromString('<root/>');
        SAML2_Utils::addStrings(
            $document->firstChild,
            'testns',
            'ns:somenode',
            FALSE,
            array('value1', 'value2')
        );
        $this->assertEquals(
            '<root>'.
            '<ns:somenode xmlns:ns="testns">value1</ns:somenode>'.
            '<ns:somenode xmlns:ns="testns">value2</ns:somenode>'.
            '</root>',
            $document->saveXML($document->firstChild)
        );

        $document->loadXML('<ns:root xmlns:ns="testns"/>');
        SAML2_Utils::addStrings(
            $document->firstChild,
            'testns',
            'ns:somenode',
            FALSE,
            array('value1', 'value2')
        );
        $this->assertEquals(
            '<ns:root xmlns:ns="testns">'.
            '<ns:somenode>value1</ns:somenode>'.
            '<ns:somenode>value2</ns:somenode>'.
            '</ns:root>',
            $document->saveXML($document->firstChild)
        );

        $document->loadXML('<root/>');
        SAML2_Utils::addStrings(
            $document->firstChild,
            'testns',
            'ns:somenode',
            TRUE,
            array('en' => 'value (en)', 'no' => 'value (no)')
        );
        $this->assertEquals(
            '<root>'.
            '<ns:somenode xmlns:ns="testns" xml:lang="en">value (en)</ns:somenode>'.
            '<ns:somenode xmlns:ns="testns" xml:lang="no">value (no)</ns:somenode>'.
            '</root>',
            $document->saveXML($document->firstChild)
        );

        $document->loadXML('<ns:root xmlns:ns="testns"/>');
        SAML2_Utils::addStrings(
            $document->firstChild,
            'testns',
            'ns:somenode',
            TRUE,
            array('en' => 'value (en)', 'no' => 'value (no)')
        );
        $this->assertEquals(
            '<ns:root xmlns:ns="testns">'.
            '<ns:somenode xml:lang="en">value (en)</ns:somenode>'.
            '<ns:somenode xml:lang="no">value (no)</ns:somenode>'.
            '</ns:root>',
            $document->saveXML($document->firstChild)
        );
    }

    /**
     * Test retrieval of a string value for a given node.
     */
    public function testExtractString()
    {
        $document = SAML2_DOMDocumentFactory::fromString(
            '<root xmlns="' . SAML2_Const::NS_MD . '">'.
            '<somenode>value1</somenode>'.
            '<somenode>value2</somenode>'.
            '</root>'
        );

        $stringValues = SAML2_Utils::extractStrings(
            $document->firstChild,
            SAML2_Const::NS_MD,
            'somenode'
        );

        $this->assertTrue(count($stringValues) === 2);
        $this->assertEquals('value1', $stringValues[0]);
        $this->assertEquals('value2', $stringValues[1]);
    }

    /**
     * Test retrieval of a localized string for a given node.
     */
    public function testExtractLocalizedString()
    {
        $document = SAML2_DOMDocumentFactory::fromString(
            '<root xmlns="' . SAML2_Const::NS_MD . '">'.
            '<somenode xml:lang="en">value (en)</somenode>'.
            '<somenode xml:lang="no">value (no)</somenode>'.
            '</root>'
        );

        $localizedStringValues = SAML2_Utils::extractLocalizedStrings(
            $document->firstChild,
            SAML2_Const::NS_MD,
            'somenode'
        );

        $this->assertTrue(count($localizedStringValues) === 2);
        $this->assertEquals('value (en)', $localizedStringValues["en"]);
        $this->assertEquals('value (no)', $localizedStringValues["no"]);
    }

    /**
     * Test xsDateTime format validity
     *
     * @dataProvider xsDateTimes
     */
    public function testXsDateTimeToTimestamp($shouldPass, $time, $expectedTs = null)
    {
        try {
            $ts = SAML2_Utils::xsDateTimeToTimestamp($time);
            $this->assertTrue($shouldPass);
            $this->assertEquals($expectedTs, $ts);
        } catch (Exception $e) {
            $this->assertFalse($shouldPass);
        }
    }

    public function xsDateTimes()
    {
        return array(
            array(true, '2015-01-01T00:00:00Z', 1420070400),
            array(true, '2015-01-01T00:00:00.0Z', 1420070400),
            array(true, '2015-01-01T00:00:00.1Z', 1420070400),
            array(false, '2015-01-01T00:00:00', 1420070400),
            array(false, '2015-01-01T00:00:00.0', 1420070400),
            array(false, 'junk'),
            array(false, '2015-01-01T00:00:00-04:00'),
            array(false, '2015-01-01T00:00:00.0-04:00'),
        );
    }
}
