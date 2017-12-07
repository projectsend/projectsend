<?php

$config = SimpleSAML_Configuration::getInstance();
$statconfig = SimpleSAML_Configuration::getConfig('module_statistics.php');
$session = SimpleSAML_Session::getSessionFromRequest();


sspmod_statistics_AccessCheck::checkAccess($statconfig);


/*
 * Check input parameters
 */
$preferRule = NULL;
$preferRule2 = NULL;
$preferTime = NULL;
$preferTimeRes = NULL;
$delimiter = NULL;
if(array_key_exists('rule', $_REQUEST)) $preferRule = $_REQUEST['rule'];
if(array_key_exists('rule2', $_REQUEST)) $preferRule2 = $_REQUEST['rule2'];
if(array_key_exists('time', $_REQUEST)) $preferTime = $_REQUEST['time'];
if(array_key_exists('res', $_REQUEST)) $preferTimeRes = $_REQUEST['res'];
if(array_key_exists('d', $_REQUEST)) $delimiter = $_REQUEST['d'];

if ($preferRule2 === '_') $preferRule2 = NULL;

/*
 * Create statistics data.
 */
$ruleset = new sspmod_statistics_Ruleset($statconfig);
$statrule = $ruleset->getRule($preferRule);
$rule = $statrule->getRuleID();

$dataset = $statrule->getDataset($preferTimeRes, $preferTime);
$dataset->setDelimiter($delimiter);

$delimiter = $dataset->getDelimiter();

$timeres = $dataset->getTimeRes();
$fileslot = $dataset->getFileslot();
$availableFileSlots = $statrule->availableFileSlots($timeres);

$timeNavigation = $statrule->getTimeNavigation($timeres, $preferTime);

$dataset->aggregateSummary();
$dataset->calculateMax();




$piedata = $dataset->getPieData();

$datasets = array();
$datasets[] = $dataset->getPercentValues();

$axis = $dataset->getAxis();

$maxes = array();

$maxes[] = $dataset->getMax();


if (isset($preferRule2)) {
	$statrule = $ruleset->getRule($preferRule2);
	$dataset2 = $statrule->getDataset($preferTimeRes, $preferTime);
	$dataset2->aggregateSummary();
	$dataset2->calculateMax();
	
	$datasets[] = $dataset2->getPercentValues();
	$maxes[] = $dataset2->getMax();
}








$dimx = $statconfig->getValue('dimension.x', 800);
$dimy = $statconfig->getValue('dimension.y', 350);
$grapher = new sspmod_statistics_Graph_GoogleCharts($dimx, $dimy);

if (array_key_exists('output', $_REQUEST) && $_REQUEST['output'] === 'csv') {

	header('Content-type: text/csv');
	header('Content-Disposition: attachment; filename="simplesamlphp-data.csv"');
	$data = $dataset->getDebugData();
	foreach($data AS $de) {
		if (isset($de[1])) {
			echo('"' . $de[0] . '",' . $de[1] . "\n");
		}
	}
	exit;
}



$t = new SimpleSAML_XHTML_Template($config, 'statistics:statistics-tpl.php');
$t->data['pageid'] = 'statistics';
$t->data['header'] = 'stat';
$t->data['imgurl'] = $grapher->show($axis['axis'], $axis['axispos'], $datasets, $maxes);
if (isset($piedata)) {
	$t->data['pieimgurl'] = $grapher->showPie( $dataset->getDelimiterPresentationPie(), $piedata);
}
$t->data['available.rules'] = $ruleset->availableRulesNames();
$t->data['available.times'] = $statrule->availableFileSlots($timeres);
$t->data['available.timeres'] = $statrule->availableTimeRes();
$t->data['available.times.prev'] = $timeNavigation['prev'];
$t->data['available.times.next'] = $timeNavigation['next'];

$t->data['selected.rule']= $rule;
$t->data['selected.rule2']= $preferRule2;
$t->data['selected.time'] = $fileslot;
$t->data['selected.timeres'] = $timeres;
$t->data['selected.delimiter'] = $delimiter;

$t->data['debugdata'] = $dataset->getDebugData();
$t->data['results'] = $dataset->getResults();
$t->data['summaryDataset'] = $dataset->getSummary();
$t->data['topdelimiters'] = $dataset->getTopDelimiters();
$t->data['availdelimiters'] = $dataset->availDelimiters();

$t->data['delimiterPresentation'] =  $dataset->getDelimiterPresentation();
$t->show();

