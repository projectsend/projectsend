<?php
require 'tickets.php';

# set manually if called directly - ie not included from validate.php or cas.php
if (!$function) $function = 'serviceValidate';

/*
 * Incoming parameters:
 *  service
 *  renew
 *  ticket
 *
 */

if (array_key_exists('service', $_GET)) {
	$service = $_GET['service'];
	$ticket = $_GET['ticket'];
	$forceAuthn = isset($_GET['renew']) && $_GET['renew'];
} else { 
	throw new Exception('Required URL query parameter [service] not provided. (CAS Server)');
}

try {
// Load SimpleSAMLphp, configuration and metadata
	$casconfig = SimpleSAML_Configuration::getConfig('module_casserver.php');
	
	$path = $casconfig->resolvePath($casconfig->getValue('ticketcache', 'ticketcache'));
	$ticketcontent = retrieveTicket($ticket, $path);
	
	$usernamefield = $casconfig->getValue('attrname', 'eduPersonPrincipalName');
	$dosendattributes = $casconfig->getValue('attributes', FALSE);
	
	$attributes = $ticketcontent['attributes'];

	$pgtiouxml = "";

	if ($ticketcontent['service'] == $service
			&& $ticketcontent['forceAuthn'] == $forceAuthn
			&& array_key_exists($usernamefield, $attributes)
			&& $ticketcontent['validbefore'] > time()) {
					
		if (isset($_GET['pgtUrl'])) {
			$pgtUrl = $_GET['pgtUrl'];
			$pgtiou = str_replace( '_', 'PGTIOU-', SimpleSAML\Utils\Random::generateID());
			$pgt = str_replace( '_', 'PGT-', SimpleSAML\Utils\Random::generateID());
			$content = array(
				'attributes' => $attributes,
				'forceAuthn' => false,
				'proxies' => array_merge(array($service), $ticketcontent['proxies']),
				'validbefore' => time() + 60);
			\SimpleSAML\Utils\HTTP::fetch($pgtUrl . '?pgtIou=' . $pgtiou . '&pgtId=' . $pgt);
			storeTicket($pgt, $path, $content);
			$pgtiouxml = "\n<cas:proxyGrantingTicket>$pgtiou</cas:proxyGrantingTicket>\n";
		}
		
		$proxiesxml = join("\n", array_map(create_function('$a', 'return "<cas:proxy>$a</cas:proxy>";'), $ticketcontent['proxies']));
		if ($proxiesxml) $proxiesxml = "<cas:proxies>\n$proxiesxml\n</cas:proxies>\n";
		returnResponse('YES', $function, $attributes[$usernamefield][0], $dosendattributes ? $attributes : array(), $pgtiouxml.$proxiesxml);
	} else {
		returnResponse('NO', $function);
	}

} catch (Exception $e) {
	returnResponse('NO', $function, $e->getMessage());
}

function returnResponse($value, $function, $usrname = '', $attributes = array(), $xtraxml = "") {
	if ($value === 'YES') {	
		if ($function != 'validate') {
			$attributesxml = "";
			foreach ($attributes as $attributename => $attributelist) {
				$attr = htmlspecialchars($attributename);
				foreach ($attributelist as $attributevalue) {
					$attributesxml .= "<cas:$attr>" . htmlspecialchars($attributevalue) . "</cas:$attr>\n";
				}
			}
			if (sizeof($attributes)) $attributesxml = "<cas:attributes>\n" . $attributesxml . "</cas:attributes>\n";
			echo '<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas">
<cas:authenticationSuccess>
<cas:user>' . htmlspecialchars($usrname) . '</cas:user>' .
		$xtraxml .
		$attributesxml .
		'</cas:authenticationSuccess>
</cas:serviceResponse>';
		} else {
			echo 'yes' . "\n" . $usrname;
		}
	} else {
		if ($function != 'validate') {
			echo '<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas">
<cas:authenticationFailure code="">
</cas:authenticationFailure>
</cas:serviceResponse>';
		} else {
			echo 'no';

		}
	}
}