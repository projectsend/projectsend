<?php


/**
 * Tests related to SAML metadata.
 */
class Utils_MetadataTest extends PHPUnit_Framework_TestCase
{

    /**
     * Test contact configuration parsing and sanitizing.
     */
    public function testGetContact()
    {
        // test missing type
        $contact = array(
            'name' => 'John Doe'
        );
        try {
            $parsed = \SimpleSAML\Utils\Config\Metadata::getContact($contact);
        } catch (InvalidArgumentException $e) {
            $this->assertStringStartsWith('"contactType" is mandatory and must be one of ', $e->getMessage());
        }

        // test invalid type
        $contact = array(
            'contactType' => 'invalid'
        );
        try {
            $parsed = \SimpleSAML\Utils\Config\Metadata::getContact($contact);
        } catch (InvalidArgumentException $e) {
            $this->assertStringStartsWith('"contactType" is mandatory and must be one of ', $e->getMessage());
        }

        // test all valid contact types
        foreach (\SimpleSAML\Utils\Config\Metadata::$VALID_CONTACT_TYPES as $type) {
            $contact = array(
                'contactType' => $type
            );
            $parsed = \SimpleSAML\Utils\Config\Metadata::getContact($contact);
            $this->assertArrayHasKey('contactType', $parsed);
            $this->assertArrayNotHasKey('givenName', $parsed);
            $this->assertArrayNotHasKey('surName', $parsed);
        }

        // test basic name parsing
        $contact = array(
            'contactType' => 'technical',
            'name'        => 'John Doe'
        );
        $parsed = \SimpleSAML\Utils\Config\Metadata::getContact($contact);
        $this->assertArrayNotHasKey('name', $parsed);
        $this->assertArrayHasKey('givenName', $parsed);
        $this->assertArrayHasKey('surName', $parsed);
        $this->assertEquals('John', $parsed['givenName']);
        $this->assertEquals('Doe', $parsed['surName']);

        // test comma-separated names
        $contact = array(
            'contactType' => 'technical',
            'name'        => 'Doe, John'
        );
        $parsed = \SimpleSAML\Utils\Config\Metadata::getContact($contact);
        $this->assertArrayHasKey('givenName', $parsed);
        $this->assertArrayHasKey('surName', $parsed);
        $this->assertEquals('John', $parsed['givenName']);
        $this->assertEquals('Doe', $parsed['surName']);

        // test long names
        $contact = array(
            'contactType' => 'technical',
            'name'        => 'John Fitzgerald Doe Smith'
        );
        $parsed = \SimpleSAML\Utils\Config\Metadata::getContact($contact);
        $this->assertArrayNotHasKey('name', $parsed);
        $this->assertArrayHasKey('givenName', $parsed);
        $this->assertArrayNotHasKey('surName', $parsed);
        $this->assertEquals('John Fitzgerald Doe Smith', $parsed['givenName']);

        // test comma-separated long names
        $contact = array(
            'contactType' => 'technical',
            'name'        => 'Doe Smith, John Fitzgerald'
        );
        $parsed = \SimpleSAML\Utils\Config\Metadata::getContact($contact);
        $this->assertArrayNotHasKey('name', $parsed);
        $this->assertArrayHasKey('givenName', $parsed);
        $this->assertArrayHasKey('surName', $parsed);
        $this->assertEquals('John Fitzgerald', $parsed['givenName']);
        $this->assertEquals('Doe Smith', $parsed['surName']);

        // test givenName
        $contact = array(
            'contactType' => 'technical',
        );
        $invalid_types = array(0, array(0), 0.1, true, false);
        foreach ($invalid_types as $type) {
            $contact['givenName'] = $type;
            try {
                \SimpleSAML\Utils\Config\Metadata::getContact($contact);
            } catch (InvalidArgumentException $e) {
                $this->assertEquals('"givenName" must be a string and cannot be empty.', $e->getMessage());
            }
        }

        // test surName
        $contact = array(
            'contactType' => 'technical',
        );
        $invalid_types = array(0, array(0), 0.1, true, false);
        foreach ($invalid_types as $type) {
            $contact['surName'] = $type;
            try {
                \SimpleSAML\Utils\Config\Metadata::getContact($contact);
            } catch (InvalidArgumentException $e) {
                $this->assertEquals('"surName" must be a string and cannot be empty.', $e->getMessage());
            }
        }

        // test company
        $contact = array(
            'contactType' => 'technical',
        );
        $invalid_types = array(0, array(0), 0.1, true, false);
        foreach ($invalid_types as $type) {
            $contact['company'] = $type;
            try {
                \SimpleSAML\Utils\Config\Metadata::getContact($contact);
            } catch (InvalidArgumentException $e) {
                $this->assertEquals('"company" must be a string and cannot be empty.', $e->getMessage());
            }
        }

        // test emailAddress
        $contact = array(
            'contactType' => 'technical',
        );
        $invalid_types = array(0, 0.1, true, false, array());
        foreach ($invalid_types as $type) {
            $contact['emailAddress'] = $type;
            try {
                \SimpleSAML\Utils\Config\Metadata::getContact($contact);
            } catch (InvalidArgumentException $e) {
                $this->assertEquals(
                    '"emailAddress" must be a string or an array and cannot be empty.',
                    $e->getMessage()
                );
            }
        }
        $invalid_types = array(array("string", true), array("string", 0));
        foreach ($invalid_types as $type) {
            $contact['emailAddress'] = $type;
            try {
                \SimpleSAML\Utils\Config\Metadata::getContact($contact);
            } catch (InvalidArgumentException $e) {
                $this->assertEquals(
                    'Email addresses must be a string and cannot be empty.',
                    $e->getMessage()
                );
            }
        }

        // test telephoneNumber
        $contact = array(
            'contactType' => 'technical',
        );
        $invalid_types = array(0, 0.1, true, false, array());
        foreach ($invalid_types as $type) {
            $contact['telephoneNumber'] = $type;
            try {
                \SimpleSAML\Utils\Config\Metadata::getContact($contact);
            } catch (InvalidArgumentException $e) {
                $this->assertEquals(
                    '"telephoneNumber" must be a string or an array and cannot be empty.',
                    $e->getMessage()
                );
            }
        }
        $invalid_types = array(array("string", true), array("string", 0));
        foreach ($invalid_types as $type) {
            $contact['telephoneNumber'] = $type;
            try {
                \SimpleSAML\Utils\Config\Metadata::getContact($contact);
            } catch (InvalidArgumentException $e) {
                $this->assertEquals('Telephone numbers must be a string and cannot be empty.', $e->getMessage());
            }
        }

        // test completeness
        $contact = array();
        foreach (\SimpleSAML\Utils\Config\Metadata::$VALID_CONTACT_OPTIONS as $option) {
            $contact[$option] = 'string';
        }
        $contact['contactType'] = 'technical';
        $contact['name'] = 'to_be_removed';
        $parsed = \SimpleSAML\Utils\Config\Metadata::getContact($contact);
        foreach (array_keys($parsed) as $key) {
            $this->assertEquals($parsed[$key], $contact[$key]);
        }
        $this->assertArrayNotHasKey('name', $parsed);
    }
}
