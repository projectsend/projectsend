--TEST--
Basic tests for generateSessionKey().
--FILE--
<?php
require(dirname(__FILE__) . '/../xmlseclibs.php');

$key = new XMLSecurityKey(XMLSecurityKey::TRIPLEDES_CBC);
$k = $key->generateSessionKey();
if ($key->key !== $k) {
    echo "Return value does not match generated key.";
    exit(1);
}

$keysizes = array(
    XMLSecurityKey::TRIPLEDES_CBC => 24,
    XMLSecurityKey::AES128_CBC => 16,
    XMLSecurityKey::AES192_CBC => 24,
    XMLSecurityKey::AES256_CBC => 32,
);

foreach ($keysizes as $type => $keysize) {
    $key = new XMLSecurityKey($type);
    $k = $key->generateSessionKey();
    if (strlen($k) !== $keysize) {
        printf("Invalid keysize for key type %s. Was %d, should have been %d.", $type, strlen($k), $keysize);
        exit(1);
    }
}

echo "OK\n";

?>
--EXPECTF--
OK
