<?php

require_once(dirname(dirname(__FILE__)) . '/libextinc/OAuth.php');

/**
 * OAuth Provider implementation..
 *
 * @author Andreas Åkre Solberg, <andreas.solberg@uninett.no>, UNINETT AS.
 * @package SimpleSAMLphp
 */
class sspmod_oauth_OAuthServer extends OAuthServer {
	public function get_signature_methods() {
		return $this->signature_methods;
	}
}

