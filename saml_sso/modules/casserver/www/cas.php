<?php

/*
 * Frontend for login.php, proxy.php, validate.php and serviceValidate.php. It allows them to be called
 * as cas.php/login, cas.php/validate and cas.php/serviceValidate and is meant for clients
 * like phpCAS which expects one configured prefix which it appends login, validate and 
 * serviceValidate to.
 * 
 * This version supports CAS proxying. As SSP controls the user session (TGT in CAS parlance)
 * and the CASServer as a backend/proxy server is not aware of termination of the session the Proxy-
 * Granting-Tickets (PGT) issued have a very short ttl - pt. 60 secs.
 *
 * ServiceTickets (SP) and ProxyTickets (PT) now have a 5 secs ttl.
 *
 * Proxyed services (targetService) shall be present in the legal_service_urls config.
 * 
 */
 
 
$validFunctions = array(
	'login' => 'login',
	'proxy' => 'proxy',
	'validate' => 'serviceValidate',
	'serviceValidate' => 'serviceValidate',
	'proxyValidate' => 'serviceValidate'
);

$function = substr($_SERVER['PATH_INFO'], 1);

if (!isset($validFunctions[$function])) {
	throw new SimpleSAML_Error_NotFound('Not a valid function for cas.php.');
}

include($validFunctions[$function].".php");
