--TEST--
Test the getCipherData() function.
--FILE--
<?php
require(dirname(__FILE__) . '/../xmlseclibs.php');

$doc = new DOMDocument();
$doc->load(dirname(__FILE__) . '/oaep_sha1-res.xml');


$objenc = new XMLSecEnc();
$encData = $objenc->locateEncryptedData($doc);
$objenc->setNode($encData);

$ciphervalue = $objenc->getCipherValue();
printf("Data CipherValue: %s\n", md5($ciphervalue));

$objKey = $objenc->locateKey();
$objKeyInfo = $objenc->locateKeyInfo($objKey);
$encryptedKey = $objKeyInfo->encryptedCtx;

$keyCV = $encryptedKey->getCipherValue();
printf("Key CipherValue: %s\n", md5($keyCV));

?>
--EXPECTF--
Data CipherValue: e3b188c5a139655d14d3f7a1e6477bc3
Key CipherValue: b36f81645cb068dd59d69c7ff96e835a
