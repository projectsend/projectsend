<?php

/**
 *
 *
 * @author Mathias Meisfjordskar, University of Oslo.
 *         <mathias.meisfjordskar@usit.uio.no>
 * @package SimpleSAMLphp
 */

$params = array(
	'secure' => FALSE,
	'httponly' => TRUE,
);
\SimpleSAML\Utils\HTTP::setCookie('NEGOTIATE_AUTOLOGIN_DISABLE_PERMANENT', NULL, $params, FALSE);

$globalConfig = SimpleSAML_Configuration::getInstance();
$session = SimpleSAML_Session::getSessionFromRequest();
$session->setData('negotiate:disable', 'session', FALSE, 24*60*60);
$t = new SimpleSAML_XHTML_Template($globalConfig, 'negotiate:enable.php');
$t->show();
