<?php
require_once('simplesamlsp/lib/_autoload.php');
$auth = new SimpleSAML_Auth_Simple('default-sp');
$login_url =  $auth->getLoginURL();
    if (!$auth->isAuthenticated()) {
        /* Show login link. */
        
		//$ss = $auth->requireAuth(array('saml:idp' => 'http://demo3.rndshosting.com/SAML/simplesamlidp/www/saml2/idp/metadata.php',));
		print('<a href="'.$login_url.'">Login</a>');
    }else{
		$attributes = $auth->getAttributes();
		print_r($attributes);	
		$url = $auth->getLogoutURL();
		print('<a href="' . htmlspecialchars($url) . '">Logout</a>');
// echo $auth->logout('http://demo3.rndshosting.com/SAML/test.php');
		
		
	}
	//exit;
//$auth->requireAuth();





?>


