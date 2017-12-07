<?php

/* 
 * Configuration for the multi-DN LDAP authentication module.
 * 
 */

$ldapmulti = array (

	'feide.no' => array(
		'description'		=> 'Feide',
		// for a description of options see equivalent options in ldap.php starting with auth.ldap.
		'dnpattern'			=> 'uid=%username%,dc=feide,dc=no,ou=feide,dc=uninett,dc=no',
		'hostname'			=> 'ldap.uninett.no',
		'attributes'		=> NULL,
		'enable_tls'		=> TRUE,
		'search.enable'		=> FALSE,
		'search.base'		=> NULL,
		'search.attributes'	=> NULL,
		'search.username'	=> NULL,
		'search.password'	=> NULL,
	),

	'uninett.no' => array(
		'description'		=> 'UNINETT',
		'dnpattern'			=> 'uid=%username%,ou=people,dc=uninett,dc=no',
		'hostname'			=> 'ldap.uninett.no',
		'attributes'		=> NULL,
	)
	
);
