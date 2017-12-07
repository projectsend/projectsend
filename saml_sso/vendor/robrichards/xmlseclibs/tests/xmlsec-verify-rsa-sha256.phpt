--TEST--
Verify RSA SHA256
--SKIPIF--
<?php if (version_compare(PHP_VERSION, '5.3.0', '<')) die('SKIP Requires PHP version 5.3.0 or newer.'); ?>
--FILE--
<?php
require(dirname(__FILE__) . '/../xmlseclibs.php');

$doc = new DOMDocument();
$arTests = array('SIGN_TEST_RSA_SHA256'=>'sign-sha256-rsa-sha256-test.xml');

foreach ($arTests AS $testName=>$testFile) {
	$doc->load(dirname(__FILE__) . "/$testFile");
	$objXMLSecDSig = new XMLSecurityDSig();
	
	$objDSig = $objXMLSecDSig->locateSignature($doc);
	if (! $objDSig) {
		throw new Exception("Cannot locate Signature Node");
	}
	$objXMLSecDSig->canonicalizeSignedInfo();
	$objXMLSecDSig->idKeys = array('wsu:Id');
	$objXMLSecDSig->idNS = array('wsu'=>'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd');
	
	$retVal = $objXMLSecDSig->validateReference();

	if (! $retVal) {
		throw new Exception("Reference Validation Failed");
	}
	
	$objKey = $objXMLSecDSig->locateKey();
	if (! $objKey ) {
		throw new Exception("We have no idea about the key");
	}
	$key = NULL;
	
	$objKeyInfo = XMLSecEnc::staticLocateKeyInfo($objKey, $objDSig);

	if (! $objKeyInfo->key && empty($key)) {
		$objKey->loadKey(dirname(__FILE__) . '/mycert.pem', TRUE);
	}

	print $testName.": ";
	if ($objXMLSecDSig->verify($objKey)) {
		print "Signature validated!";
	} else {
		print "Failure!!!!!!!!";
	}
	print "\n";
}
?>
--EXPECTF--
SIGN_TEST_RSA_SHA256: Signature validated!
