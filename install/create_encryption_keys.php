<?php
// Create The First Key
echo "<?php\ndefine('FIRSTKEY','" . base64_encode(openssl_random_pseudo_bytes(32)) . "');\n";

// Create The Second Key
echo "define('SECONDKEY','" . base64_encode(openssl_random_pseudo_bytes(64)) . "');\n?>\n";
?>
