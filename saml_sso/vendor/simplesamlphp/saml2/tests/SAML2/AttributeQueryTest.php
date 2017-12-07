<?php

/**
 * Class SAML2_AttributeQueryTest
 */
class SAML2_AttributeQueryTest extends \PHPUnit_Framework_TestCase
{
    public function testMarshalling()
    {
        $attributeQuery = new SAML2_AttributeQuery();
        $attributeQuery->setNameID(array('Value' => 'NameIDValue'));
        $attributeQuery->setAttributes(
            array(
                'test1' => array(
                    'test1_attrv1',
                    'test1_attrv2',
                ),
                'test2' => array(
                    'test2_attrv1',
                    'test2_attrv2',
                    'test2_attrv3',
                ),
                'test3' => array(),
            )
        );
        $attributeQueryElement = $attributeQuery->toUnsignedXML();

        // Test Attribute Names
        $attributes = SAML2_Utils::xpQuery($attributeQueryElement, './saml_assertion:Attribute');
        $this->assertCount(3, $attributes);
        $this->assertEquals('test1', $attributes[0]->getAttribute('Name'));
        $this->assertEquals('test2', $attributes[1]->getAttribute('Name'));
        $this->assertEquals('test3', $attributes[2]->getAttribute('Name'));

        // Test Attribute Values for Attribute 1
        $av1 = SAML2_Utils::xpQuery($attributes[0], './saml_assertion:AttributeValue');
        $this->assertCount(2, $av1);
        $this->assertEquals('test1_attrv1', $av1[0]->textContent);
        $this->assertEquals('test1_attrv2', $av1[1]->textContent);

        // Test Attribute Values for Attribute 2
        $av2 = SAML2_Utils::xpQuery($attributes[1], './saml_assertion:AttributeValue');
        $this->assertCount(3, $av2);
        $this->assertEquals('test2_attrv1', $av2[0]->textContent);
        $this->assertEquals('test2_attrv2', $av2[1]->textContent);
        $this->assertEquals('test2_attrv3', $av2[2]->textContent);

        // Test Attribute Values for Attribute 3
        $av3 = SAML2_Utils::xpQuery($attributes[2], './saml_assertion:AttributeValue');
        $this->assertCount(0, $av3);
    }
}
