#!/usr/bin/env php
<?php
/*
 * Interactive script to generate password hashes.
 *
 */


// This is the base directory of the SimpleSAMLphp installation
$baseDir = dirname(dirname(__FILE__));

// Add library autoloader
require_once($baseDir . '/lib/_autoload.php');


echo "Enter password: ";
$password = trim(fgets(STDIN));

if(empty($password)) {
	echo "Need at least one character for a password\n";
	exit(1);
}

$table = '';
foreach (array_chunk(hash_algos(), 6) as $chunk) {
	foreach($chunk as $algo) {
		$table .= sprintf('%-13s', $algo);
	}
	$table .= "\n";
}

echo "The following hashing algorithms are available:\n" . $table . "\n";
echo "Which one do you want? [sha256] ";
$algo = trim(fgets(STDIN));
if(empty($algo)) {
	$algo = 'sha256';
}

if(!in_array(strtolower($algo), hash_algos())) {
	echo "Hashing algorithm '$algo' is not supported\n";
	exit(1);
}

echo "Do you want to use a salt? (yes/no) [yes] ";
$s = (trim(fgets(STDIN)) == 'no') ? '' : 'S';

echo "\n  " . SimpleSAML\Utils\Crypto::pwHash($password, strtoupper( $s . $algo ) ). "\n\n";
