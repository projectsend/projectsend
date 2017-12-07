--TEST--
Check that generated triple-des keys have the correct parity.
--FILE--
<?php
require(dirname(__FILE__) . '/../xmlseclibs.php');

/* Run the test several times, to increase the chance of detecting an error. */
for ($t = 0; $t < 16; $t++) {
    $key = new XMLSecurityKey(XMLSecurityKey::TRIPLEDES_CBC);
    $k = $key->generateSessionKey();

    for ($i = 0; $i < strlen($k); $i++) {
        $byte = ord($k[$i]);
        $parity = 0;
        while ($byte !== 0) {
            $parity ^= $byte & 1;
            $byte >>= 1;
        }
        if ($parity !== 1) {
            printf("Parity mismatch on position %d. Key was %s.\n", $i, bin2hex($k));
            exit(1);
        }
    }
}

echo "OK\n";

?>
--EXPECTF--
OK
