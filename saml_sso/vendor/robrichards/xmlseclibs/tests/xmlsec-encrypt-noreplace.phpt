--TEST--
Encryption without modifying original data
--FILE--
<?php
require(dirname(__FILE__) . '/../xmlseclibs.php');

$dom = new DOMDocument();
$dom->load(dirname(__FILE__) . '/basic-doc.xml');

$origData = $dom->saveXML();

$objKey = new XMLSecurityKey(XMLSecurityKey::AES256_CBC);
$objKey->generateSessionKey();

$siteKey = new XMLSecurityKey(XMLSecurityKey::RSA_OAEP_MGF1P, array('type'=>'public'));
$siteKey->loadKey(dirname(__FILE__) . '/mycert.pem', TRUE, TRUE);

$enc = new XMLSecEnc();
$enc->setNode($dom->documentElement);
$enc->encryptKey($siteKey, $objKey);

$enc->type = XMLSecEnc::Element;
$encNode = $enc->encryptNode($objKey, FALSE);

$newData = $dom->saveXML();
if ($newData !== $origData) {
    echo "Original data was modified.\n";
}

if ($encNode->namespaceURI !== XMLSecEnc::XMLENCNS || $encNode->localName !== 'EncryptedData') {
    echo "Encrypted node wasn't a <xenc:EncryptedData>-element.\n";
}

?>
--EXPECTF--
