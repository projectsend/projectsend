--TEST--
Extract Public Key
--FILE--
<?php
require(dirname(__FILE__) . '/../xmlseclibs.php');

$doc = new DOMDocument();
$arTests = array(
	'SIGN_TEST'=>'sign-basic-test.xml',
	'ERROR_TEST'=>'sign-basic-test.xml'
);

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
	if ($testName == 'SIGN_TEST') {
		$objKey->loadKey(dirname(__FILE__) . '/mycert.pem', TRUE);
		print $testName.": ";
		if ($objXMLSecDSig->verify($objKey)) {
			print "Signature validated!";
		} else {
			print "Failure!!!!!!!!";
		}
	}
	if ($testName == 'ERROR_TEST') {
		print $testName.": ";
		try {
			$objKey->loadKey(dirname(__FILE__) . '/mycert.win.pem', TRUE);
			print "PASS";
		} catch (Exception $e) {
			print $e->getMessage();
		}
	}
	print "\n";
}
?>
--EXPECTF--
SIGN_TEST: Signature validated!
ERROR_TEST: Unable to extract public key
