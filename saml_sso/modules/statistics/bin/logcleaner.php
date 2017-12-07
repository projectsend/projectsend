#!/usr/bin/env php
<?php


// This is the base directory of the SimpleSAMLphp installation
$baseDir = dirname(dirname(dirname(dirname(__FILE__))));

// Add library autoloader.
require_once($baseDir . '/lib/_autoload.php');

/* Initialize the configuration. */
$configdir = SimpleSAML\Utils\Config::getConfigDir();
SimpleSAML_Configuration::setConfigDir($configdir);



$progName = array_shift($argv);
$debug = FALSE;
$dryrun = FALSE;
$output = '/tmp/simplesamlphp-new.log';
$infile = NULL;

foreach($argv as $a) {
	if(strlen($a) === 0) continue;

	if(strpos($a, '=') !== FALSE) {
		$p = strpos($a, '=');
		$v = substr($a, $p + 1);
		$a = substr($a, 0, $p);
	} else {
		$v = NULL;
	}

	/* Map short options to long options. */
	$shortOptMap = array(
		'-d' => '--debug',
	);
	if(array_key_exists($a, $shortOptMap))  $a = $shortOptMap[$a];

	switch($a) {
		case '--help':
			printHelp();
			exit(0);
		case '--debug':
			$debug = TRUE;
			break;
		case '--dry-run':
			$dryrun = TRUE;
			break;
		case '--infile':
			$infile = $v;
			break;
		case '--outfile':
			$output = $v;
			break;
		default:
			echo('Unknown option: ' . $a . "\n");
			echo('Please run `' . $progName . ' --help` for usage information.' . "\n");
			exit(1);
		}
}

$cleaner = new sspmod_statistics_LogCleaner($infile);
$cleaner->dumpConfig();
$todelete = $cleaner->clean($debug);

echo "Cleaning these trackIDs: " . join(', ', $todelete) . "\n";

if (!$dryrun) {
	$cleaner->store($todelete, $output);
}

/**
 * This function prints the help output.
 */
function printHelp() {
	global $progName;

	/*   '======================================================================' */
	echo('Usage: ' . $progName . ' [options]

This program cleans logs. This script is experimental. Do not run it unless you have talked to Andreas about it. 
The script deletes log lines related to sessions that produce more than 200 lines.

Options:
	-d, --debug			Used when configuring the log file syntax. See doc.
	--dry-run			Aggregate but do not store the results.
	--infile			File input.
	--outfile			File to output the results.

');
}

