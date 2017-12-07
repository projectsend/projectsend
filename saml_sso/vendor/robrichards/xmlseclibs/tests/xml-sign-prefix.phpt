--TEST--
Basic Signature with no namespace prefix
--FILE--
<?php
require(dirname(__FILE__) . '/../xmlseclibs.php');

$prefixes = array('ds' => 'ds', 'pfx' => 'pfx', 'none' => null);

foreach ($prefixes as $file_out => $prefix) {
	$doc = new DOMDocument();
	$doc->load(dirname(__FILE__) . '/basic-doc.xml');
	
	$objDSig = new XMLSecurityDSig($prefix);
	
	$objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
	
	$objDSig->addReference($doc, XMLSecurityDSig::SHA1, array('http://www.w3.org/2000/09/xmldsig#enveloped-signature'));
	
	$objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type'=>'private'));
	/* load private key */
	$objKey->loadKey(dirname(__FILE__) . '/privkey.pem', TRUE);
	
	/* if key has Passphrase, set it using $objKey->passphrase = <passphrase> " */
	
	$objDSig->sign($objKey);
	
	/* Add associated public key */
	$options = array('issuerSerial' => true, 'subjectName' => true, );
	$objDSig->add509Cert(file_get_contents(dirname(__FILE__) . '/mycert.pem'), true, false, $options);
	
	$objDSig->appendSignature($doc->documentElement);
	$sig_out = "/xml-sign-prefix-$file_out.xml";
	$doc->save(dirname(__FILE__) . $sig_out);
	
	$sign_output = file_get_contents(dirname(__FILE__) . $sig_out);
	$sign_output_def = file_get_contents(dirname(__FILE__) . "/xml-sign-prefix-$file_out.res");
	if ($sign_output != $sign_output_def) {
		echo "NOT THE SAME\n";
	}
	echo "DONE\n";
	unlink(dirname(__FILE__) . $sig_out);
}
?>
--EXPECTF--
DONE
DONE
DONE
