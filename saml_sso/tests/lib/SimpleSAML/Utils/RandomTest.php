<?php


/**
 * Tests for SimpleSAML\Utils\Random.
 */
class RandomTest extends PHPUnit_Framework_TestCase
{

    public function testGenerateID()
    {
        // check that it always starts with an underscore
        $this->assertStringStartsWith('_', SimpleSAML\Utils\Random::generateID());

        // check the length
        $this->assertEquals(SimpleSAML\Utils\Random::ID_LENGTH, strlen(SimpleSAML\Utils\Random::generateID()));
    }
}
