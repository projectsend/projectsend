<?php


$config = SimpleSAML_Configuration::getInstance();

$info = array();
$errors = array();
$hookinfo = array(
	'info' => &$info, 
	'errors' => &$errors,
);
SimpleSAML_Module::callHooks('sanitycheck', $hookinfo);


if (isset($_REQUEST['output']) && $_REQUEST['output'] == 'text') {
	
	if (count($errors) === 0) {
		echo 'OK';
	} else {
		echo 'FAIL';
	}
	exit;
}

$t = new SimpleSAML_XHTML_Template($config, 'sanitycheck:check-tpl.php');
$t->data['pageid'] = 'sanitycheck';
$t->data['errors'] = $errors;
$t->data['info'] = $info;
$t->show();
