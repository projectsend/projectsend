<?php
require 'tickets.php';

/*
 * Incoming parameters:
 *  targetService
 *  ptg
 *  
 */

if (array_key_exists('targetService', $_GET)) {
	$targetService = $_GET['targetService'];
	$pgt = $_GET['pgt'];
} else {
	throw new Exception('Required URL query parameter [targetService] not provided. (CAS Server)');
}

$casconfig = SimpleSAML_Configuration::getConfig('module_casserver.php');

$legal_service_urls = $casconfig->getValue('legal_service_urls');

if (!checkServiceURL($targetService, $legal_service_urls))
	throw new Exception('Service parameter provided to CAS server is not listed as a legal service: [service] = ' . $service);

$path = $casconfig->resolvePath($casconfig->getValue('ticketcache', 'ticketcache'));

$ticket = retrieveTicket($pgt, $path, false);
if ($ticket['validbefore'] > time()) {
	$pt = str_replace( '_', 'PT-', SimpleSAML\Utils\Random::generateID() );
	storeTicket($pt, $path, array(
		'service' => $targetService,
		'forceAuthn' => false,
		'attributes' => $ticket['attributes'],
		'proxies' => $ticket['proxies'],
		'validbefore' => time() + 5)
	);
		
print <<<eox
<cas:serviceResponse xmlns:cas='http://www.yale.edu/tp/cas'>
    <cas:proxySuccess>
        <cas:proxyTicket>$pt</cas:proxyTicket>
    </cas:proxySuccess>
</cas:serviceResponse>
eox;
} else {
print <<<eox
<cas:serviceResponse xmlns:cas='http://www.yale.edu/tp/cas'>
    <cas:proxyFailure code="INVALID_REQUEST">
        Proxygranting ticket to old - ssp casserver only supports shortlived (30 secs) pgts.
    </cas:proxyFailure>
</cas:serviceResponse>
eox;
}