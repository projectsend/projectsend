<?php
/**
 * Config file for consentAdmin
 *
 * @author Jacob Christiansen, <jach@wayf.dk>
 * @package SimpleSAMLphp
 */
$config = array(
	/*
	 * Configuration for the database connection.
	 */
	'consentadmin'  => array(
		'consent:Database',
		'dsn'		=>	'mysql:host=DBHOST;dbname=DBNAME',
		'username'	=>	'USERNAME', 
		'password'	=>	'PASSWORD',
	),
	
	// Hash attributes including values or not
	'attributes.hash' => TRUE,

	// Where to direct the user after logout
    // REMEMBER to prefix with http:// otherwise the relaystate is only appended 
    // to saml2 logout URL
	'returnURL' => 'http://www.wayf.dk',

    // Shows description of the services if set to true (defaults to true)
    'showDescription' => true,

    // Set authority
    'authority' => 'saml2',
);
