<?php


/**
 * Tests for SimpleSAML\Utils\Attributes.
 *
 * @author Jaime Perez, UNINETT AS <jaime.perez@uninett.no>
 */
class Utils_AttributesTest extends PHPUnit_Framework_TestCase
{

    /**
     * Test the getExpectedAttribute() method with invalid attributes array.
     */
    public function testGetExpectedAttributeInvalidAttributesArray()
    {
        // check with empty array as input
        $attributes = 'string';
        $expected = 'string';
        $this->setExpectedException(
            'InvalidArgumentException',
            'The attributes array is not an array, it is: '.print_r($attributes, true).'.'
        );
        \SimpleSAML\Utils\Attributes::getExpectedAttribute($attributes, $expected);
    }


    /**
     * Test the getExpectedAttributeMethod() method with invalid expected attribute parameter.
     */
    public function testGetExpectedAttributeInvalidAttributeName()
    {
        // check with invalid attribute name
        $attributes = array();
        $expected = false;
        $this->setExpectedException(
            'InvalidArgumentException',
            'The expected attribute is not a string, it is: '.print_r($expected, true).'.'
        );
        \SimpleSAML\Utils\Attributes::getExpectedAttribute($attributes, $expected);
    }


    /**
     * Test the getExpectedAttributeMethod() method with a non-normalized attributes array.
     */
    public function testGetExpectedAttributeNonNormalizedArray()
    {
        // check with non-normalized attributes array
        $attributes = array(
            'attribute' => 'value',
        );
        $expected = 'attribute';
        $this->setExpectedException(
            'InvalidArgumentException',
            'The attributes array is not normalized, values should be arrays.'
        );
        \SimpleSAML\Utils\Attributes::getExpectedAttribute($attributes, $expected);
    }


    /**
     * Test the getExpectedAttribute() method with valid input but missing expected attribute.
     */
    public function testGetExpectedAttributeMissingAttribute()
    {
        // check missing attribute
        $attributes = array(
            'attribute' => array('value'),
        );
        $expected = 'missing';
        $this->setExpectedException(
            'SimpleSAML_Error_Exception',
            "No such attribute '".$expected."' found."
        );
        \SimpleSAML\Utils\Attributes::getExpectedAttribute($attributes, $expected);
    }


    /**
     * Test the getExpectedAttribute() method with an empty attribute.
     */
    public function testGetExpectedAttributeEmptyAttribute()
    {
        // check empty attribute
        $attributes = array(
            'attribute' => array(),
        );
        $expected = 'attribute';
        $this->setExpectedException(
            'SimpleSAML_Error_Exception',
            "Empty attribute '".$expected."'.'"
        );
        \SimpleSAML\Utils\Attributes::getExpectedAttribute($attributes, $expected);
    }


    /**
     * Test the getExpectedAttributeMethod() method with multiple values (not being allowed).
     */
    public function testGetExpectedAttributeMultipleValues()
    {
        // check attribute with more than value, that being not allowed
        $attributes = array(
            'attribute' => array(
                'value1',
                'value2',
            ),
        );
        $expected = 'attribute';
        $this->setExpectedException(
            'SimpleSAML_Error_Exception',
            'More than one value found for the attribute, multiple values not allowed.'
        );
        \SimpleSAML\Utils\Attributes::getExpectedAttribute($attributes, $expected);
    }


    /**
     * Test that the getExpectedAttribute() method successfully obtains values from the attributes array.
     */
    public function testGetExpectedAttribute()
    {
        // check one value
        $value = 'value';
        $attributes = array(
            'attribute' => array($value),
        );
        $expected = 'attribute';
        $this->assertEquals($value, \SimpleSAML\Utils\Attributes::getExpectedAttribute($attributes, $expected));

        // check multiple (allowed) values
        $value = 'value';
        $attributes = array(
            'attribute' => array($value, 'value2', 'value3'),
        );
        $expected = 'attribute';
        $this->assertEquals($value, \SimpleSAML\Utils\Attributes::getExpectedAttribute($attributes, $expected, true));
    }


    /**
     * Test the normalizeAttributesArray() function with input not being an array
     *
     * @expectedException InvalidArgumentException
     */
    public function testNormalizeAttributesArrayBadInput()
    {
        SimpleSAML\Utils\Attributes::normalizeAttributesArray('string');
    }

    /**
     * Test the normalizeAttributesArray() function with an array with non-string attribute names.
     *
     * @expectedException InvalidArgumentException
     */
    public function testNormalizeAttributesArrayBadKeys()
    {
        SimpleSAML\Utils\Attributes::normalizeAttributesArray(array('attr1' => 'value1', 1 => 'value2'));
    }

    /**
     * Test the normalizeAttributesArray() function with an array with non-string attribute values.
     *
     * @expectedException InvalidArgumentException
     */
    public function testNormalizeAttributesArrayBadValues()
    {
        SimpleSAML\Utils\Attributes::normalizeAttributesArray(array('attr1' => 'value1', 'attr2' => 0));
    }

    /**
     * Test the normalizeAttributesArray() function.
     */
    public function testNormalizeAttributesArray()
    {
        $attributes = array(
            'key1' => 'value1',
            'key2' => array('value2', 'value3'),
            'key3' => 'value1'
        );
        $expected = array(
            'key1' => array('value1'),
            'key2' => array('value2', 'value3'),
            'key3' => array('value1')
        );
        $this->assertEquals(
            $expected,
            SimpleSAML\Utils\Attributes::normalizeAttributesArray($attributes),
            'Attribute array normalization failed'
        );
    }
}
