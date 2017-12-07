<?php

require_once('../_include.php');

// Load SimpleSAMLphp, configuration
$config = SimpleSAML_Configuration::getInstance();
$session = SimpleSAML_Session::getSessionFromRequest();

// Check if valid local session exists..
SimpleSAML\Utils\Auth::requireAdmin();

$attributes = array();

$attributes['HTTP_HOST'] = array($_SERVER['HTTP_HOST']);
$attributes['HTTPS'] = isset($_SERVER['HTTPS'])? array($_SERVER['HTTPS']) : array();
$attributes['SERVER_PROTOCOL'] = array($_SERVER['SERVER_PROTOCOL']);
$attributes['SERVER_PORT'] = array($_SERVER['SERVER_PORT']);

$attributes['Utilities_getBaseURL()'] = array(\SimpleSAML\Utils\HTTP::getBaseURL());
$attributes['Utilities_getSelfHost()'] = array(\SimpleSAML\Utils\HTTP::getSelfHost());
$attributes['Utilities_selfURLhost()'] = array(\SimpleSAML\Utils\HTTP::getSelfURLHost());
$attributes['Utilities_selfURLNoQuery()'] = array(\SimpleSAML\Utils\HTTP::getSelfURLNoQuery());
$attributes['Utilities_getSelfHostWithPath()'] = array(\SimpleSAML\Utils\HTTP::getSelfHostWithPath());
$attributes['Utilities_getFirstPathElement()'] = array(\SimpleSAML\Utils\HTTP::getFirstPathElement());
$attributes['Utilities_selfURL()'] = array(\SimpleSAML\Utils\HTTP::getSelfURL());

$template = new SimpleSAML_XHTML_Template($config, 'hostnames.php');

$template->data['remaining']  = $session->getAuthData('admin', 'Expire') - time();
$template->data['attributes'] = $attributes;
$template->data['valid'] = 'na';
$template->data['logout'] = null;

$template->show();
