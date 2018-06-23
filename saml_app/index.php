<?php
error_reporting(-1);
require_once (dirname(__FILE__) . '/../sys.includes.php');
require_once (dirname(__FILE__) . '/../saml_sso/lib/_autoload.php');
$as = new SimpleSAML_Auth_Simple('default-sp');
$as->requireAuth();

$attributes = $as->getAttributes();
$email = $attributes['http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress'][0];
$loc = '../sociallogin/saml-login.php?email='.$email;
echo "<script type='text/javascript'>window.location.href = '$loc';</script>";
exit();
// Get a logout URL
$url = $as->getLogoutURL();
echo '<a href="' . htmlspecialchars($url) . '">Logout</a>';
