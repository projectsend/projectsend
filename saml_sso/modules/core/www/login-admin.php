<?php
/*
 * Helper page for starting a admin login. Can be used as a target for links.
 */

if (!array_key_exists('ReturnTo', $_REQUEST)) {
	throw new SimpleSAML_Error_BadRequest('Missing ReturnTo parameter.');
}

SimpleSAML\Utils\Auth::requireAdmin();

\SimpleSAML\Utils\HTTP::redirectUntrustedURL($_REQUEST['ReturnTo']);

