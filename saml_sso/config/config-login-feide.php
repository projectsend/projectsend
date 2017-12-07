<?php
/* 
 * Configuration for the auth/login-feide.php login module.
 *
 * The configuration file is an array with multiple organizations. The user
 * can select which organization he/she wants to log in with, with a drop-down
 * menu in the user interface.
 * 
 */

$config = array (

	'orgldapconfig' => array(

		'example1.com' => array(
			'description' => 'Example Org 1',
			'searchbase'  => 'cn=people,dc=example1,dc=com',
			'hostname'    => 'ldaps://ldap.example1.com',
			'attributes'  => null,
			
			'contactMail' => 'admin@example1.com',
			'contactURL'  => 'http://admin.example1.com',
			
			// System user to bind() before we do a search for eduPersonPrincipalName
			'adminUser'     => 'uid=admin,dc=example1,dc=com',
			'adminPassword' => 'xxx',
	
		),
		'example1.com' => array(
			'description' => 'Example Org 1',
			'searchbase'  => 'cn=people,dc=example1,dc=com',
			'hostname'    => 'ldaps://ldap.example1.com',
			
			'attributes'  => array('mail', 'street'),
		),
	),
);
