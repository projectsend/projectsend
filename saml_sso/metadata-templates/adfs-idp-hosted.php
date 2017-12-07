<?php

$metadata['__DYNAMIC:1__'] = array(
	'host' => '__DEFAULT__',
	'privatekey' => 'server.pem',
	'certificate' => 'server.crt',
	'auth' => 'example-userpass',
	'authproc' => array(
		// Convert LDAP names to WS-Fed Claims.
		100 => array('class' => 'core:AttributeMap', 'name2claim'),
	),
);
