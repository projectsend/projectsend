<?php
/* 
 * The configuration of SimpleSAMLphp
 * 
 * 
 */

$casldapconfig = array (
	'idpentityid.example.org' => array(
		'cas' => array(
			'login' => 'https://idpentityid.example.org/cas/login',
			'validate' => 'https://idpentityid.example.org/cas/validate',
		),
		'ldap' => array(
			'servers' => 'idpentityid.example.org',
			'enable_tls' => true,
			'searchbase' => 'dc=example,dc=org',
			'searchattributes' => 'uid',
			'attributes' => array('cn', 'mail'),
		),
	),
	'idpentityid2.example.org' => array(
		'cas' => array(
			'login' => 'https://idpentityid2.example.org/login',
			'validate' => 'https://idpentityid2.example.org/validate',
		),
		'ldap' => array(
			'servers' => 'ldap://idpentityid2.example.org',
			'enable_tls' => true,
			'searchbase' => 'ou=users,dc=example,dc=org',
			'searchattributes' => array('uid', 'mail'), # array for being able to login with either uid or mail.
			'attributes' => null,
			'priv_user_dn' => 'uid=admin,ou=users,dc=example,dc=org',
			'priv_user_pw' => 'xxxxx',
		),
	),

);
