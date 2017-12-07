--TEST--
Certificate thumbprint check
--FILE--
<?php
require(dirname(__FILE__) . '/../xmlseclibs.php');

$siteKey = new XMLSecurityKey(XMLSecurityKey::RSA_OAEP_MGF1P, array('type'=>'public'));
$siteKey->loadKey(dirname(__FILE__) . '/mycert.pem', TRUE, TRUE);

$thumbprint = $siteKey->getX509Thumbprint();
echo $thumbprint."\n";
echo base64_encode($thumbprint)."\n";
?>
--EXPECTF--
8b600d9155e8e8dfa3c10998f736be086e83ef3b
OGI2MDBkOTE1NWU4ZThkZmEzYzEwOTk4ZjczNmJlMDg2ZTgzZWYzYg==
