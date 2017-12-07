--TEST--
Basic Encryption: Content
--FILE--
<?php
require(dirname(__FILE__) . '/../xmlseclibs.php');

if (file_exists(dirname(__FILE__) . '/oaep_sha1.xml')) {
    unlink(dirname(__FILE__) . '/oaep_sha1.xml');
}

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

$dom->save(dirname(__FILE__) . '/oaep_sha1.xml');

$root = $dom->documentElement;
echo $root->localName."\n";

unlink(dirname(__FILE__) . '/oaep_sha1.xml');

?>
--EXPECTF--
Root
