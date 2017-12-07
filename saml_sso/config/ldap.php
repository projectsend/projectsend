<?php
/* 
 * Configuration for the LDAP authentication module.
 */

$config = array (

	/**
	 * LDAP configuration. This is only relevant if you use the LDAP authentication plugin.
	 *
	 * The attributes parameter is a list of attributes that should be retrieved.
	 * If the attributes parameter is set to null, all attributes will be retrieved.
	 */
	'auth.ldap.dnpattern'  => 'uid=%username%,dc=feide,dc=no,ou=feide,dc=uninett,dc=no',
	'auth.ldap.hostname'   => 'ldap.uninett.no',
	'auth.ldap.attributes' => null,
	'auth.ldap.enable_tls' => true,
	
	/*
	 * Searching the DN of the user.
	 */

	// Set this to TRUE to enable searching.
	'auth.ldap.search.enable' => FALSE,

	// The base DN for the search.
	'auth.ldap.search.base' => NULL,

	/* The attribute(s) to search for.
	 *
	 * This may be a single string, or an array of string. If this is an array, then any of the attributes
	 * in the array may match the value the user supplied as the username.
	 */
	'auth.ldap.search.attributes' => NULL,

	/* The username & password the SimpleSAMLphp should bind as before searching. If this is left
	 * as NULL, no bind will be performed before searching.
	 */
	'auth.ldap.search.username' => NULL,
	'auth.ldap.search.password' => NULL,

);
