--TEST--
Test getSymmetricKeySize().
--FILE--
<?php
require(dirname(__FILE__) . '/../xmlseclibs.php');

$keysizes = array(
    XMLSecurityKey::TRIPLEDES_CBC => 24,
    XMLSecurityKey::AES128_CBC => 16,
    XMLSecurityKey::AES192_CBC => 24,
    XMLSecurityKey::AES256_CBC => 32,
);

foreach ($keysizes as $type => $keysize) {
    $key = new XMLSecurityKey($type);
    $size = $key->getSymmetricKeySize();
    if ($size !== $keysize) {
        printf("Invalid keysize for key type %s. Was %d, should have been %d.", $type, $size, $keysize);
        exit(1);
    }
}

echo "OK\n";

?>
--EXPECTF--
OK
