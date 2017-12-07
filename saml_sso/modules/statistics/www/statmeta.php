<?php

$config = SimpleSAML_Configuration::getInstance();
$statconfig = SimpleSAML_Configuration::getConfig('module_statistics.php');

sspmod_statistics_AccessCheck::checkAccess($statconfig);

$aggr = new sspmod_statistics_Aggregator();
$aggr->loadMetadata();
$metadata = $aggr->getMetadata();


$t = new SimpleSAML_XHTML_Template($config, 'statistics:statmeta-tpl.php');
$t->data['metadata'] =  $metadata;
$t->show();

