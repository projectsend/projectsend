--TEST--
Validate Digest SHA 512
--FILE--
<?php
require(dirname(__FILE__) . '/../xmlseclibs.php');

$doc = new DOMDocument();
$doc->load(dirname(__FILE__) . '/basic-doc.xml');

$objDSig = new XMLSecurityDSig();

$objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);

$objDSig->addReference($doc, XMLSecurityDSig::SHA512, array('http://www.w3.org/2000/09/xmldsig#enveloped-signature'));

$objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type'=>'private'));
/* load private key */
$objKey->loadKey(dirname(__FILE__) . '/privkey.pem', TRUE);

$objDSig->sign($objKey);

/* Add associated public key */
$objDSig->add509Cert(file_get_contents(dirname(__FILE__) . '/mycert.pem'));

$objDSig->appendSignature($doc->documentElement);

$signed = $doc->saveXML();

/* Validate the digest which we first split at char 64 to new line */ 
$dom = new DOMDocument();
$dom->loadXML($signed);
/* Add linefeed after char 64 in the digest value */
$xpath = new DOMXPath($dom);
$xpath->registerNamespace('dsig', XMLSecurityDSig::XMLDSIGNS);
$query = '//dsig:DigestValue/text()';
$nodeset = $xpath->query($query, $dom);

$digestValue = $nodeset->item(0);
$digestValue->insertData(63, "\n");

$objXMLSecDSig = new XMLSecurityDSig();

$objDSig = $objXMLSecDSig->locateSignature($dom);
if (! $objDSig) {
	throw new Exception("Cannot locate Signature Node");
}
$objXMLSecDSig->canonicalizeSignedInfo();
	
try {
	$retVal = $objXMLSecDSig->validateReference();
	echo "Reference Validation Succeeded\n";
} catch (Exception $e) {
	echo "Reference Validation Failed\n";
}

?>
--EXPECTF--
Reference Validation Succeeded
