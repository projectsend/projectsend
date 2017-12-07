--TEST--
Signature RSA SHA256
--SKIPIF--
<?php if (version_compare(PHP_VERSION, '5.3.0', '<')) die('SKIP Requires PHP version 5.3.0 or newer.'); ?>
--FILE--
<?php
require(dirname(__FILE__) . '/../xmlseclibs.php');

if (file_exists(dirname(__FILE__) . '/sign-sha256-rsa-sha256-test.xml')) {
    unlink(dirname(__FILE__) . '/sign-sha256-rsa-sha256-test.xml');
}

$doc = new DOMDocument();
$doc->load(dirname(__FILE__) . '/basic-doc.xml');

$objDSig = new XMLSecurityDSig();

$objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);

$objDSig->addReference($doc, XMLSecurityDSig::SHA256, array('http://www.w3.org/2000/09/xmldsig#enveloped-signature'));

$objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, array('type'=>'private'));
/* load private key */
$objKey->loadKey(dirname(__FILE__) . '/privkey.pem', TRUE);

/* if key has Passphrase, set it using $objKey->passphrase = <passphrase> " */


$objDSig->sign($objKey);

/* Add associated public key */
$objDSig->add509Cert(file_get_contents(dirname(__FILE__) . '/mycert.pem'));

$objDSig->appendSignature($doc->documentElement);
$doc->save(dirname(__FILE__) . '/sign-sha256-rsa-sha256-test.xml');

$sign_output = file_get_contents(dirname(__FILE__) . '/sign-sha256-rsa-sha256-test.xml');
$sign_output_def = file_get_contents(dirname(__FILE__) . '/sign-sha256-rsa-sha256-test.res');
if ($sign_output != $sign_output_def) {
	echo "NOT THE SAME\n";
}
echo "DONE\n";
?>
--EXPECTF--
DONE
