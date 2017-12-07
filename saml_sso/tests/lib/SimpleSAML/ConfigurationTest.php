<?php

/**
 * Tests for SimpleSAML_Configuration
 */
class Test_SimpleSAML_Configuration extends PHPUnit_Framework_TestCase
{

    /**
     * Test SimpleSAML_Configuration::getVersion()
     */
    public function testGetVersion() {
        $c = SimpleSAML_Configuration::getOptionalConfig();
        $this->assertTrue(is_string($c->getVersion()));
    }

    /**
     * Test SimpleSAML_Configuration::getValue()
     */
    public function testGetValue() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'exists_true' => TRUE,
            'exists_null' => NULL,
        ));
        $this->assertEquals($c->getValue('missing'), NULL);
        $this->assertEquals($c->getValue('missing', TRUE), TRUE);
        $this->assertEquals($c->getValue('missing', TRUE), TRUE);

        $this->assertEquals($c->getValue('exists_true'), TRUE);

        $this->assertEquals($c->getValue('exists_null'), NULL);
        $this->assertEquals($c->getValue('exists_null', FALSE), NULL);
    }

    /**
     * Test SimpleSAML_Configuration::getValue(), REQUIRED_OPTION flag.
     * @expectedException Exception
     */
    public function testGetValueRequired() {
        $c = SimpleSAML_Configuration::loadFromArray(array());
        $c->getValue('missing', SimpleSAML_Configuration::REQUIRED_OPTION);
    }

    /**
     * Test SimpleSAML_Configuration::hasValue()
     */
    public function testHasValue() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'exists_true' => TRUE,
            'exists_null' => NULL,
        ));
        $this->assertEquals($c->hasValue('missing'), FALSE);
        $this->assertEquals($c->hasValue('exists_true'), TRUE);
        $this->assertEquals($c->hasValue('exists_null'), TRUE);
    }

    /**
     * Test SimpleSAML_Configuration::hasValue()
     */
    public function testHasValueOneOf() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'exists_true' => TRUE,
            'exists_null' => NULL,
        ));
        $this->assertEquals($c->hasValueOneOf(array()), FALSE);
        $this->assertEquals($c->hasValueOneOf(array('missing')), FALSE);
        $this->assertEquals($c->hasValueOneOf(array('exists_true')), TRUE);
        $this->assertEquals($c->hasValueOneOf(array('exists_null')), TRUE);

        $this->assertEquals($c->hasValueOneOf(array('missing1', 'missing2')), FALSE);
        $this->assertEquals($c->hasValueOneOf(array('exists_true', 'missing')), TRUE);
        $this->assertEquals($c->hasValueOneOf(array('missing', 'exists_true')), TRUE);
    }

    /**
     * Test SimpleSAML_Configuration::getBaseURL()
     */
    public function testGetBaseURL() {
        $c = SimpleSAML_Configuration::loadFromArray(array());
        $this->assertEquals($c->getBaseURL(), 'simplesaml/');

        $c = SimpleSAML_Configuration::loadFromArray(array(
            'baseurlpath' => 'simplesaml/',
        ));
        $this->assertEquals($c->getBaseURL(), 'simplesaml/');

        $c = SimpleSAML_Configuration::loadFromArray(array(
            'baseurlpath' => '/simplesaml/',
        ));
        $this->assertEquals($c->getBaseURL(), 'simplesaml/');

        $c = SimpleSAML_Configuration::loadFromArray(array(
            'baseurlpath' => 'path/to/simplesaml/',
        ));
        $this->assertEquals($c->getBaseURL(), 'path/to/simplesaml/');

        $c = SimpleSAML_Configuration::loadFromArray(array(
            'baseurlpath' => '/path/to/simplesaml/',
        ));
        $this->assertEquals($c->getBaseURL(), 'path/to/simplesaml/');

        $c = SimpleSAML_Configuration::loadFromArray(array(
            'baseurlpath' => 'https://example.org/ssp/',
        ));
        $this->assertEquals($c->getBaseURL(), 'ssp/');

        $c = SimpleSAML_Configuration::loadFromArray(array(
            'baseurlpath' => 'https://example.org/',
        ));
        $this->assertEquals($c->getBaseURL(), '');

        $c = SimpleSAML_Configuration::loadFromArray(array(
            'baseurlpath' => 'http://example.org/ssp/',
        ));
        $this->assertEquals($c->getBaseURL(), 'ssp/');

        $c = SimpleSAML_Configuration::loadFromArray(array(
            'baseurlpath' => '',
        ));
        $this->assertEquals($c->getBaseURL(), '');

        $c = SimpleSAML_Configuration::loadFromArray(array(
            'baseurlpath' => '/',
        ));
        $this->assertEquals($c->getBaseURL(), '');
    }

    /**
     * Test that SimpleSAML_Configuration::getBaseURL() fails if given a path without trailing slash
     * @expectedException SimpleSAML_Error_Exception
     */
    public function testGetBaseURLError() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'baseurlpath' => 'simplesaml',
        ));
        $c->getBaseURL();
    }

    /**
     * Test SimpleSAML_Configuration::resolvePath()
     */
    public function testResolvePath() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'basedir' => '/basedir/',
        ));

        $this->assertEquals($c->resolvePath(NULL), NULL);
        $this->assertEquals($c->resolvePath('/otherdir'), '/otherdir');
        $this->assertEquals($c->resolvePath('relativedir'), '/basedir/relativedir');

        $this->assertEquals($c->resolvePath('slash/'), '/basedir/slash');
        $this->assertEquals($c->resolvePath('slash//'), '/basedir/slash');
    }

    /**
     * Test SimpleSAML_Configuration::getPathValue()
     */
    public function testGetPathValue() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'basedir' => '/basedir/',
            'path_opt' => 'path',
            'slashes_opt' => 'slashes//',
        ));

        $this->assertEquals($c->getPathValue('missing'), NULL);
        $this->assertEquals($c->getPathValue('path_opt'), '/basedir/path/');
        $this->assertEquals($c->getPathValue('slashes_opt'), '/basedir/slashes/');
    }

    /**
     * Test SimpleSAML_Configuration::getBaseDir()
     */
    public function testGetBaseDir() {
        $c = SimpleSAML_Configuration::loadFromArray(array());
        $this->assertEquals($c->getBaseDir(), dirname(dirname(dirname(dirname(__FILE__)))) . '/');

        $c = SimpleSAML_Configuration::loadFromArray(array(
            'basedir' => '/basedir',
        ));
        $this->assertEquals($c->getBaseDir(), '/basedir/');

        $c = SimpleSAML_Configuration::loadFromArray(array(
            'basedir' => '/basedir/',
        ));
        $this->assertEquals($c->getBaseDir(), '/basedir/');
    }

    /**
     * Test SimpleSAML_Configuration::getBoolean()
     */
    public function testGetBoolean() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'true_opt' => TRUE,
            'false_opt' => FALSE,
        ));
        $this->assertEquals($c->getBoolean('missing_opt', '--missing--'), '--missing--');
        $this->assertEquals($c->getBoolean('true_opt', '--missing--'), TRUE);
        $this->assertEquals($c->getBoolean('false_opt', '--missing--'), FALSE);
    }

    /**
     * Test SimpleSAML_Configuration::getBoolean() missing option
     * @expectedException Exception
     */
    public function testGetBooleanMissing() {
        $c = SimpleSAML_Configuration::loadFromArray(array());
        $c->getBoolean('missing_opt');
    }

    /**
     * Test SimpleSAML_Configuration::getBoolean() wrong option
     * @expectedException Exception
     */
    public function testGetBooleanWrong() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'wrong' => 'true',
        ));
        $c->getBoolean('wrong');
    }

    /**
     * Test SimpleSAML_Configuration::getString()
     */
    public function testGetString() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'str_opt' => 'Hello World!',
        ));
        $this->assertEquals($c->getString('missing_opt', '--missing--'), '--missing--');
        $this->assertEquals($c->getString('str_opt', '--missing--'), 'Hello World!');
    }

    /**
     * Test SimpleSAML_Configuration::getString() missing option
     * @expectedException Exception
     */
    public function testGetStringMissing() {
        $c = SimpleSAML_Configuration::loadFromArray(array());
        $c->getString('missing_opt');
    }

    /**
     * Test SimpleSAML_Configuration::getString() wrong option
     * @expectedException Exception
     */
    public function testGetStringWrong() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'wrong' => FALSE,
        ));
        $c->getString('wrong');
    }

    /**
     * Test SimpleSAML_Configuration::getInteger()
     */
    public function testGetInteger() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'int_opt' => 42,
        ));
        $this->assertEquals($c->getInteger('missing_opt', '--missing--'), '--missing--');
        $this->assertEquals($c->getInteger('int_opt', '--missing--'), 42);
    }

    /**
     * Test SimpleSAML_Configuration::getInteger() missing option
     * @expectedException Exception
     */
    public function testGetIntegerMissing() {
        $c = SimpleSAML_Configuration::loadFromArray(array());
        $c->getInteger('missing_opt');
    }

    /**
     * Test SimpleSAML_Configuration::getInteger() wrong option
     * @expectedException Exception
     */
    public function testGetIntegerWrong() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'wrong' => '42',
        ));
        $c->getInteger('wrong');
    }

    /**
     * Test SimpleSAML_Configuration::getIntegerRange()
     */
    public function testGetIntegerRange() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'int_opt' => 42,
        ));
        $this->assertEquals($c->getIntegerRange('missing_opt', 0, 100, '--missing--'), '--missing--');
        $this->assertEquals($c->getIntegerRange('int_opt', 0, 100), 42);
    }

    /**
     * Test SimpleSAML_Configuration::getIntegerRange() below limit
     * @expectedException Exception
     */
    public function testGetIntegerRangeBelow() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'int_opt' => 9,
        ));
        $this->assertEquals($c->getIntegerRange('int_opt', 10, 100), 42);
    }

    /**
     * Test SimpleSAML_Configuration::getIntegerRange() above limit
     * @expectedException Exception
     */
    public function testGetIntegerRangeAbove() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'int_opt' => 101,
        ));
        $this->assertEquals($c->getIntegerRange('int_opt', 10, 100), 42);
    }

    /**
     * Test SimpleSAML_Configuration::getValueValidate()
     */
    public function testGetValueValidate() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'opt' => 'b',
        ));
        $this->assertEquals($c->getValueValidate('missing_opt', array('a', 'b', 'c'), '--missing--'), '--missing--');
        $this->assertEquals($c->getValueValidate('opt', array('a', 'b', 'c')), 'b');
    }

    /**
     * Test SimpleSAML_Configuration::getValueValidate() wrong option
     * @expectedException Exception
     */
    public function testGetValueValidateWrong() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'opt' => 'd',
        ));
        $c->getValueValidate('opt', array('a', 'b', 'c'));
    }

    /**
     * Test SimpleSAML_Configuration::getArray()
     */
    public function testGetArray() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'opt' => array('a', 'b', 'c'),
        ));
        $this->assertEquals($c->getArray('missing_opt', '--missing--'), '--missing--');
        $this->assertEquals($c->getArray('opt'), array('a', 'b', 'c'));
    }

    /**
     * Test SimpleSAML_Configuration::getArray() wrong option
     * @expectedException Exception
     */
    public function testGetArrayWrong() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'opt' => 'not_an_array',
        ));
        $c->getArray('opt');
    }

    /**
     * Test SimpleSAML_Configuration::getArrayize()
     */
    public function testGetArrayize() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'opt' => array('a', 'b', 'c'),
            'opt_int' => 42,
            'opt_str' => 'string',
        ));
        $this->assertEquals($c->getArrayize('missing_opt', '--missing--'), '--missing--');
        $this->assertEquals($c->getArrayize('opt'), array('a', 'b', 'c'));
        $this->assertEquals($c->getArrayize('opt_int'), array(42));
        $this->assertEquals($c->getArrayize('opt_str'), array('string'));
    }

    /**
     * Test SimpleSAML_Configuration::getArrayizeString()
     */
    public function testGetArrayizeString() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'opt' => array('a', 'b', 'c'),
            'opt_str' => 'string',
        ));
        $this->assertEquals($c->getArrayizeString('missing_opt', '--missing--'), '--missing--');
        $this->assertEquals($c->getArrayizeString('opt'), array('a', 'b', 'c'));
        $this->assertEquals($c->getArrayizeString('opt_str'), array('string'));
    }

    /**
     * Test SimpleSAML_Configuration::getArrayizeString() option with an array that contains something that isn't a string.
     * @expectedException Exception
     */
    public function testGetArrayizeStringWrongValue() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'opt' => array('a', 'b', 42),
        ));
        $c->getArrayizeString('opt');
    }

    /**
     * Test SimpleSAML_Configuration::getConfigItem()
     */
    public function testGetConfigItem() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'opt' => array('a' => 42),
        ));
        $this->assertEquals($c->getConfigItem('missing_opt', '--missing--'), '--missing--');
        $opt = $c->getConfigItem('opt');
        $this->assertInstanceOf('SimpleSAML_Configuration', $opt);
        $this->assertEquals($opt->getValue('a'), 42);
    }

    /**
     * Test SimpleSAML_Configuration::getConfigItem() wrong option
     * @expectedException Exception
     */
    public function testGetConfigItemWrong() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'opt' => 'not_an_array',
        ));
        $c->getConfigItem('opt');
    }

    /**
     * Test SimpleSAML_Configuration::getConfigList()
     */
    public function testGetConfigList() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'opts' => array(
               'a' => array('opt1' => 'value1'),
               'b' => array('opt2' => 'value2'),
            ),
        ));
        $this->assertEquals($c->getConfigList('missing_opt', '--missing--'), '--missing--');
        $opts = $c->getConfigList('opts');
        $this->assertInternalType('array', $opts);
        $this->assertEquals(array_keys($opts), array('a', 'b'));
        $this->assertInstanceOf('SimpleSAML_Configuration', $opts['a']);
        $this->assertEquals($opts['a']->getValue('opt1'), 'value1');
        $this->assertInstanceOf('SimpleSAML_Configuration', $opts['b']);
        $this->assertEquals($opts['b']->getValue('opt2'), 'value2');
    }

    /**
     * Test SimpleSAML_Configuration::getConfigList() wrong option
     * @expectedException Exception
     */
    public function testGetConfigListWrong() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'opt' => 'not_an_array',
        ));
        $c->getConfigList('opt');
    }

    /**
     * Test SimpleSAML_Configuration::getOptions()
     */
    public function testGetOptions() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'a' => TRUE,
            'b' => NULL,
        ));
        $this->assertEquals($c->getOptions(), array('a', 'b'));
    }

    /**
     * Test SimpleSAML_Configuration::toArray()
     */
    public function testToArray() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'a' => TRUE,
            'b' => NULL,
        ));
        $this->assertEquals($c->toArray(), array('a' => TRUE, 'b' => NULL));
    }

    /**
     * Test SimpleSAML_Configuration::getLocalizedString()
     */
    public function testGetLocalizedString() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'str_opt' => 'Hello World!',
            'str_array' => array(
                'en' => 'Hello World!',
                'no' => 'Hei Verden!',
            ),
        ));
        $this->assertEquals($c->getLocalizedString('missing_opt', '--missing--'), '--missing--');
        $this->assertEquals($c->getLocalizedString('str_opt'), array('en' => 'Hello World!'));
        $this->assertEquals($c->getLocalizedString('str_array'), array('en' => 'Hello World!', 'no' => 'Hei Verden!'));
    }

    /**
     * Test SimpleSAML_Configuration::getLocalizedString() not array nor simple string
     * @expectedException Exception
     */
    public function testGetLocalizedStringNotArray() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'opt' => 42,
        ));
        $c->getLocalizedString('opt');
    }

    /**
     * Test SimpleSAML_Configuration::getLocalizedString() not string key
     * @expectedException Exception
     */
    public function testGetLocalizedStringNotStringKey() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'opt' => array(42 => 'text'),
        ));
        $c->getLocalizedString('opt');
    }

    /**
     * Test SimpleSAML_Configuration::getLocalizedString() not string value
     * @expectedException Exception
     */
    public function testGetLocalizedStringNotStringValue() {
        $c = SimpleSAML_Configuration::loadFromArray(array(
            'opt' => array('en' => 42),
        ));
        $c->getLocalizedString('opt');
    }

}
