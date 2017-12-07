#!/usr/bin/env php
<?php

/*
 * This script can be used to generate metadata for SimpleSAMLphp
 * based on an XML metadata file.
 */


// This is the base directory of the SimpleSAMLphp installation
$baseDir = dirname(dirname(dirname(dirname(__FILE__))));

// Add library autoloader.
require_once($baseDir . '/lib/_autoload.php');

SimpleSAML_Session::useTransientSession(); /* No need to try to create a session here. */

if(!SimpleSAML_Module::isModuleEnabled('metarefresh')) {
	echo("You need to enable the metarefresh module before this script can be used.\n");
	echo("You can enable it by running the following command:\n");
	echo('  echo >"' . $baseDir . '/modules/metarefresh/enable' . "\"\n");
	exit(1);
}

/* Initialize the configuration. */
$configdir = SimpleSAML\Utils\Config::getConfigDir();
SimpleSAML_Configuration::setConfigDir($configdir);

/* $outputDir contains the directory we will store the generated metadata in. */
$outputDir = $baseDir . '/metadata-generated';


/* $toStdOut is a boolean telling us wheter we will print the output to stdout instead
 * of writing it to files in $outputDir.
 */
$toStdOut = FALSE;

/* $certificates contains the certificates which should be used to check the signature of the signed
 * EntityDescriptor in the metadata, or NULL if signature verification shouldn't be done.
 */
$certificates = NULL;

/* $validateFingerprint contains the fingerprint of the certificate which should have been used
 * to sign the EntityDescriptor in the metadata, or NULL if fingerprint validation shouldn't be
 * done.
 */
$validateFingerprint = NULL;


/* This variable contains the files we will parse. */
$files = array();

/* Parse arguments. */

$progName = array_shift($argv);

foreach($argv as $a) {
	if(strlen($a) === 0) {
		continue;
	}

	if($a[0] !== '-') {
		/* Not an option. Assume that it is a file we should parse. */
		$files[] = $a;
		continue;
	}

	if(strpos($a, '=') !== FALSE) {
		$p = strpos($a, '=');
		$v = substr($a, $p + 1);
		$a = substr($a, 0, $p);
	} else {
		$v = NULL;
	}

	/* Map short options to long options. */
	$shortOptMap = array(
		'-h' => '--help',
		'-o' => '--out-dir',
		'-s' => '--stdout',
		);
	if(array_key_exists($a, $shortOptMap)) {
		$a = $shortOptMap[$a];
	}

	switch($a) {
	case '--certificate':
		if($v === NULL || strlen($v) === 0) {
			echo('The --certficate option requires an parameter.' . "\n");
			echo('Please run `' . $progName . ' --help` for usage information.' . "\n");
			exit(1);
		}
		$certificates[] = $v;
		break;
	case '--validate-fingerprint':
		if($v === NULL || strlen($v) === 0) {
			echo('The --validate-fingerprint option requires an parameter.' . "\n");
			echo('Please run `' . $progName . ' --help` for usage information.' . "\n");
			exit(1);
		}
		$validateFingerprint = $v;
		break;
	case '--help':
		printHelp();
		exit(0);
	case '--out-dir':
		if($v === NULL || strlen($v) === 0) {
			echo('The --out-dir option requires an parameter.' . "\n");
			echo('Please run `' . $progName . ' --help` for usage information.' . "\n");
			exit(1);
		}
		$outputDir =   $baseDir . ($v[0] == '/' ? $v : '/' .  $v);
		break;
	case '--stdout':
		$toStdOut = TRUE;
		break;
	default:
		echo('Unknown option: ' . $a . "\n");
		echo('Please run `' . $progName . ' --help` for usage information.' . "\n");
		exit(1);
	}
}

if(count($files) === 0) {
	echo($progName . ': Missing input files. Please run `' . $progName . ' --help` for usage information.' . "\n");
	exit(1);
}




/* The metadata global variable will be filled with the metadata we extract. */
$metaloader = new sspmod_metarefresh_MetaLoader();

foreach($files as $f) {
	$source = array('src' => $f);
	if (isset($certificates)) $source['certificates'] = $certificates;
	if (isset($validateFingerprint)) $source['validateFingerprint'] = $validateFingerprint;
	$metaloader->loadSource($source);
}

if($toStdOut) {
	$metaloader->dumpMetadataStdOut();
} else {
	$metaloader->writeMetadataFiles($outputDir);
}

/**
 * This function prints the help output.
 */
function printHelp() {
	global $progName;

	/*   '======================================================================' */
	echo('Usage: ' . $progName . ' [options] [files]' . "\n");
	echo("\n");
	echo('This program parses a SAML metadata files and output pieces that can' . "\n");
	echo('be added to the metadata files in metadata/.' . "\n");
	echo("\n");
	echo('Options:' . "\n");
	echo(' --certificate=<FILE>         The certificate which should be used' . "\n");
	echo('                              to check the signature of the metadata.' . "\n");
	echo('                              The file are stored in the cert dir.' . "\n");
	echo('                              It is possibility to add multiple' . "\n");
	echo('                              --certificate options to handle' . "\n");
	echo('                              key rollover.' . "\n");
	echo(' --validate-fingerprint=<FINGERPRINT>' . "\n");
	echo('                              Check the signature of the metadata,' . "\n");
	echo('                              and check the fingerprint of the' . "\n");
	echo('                              certificate against <FINGERPRINT>.' . "\n");
	echo(' -h, --help                   Print this help.' . "\n");
	echo(' -o=<DIR>, --out-dir=<DIR>    Write the output to this directory. The' . "\n");
	echo('                              default directory is metadata-generated/.' . "\n");
	echo('                              Path will be relative to the SimpleSAMLphp' . "\n");
	echo('                              base directory.' . "\n");
	echo(' -s, --stdout                 Write the output to stdout instead of' . "\n");
	echo('                              seperate files in the output directory.' . "\n");
	echo("\n");
}
