--TEST--
WithComments with an ID reference.
--DESCRIPTION--
Checks that comments are removed when using an ID URI in a Reference.
--FILE--
<?php
require(dirname(__FILE__) . '/../xmlseclibs.php');

$doc = new DOMDocument();
$doc->load(dirname(__FILE__) . '/withcomment-id-uri.xml');
$objXMLSecDSig = new XMLSecurityDSig();
$objXMLSecDSig->idKeys = array('xml:id');

$objDSig = $objXMLSecDSig->locateSignature($doc);
if (! $objDSig) {
	throw new Exception("Cannot locate Signature Node");
}

$retVal = $objXMLSecDSig->validateReference();
if (! $retVal) {
	throw new Exception("Reference Validation Failed");
}

/*
 * Since we are testing reference canonicalization, we don't need to
 * do more than reference validation here.
 */
echo "OK\n";
?>
--EXPECTF--
OK
