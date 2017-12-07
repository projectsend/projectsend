<?php

try {
	$discoHandler = new sspmod_discopower_PowerIdPDisco(array('saml20-idp-remote', 'shib13-idp-remote'), 'poweridpdisco');
} catch (Exception $exception) {
	// An error here should be caused by invalid query parameters
	throw new SimpleSAML_Error_Error('DISCOPARAMS', $exception);
}

try {
	$discoHandler->handleRequest();
} catch(Exception $exception) {
	// An error here should be caused by metadata
	throw new SimpleSAML_Error_Error('METADATA', $exception);
}
