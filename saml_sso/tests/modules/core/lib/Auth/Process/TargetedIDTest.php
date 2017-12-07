<?php

/**
 * Test for the core:TargetedID filter.
 */
class Test_Core_Auth_Process_TargetedID extends PHPUnit_Framework_TestCase
{
    /**
     * Helper function to run the filter with a given configuration.
     *
     * @param array $config  The filter configuration.
     * @param array $request  The request state.
     * @return array  The state array after processing.
     */
    private static function processFilter(array $config, array $request)
    {
        $filter = new sspmod_core_Auth_Process_TargetedID($config, NULL);
        $filter->process($request);
        return $request;
    }

//    /**
//     * Test the most basic functionality
//     */
//    public function testBasic()
//    {
//        $config = array(
//        );
//        $request = array(
//            'Attributes' => array(),
//            'UserID' => 'user2@example.org',
//        );
//        $result = self::processFilter($config, $request);
//        $attributes = $result['Attributes'];
//        $this->assertArrayHasKey('eduPersonTargetedID', $attributes);
//        $this->assertRegExp('/^[0-9a-f]{40}$/', $attributes['eduPersonTargetedID'][0]);
//    }
//
//    /**
//     * Test with src and dst entityIds.
//     * Make sure to overwrite any present eduPersonTargetedId
//     */
//    public function testWithSrcDst()
//    {
//        $config = array(
//        );
//        $request = array(
//            'Attributes' => array(
//                'eduPersonTargetedID' => 'dummy',
//            ),
//            'UserID' => 'user2@example.org',
//            'Source' => array(
//                'metadata-set' => 'saml20-idp-hosted',
//                'entityid' => 'urn:example:src:id',
//            ),
//            'Destination' => array(
//                'metadata-set' => 'saml20-sp-remote',
//                'entityid' => 'joe',
//            ),
//        );
//        $result = self::processFilter($config, $request);
//        $attributes = $result['Attributes'];
//        $this->assertArrayHasKey('eduPersonTargetedID', $attributes);
//        $this->assertRegExp('/^[0-9a-f]{40}$/', $attributes['eduPersonTargetedID'][0]);
//    }
//
//    /**
//     * Test with nameId config option set.
//     */
//    public function testNameIdGeneration()
//    {
//        $config = array(
//            'nameId' => true,
//        );
//        $request = array(
//            'Attributes' => array(
//            ),
//            'UserID' => 'user2@example.org',
//            'Source' => array(
//                'metadata-set' => 'saml20-idp-hosted',
//                'entityid' => 'urn:example:src:id',
//            ),
//            'Destination' => array(
//                'metadata-set' => 'saml20-sp-remote',
//                'entityid' => 'joe',
//            ),
//        );
//        $result = self::processFilter($config, $request);
//        $attributes = $result['Attributes'];
//        $this->assertArrayHasKey('eduPersonTargetedID', $attributes);
//        $this->assertRegExp('#^<saml:NameID xmlns:saml="urn:oasis:names:tc:SAML:2\.0:assertion" NameQualifier="urn:example:src:id" SPNameQualifier="joe" Format="urn:oasis:names:tc:SAML:2\.0:nameid-format:persistent">[0-9a-f]{40}</saml:NameID>$#', $attributes['eduPersonTargetedID'][0]);
//    }
//
//    /**
//     * Test that Id is the same for subsequent invocations with same input.
//     */
//    public function testIdIsPersistent()
//    {
//        $config = array(
//        );
//        $request = array(
//            'Attributes' => array(
//                'eduPersonTargetedID' => 'dummy',
//            ),
//            'UserID' => 'user2@example.org',
//            'Source' => array(
//                'metadata-set' => 'saml20-idp-hosted',
//                'entityid' => 'urn:example:src:id',
//            ),
//            'Destination' => array(
//                'metadata-set' => 'saml20-sp-remote',
//                'entityid' => 'joe',
//            ),
//        );
//        for ($i = 0; $i < 10; ++$i) {
//		$result = self::processFilter($config, $request);
//		$attributes = $result['Attributes'];
//                $tid = $attributes['eduPersonTargetedID'][0];
//                if (isset($prevtid)) {
//                    $this->assertEquals($prevtid, $tid);
//                    $prevtid = $tid;
//                }
//        }
//    }
//
//    /**
//     * Test that Id is different for two different usernames and two different sp's
//     */
//    public function testIdIsUnique()
//    {
//        $config = array(
//        );
//        $request = array(
//            'Attributes' => array(
//            ),
//            'UserID' => 'user2@example.org',
//            'Source' => array(
//                'metadata-set' => 'saml20-idp-hosted',
//                'entityid' => 'urn:example:src:id',
//            ),
//            'Destination' => array(
//                'metadata-set' => 'saml20-sp-remote',
//                'entityid' => 'joe',
//            ),
//        );
//	$result = self::processFilter($config, $request);
//	$tid1 = $result['Attributes']['eduPersonTargetedID'][0];
//
//        $request['UserID'] = 'user3@example.org';
//	$result = self::processFilter($config, $request);
//	$tid2 = $result['Attributes']['eduPersonTargetedID'][0];
//        
//        $this->assertNotEquals($tid1, $tid2);
//
//        $request['Destination']['entityid'] = 'urn:example.org:another-sp';
//	$result = self::processFilter($config, $request);
//	$tid3 = $result['Attributes']['eduPersonTargetedID'][0];
//        
//        $this->assertNotEquals($tid2, $tid3);
//    }

    /**
     * Test no userid set
     *
     * @expectedException Exception
     */
    public function testNoUserID()
    {
        $config = array(
        );
        $request = array(
            'Attributes' => array(),
        );
        $result = self::processFilter($config, $request);
    }

    /**
     * Test with specified attribute not set
     *
     * @expectedException Exception
     */
    public function testAttributeNotExists()
    {
        $config = array(
            'attributename' => 'uid',
        );
        $request = array(
            'Attributes' => array(
                'displayName' => 'Jack Student',
            ),
        );
        $result = self::processFilter($config, $request);
    }

    /**
     * Test with configuration error 1
     *
     * @expectedException Exception
     */
    public function testConfigInvalidAttributeName()
    {
        $config = array(
            'attributename' => 5,
        );
        $request = array(
            'Attributes' => array(
                'displayName' => 'Jack Student',
            ),
        );
        $result = self::processFilter($config, $request);
    }

    /**
     * Test with configuration error 2
     *
     * @expectedException Exception
     */
    public function testConfigInvalidNameId()
    {
        $config = array(
            'nameId' => 'persistent',
        );
        $request = array(
            'Attributes' => array(
                'displayName' => 'Jack Student',
            ),
        );
        $result = self::processFilter($config, $request);
    }
}
