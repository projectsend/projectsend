<?php
require 'tickets.php';

/*
 * Incoming parameters:
 *  service
 *  renew
 *  gateway
 *  
 */

if (!array_key_exists('service', $_GET))
	throw new Exception('Required URL query parameter [service] not provided. (CAS Server)');

$service = $_GET['service'];

$forceAuthn =isset($_GET['renew']) && $_GET['renew'];
$isPassive = isset($_GET['gateway']) && $_GET['gateway'];

$config = SimpleSAML_Configuration::getInstance();
$casconfig = SimpleSAML_Configuration::getConfig('module_casserver.php');

$legal_service_urls = $casconfig->getValue('legal_service_urls');
if (!checkServiceURL($service, $legal_service_urls))
	throw new Exception('Service parameter provided to CAS server is not listed as a legal service: [service] = ' . $service);

$auth = $casconfig->getValue('auth', 'saml2');
if (!in_array($auth, array('saml2', 'shib13')))
 	throw new Exception('CAS Service configured to use [auth] = ' . $auth . ' only [saml2,shib13] is legal.');
 
$as = new SimpleSAML_Auth_Simple($auth);
if (!$as->isAuthenticated()) {
	$params = array(
		'ForceAuthn' => $forceAuthn,
		'isPassive' => $isPassive,
	);
	$as->login($params);
}

$attributes = $as->getAttributes();

$path = $casconfig->resolvePath($casconfig->getValue('ticketcache', '/tmp'));

$ticket = str_replace( '_', 'ST-', SimpleSAML\Utils\Random::generateID() );
storeTicket($ticket, $path, array('service' => $service,
	'forceAuthn' => $forceAuthn,
	'attributes' => $attributes,
	'proxies' => array(),
	'validbefore' => time() + 5));

\SimpleSAML\Utils\HTTP::redirectTrustedURL(
	\SimpleSAML\Utils\HTTP::addURLParameters($service,
		array('ticket' => $ticket)
	)
);