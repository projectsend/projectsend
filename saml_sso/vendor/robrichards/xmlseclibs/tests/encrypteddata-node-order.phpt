--TEST--
Test node order of EncryptedData children.
--DESCRIPTION--
Makes sure that the child elements of EncryptedData appear in
the correct order.
--FILE--
<?php
require(dirname(__FILE__) . '/../xmlseclibs.php');

$dom = new DOMDocument();
$dom->load(dirname(__FILE__) . '/basic-doc.xml');

$objKey = new XMLSecurityKey(XMLSecurityKey::AES256_CBC);
$objKey->generateSessionKey();

$siteKey = new XMLSecurityKey(XMLSecurityKey::RSA_OAEP_MGF1P, array('type'=>'public'));
$siteKey->loadKey(dirname(__FILE__) . '/mycert.pem', TRUE, TRUE);

$enc = new XMLSecEnc();
$enc->setNode($dom->documentElement);
$enc->encryptKey($siteKey, $objKey);

$enc->type = XMLSecEnc::Content;
$encNode = $enc->encryptNode($objKey);

$nodeOrder = array(
	'EncryptionMethod',
	'KeyInfo',
	'CipherData',
	'EncryptionProperties',
);

$prevNode = 0;
for ($node = $encNode->firstChild; $node !== NULL; $node = $node->nextSibling) {
	if (! ($node instanceof DOMElement)) {
		/* Skip comment and text nodes. */
		continue;
	}

	$name = $node->localName;

	$cIndex = array_search($name, $nodeOrder, TRUE);
	if ($cIndex === FALSE) {
		die("Unknown node: $name");
	}

	if ($cIndex >= $prevNode) {
		/* In correct order. */
		$prevNode = $cIndex;
		continue;
	}

	$prevName = $nodeOrder[$prevNode];
	die("Incorrect order: $name must appear before $prevName");
}

echo("OK\n");

?>
--EXPECTF--
OK
