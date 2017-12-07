<?php

function tdate($input) {
	return date(DATE_RFC822, $input); 
}

function hours($input) {
	if ($input < 60) return number_format($input, 2) . ' sec';
	if ($input < 60*60) return number_format(($input/60),2) . ' min';
	if ($input < 24*60*60) return number_format(($input/(60*60)),2) . ' hours';
	return number_format($input/(24*60*60),2) . ' days';
	
}


function humanreadable($input) {
	 
	$output = "";
	$input = abs($input);
	
	if ($input >= (1024*1024*1024*1024*1024*1024*1024*100)) {
		$output = sprintf("%5ldEi", $input / (1024*1024*1024*1024*1024*1024) );		
	} else if ($input >= (1024*1024*1024*1024*1024*1024*10)) {
		$output = sprintf("%5.1fEi", $input / (1024.0*1024.0*1024.0*1024.0*1024.0*1024.0) );		
	} else if ($input >= (1024*1024*1024*1024*1024*1024)) {
		$output = sprintf("%5.2fEi", $input / (1024.0*1024.0*1024.0*1024.0*1024.0*1024.0) );	


	} else if ($input >= (1024*1024*1024*1024*1024*100)) {
		$output = sprintf("%5ldPi", $input / (1024*1024*1024*1024*1024) );		
	} else if ($input >= (1024*1024*1024*1024*1024*10)) {
		$output = sprintf("%5.1fPi", $input / (1024.0*1024.0*1024.0*1024.0*1024.0) );		
	} else if ($input >= (1024*1024*1024*1024*1024)) {
		$output = sprintf("%5.2fPi", $input / (1024.0*1024.0*1024.0*1024.0*1024.0) );	
		
	} else if ($input >= (1024*1024*1024*1024*100)) {
		$output = sprintf("%5ldTi", $input / (1024*1024*1024*1024) );
	} else if ($input >= (1024*1024*1024*1024*10)) {
		$output = sprintf("%5.1fTi", $input / (1024.0*1024.0*1024.0*1024.0) );	
	} else if ($input >= (1024*1024*1024*1024)) {
		$output = sprintf("%5.2fTi", $input / (1024.0*1024.0*1024.0*1024.0) );


	} else if ($input >= (1024*1024*1024*100)) {
		$output = sprintf("%5ldGi", $input / (1024*1024*1024) );		
	} else if ($input >= (1024*1024*1024*10)) {
		$output = sprintf("%5.1fGi", $input / (1024.0*1024.0*1024.0) );		
	} else if ($input >= (1024*1024*1024)) {
		$output = sprintf("%5.2fGi", $input / (1024.0*1024.0*1024.0) );	
		
	} else if ($input >= (1024*1024*100)) {
		$output = sprintf("%5ldMi", $input / (1024*1024) );
	} else if ($input >= (1024*1024*10)) {
		$output = sprintf("%5.1fM", $input / (1024.0*1024.0) );	
	} else if ($input >= (1024*1024)) {
		$output = sprintf("%5.2fMi", $input / (1024.0*1024.0) );		
		
	} else if ($input >= (1024 * 100)) {
		$output = sprintf("%5ldKi", $input / (1024) );
	} else if ($input >= (1024 * 10)) {
		$output = sprintf("%5.1fKi", $input / 1024.0 );
	} else if ($input >= (1024)) {
		$output = sprintf("%5.2fKi", $input / 1024.0 );
		
	} else {
		$output = sprintf("%5ld", $input );
	}

	return $output;
}




$config = SimpleSAML_Configuration::getInstance();

// Make sure that the user has admin access rights
SimpleSAML\Utils\Auth::requireAdmin();


$formats = array(
	'bytes' => 'humanreadable',
	'bytes_read' => 'humanreadable',
	'bytes_written' => 'humanreadable',
	'limit_maxbytes' => 'humanreadable',
	'time' => 'tdate',
	'uptime' => 'hours',
);

$statsraw = SimpleSAML_Memcache::getStats();

$stats = $statsraw;

foreach($stats AS $key => &$entry) {
	if (array_key_exists($key, $formats)) {
		$func = $formats[$key];
		foreach($entry AS $k => $val) {
			$entry[$k] = $func($val);
		}
	}

}

$template = new SimpleSAML_XHTML_Template($config, 'memcacheMonitor:memcachestat.tpl.php');
$template->data['title'] = 'Memcache stats';
$template->data['table'] = $stats;
$template->data['statsraw'] = $statsraw;
$template->show();
