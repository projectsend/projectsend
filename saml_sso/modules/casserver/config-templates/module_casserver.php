<?php
/* 
 * Configuration for the module casserver.
 */

$config = array (

	'legal_service_urls' => array(
		'http://test.feide.no/casclient',
		'http://test.feide.no/cas2',
	),

	// Legal values: saml2, shib13
	'auth' => 'saml2',
	
	'ticketcache' => 'ticketcache',

	'attrname' => 'mail', // 'eduPersonPrincipalName',
	#'attributes' => TRUE, // enable transfer of attributes
	
);
