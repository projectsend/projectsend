<?php
$this->data['header'] = 'SimpleSAMLphp Statistics';

$this->data['jquery'] = array('core' => TRUE, 'ui' => TRUE, 'css' => TRUE);

$this->data['head'] ='';
$this->data['head'] .= '<script type="text/javascript">
$(document).ready(function() {
	$("#tabdiv").tabs();
});
</script>';

$this->includeAtTemplateBase('includes/header.php');


function getBaseURL($t, $type = 'get', $key = NULL, $value = NULL) {
	$vars = array(
		'rule' => $t->data['selected.rule'],
		'time' => $t->data['selected.time'],
		'res' => $t->data['selected.timeres'],
	);
	if (isset($t->data['selected.delimiter'])) $vars['d'] = $t->data['selected.delimiter'];
	if (!empty($t->data['selected.rule2']) && $t->data['selected.rule2'] !== '_') $vars['rule2'] = $t->data['selected.rule2'];
	
	if (isset($key)) {
		if (isset($vars[$key])) unset($vars[$key]);
		if (isset($value)) $vars[$key] = $value;
	}

	if ($type === 'get') {
		return 'showstats.php?' . http_build_query($vars, '', '&amp;');
	} else {
		$text = '';
		foreach($vars AS $k => $v) {
			$text .= '<input type="hidden" name="' . $k . '" value="'. htmlspecialchars($v) . '" />' . "\n";
		}
		return $text;
	}
	
}



?>

	<style type="text/css" media="all">
.ui-tabs-panel { padding: .5em }
div#content {
	margin: .4em ! important;
}
.tableview {
	border-collapse: collapse;
	border: 1px solid #ccc;
	margin: 1em;
	width: 80%;
}
.tableview th, .tableview td{
	border: 1px solid: #ccc;
	padding: 0px 5px;
}
.tableview th {
	background: #e5e5e5;
}
.tableview tr.total td {
	color: #500; font-weight: bold;
}
.tableview tr.even td {
	background: #f5f5f5;
	border-top: 1px solid #e0e0e0;
	border-bottom: 1px solid #e0e0e0;
}
.tableview th.value, .tableview td.value {
	text-align: right;
}
div.corner_t {
    max-width: none ! important;
}
table.timeseries tr.odd td {
	background-color: #f4f4f4;
}
table.timeseries td {
	padding-right: 2em; border: 1px solid #ccc
}
td.datacontent {
	text-align: right;
}
	</style>

<?php
echo('<h1>'. $this->data['available.rules'][$this->data['selected.rule']]['name'] . '</h1>');
echo('<p>' . $this->data['available.rules'][$this->data['selected.rule']]['descr'] . '</p>');

// Report settings
echo '<table class="selecttime" style="width: 100%; border: 1px solid #ccc; background: #eee; margin: 1px 0px; padding: 0px">';
echo('<tr><td style="width: 50px; padding: 0px"><img style="margin: 0px" src="../../resources/icons/crystal_project/kchart.32x32.png" alt="Report settings" /></td>');

// Select report
echo '<td>';
echo '<form style="display: inline">';
echo getBaseURL($this, 'post', 'rule');
echo '<select onChange="submit();" name="rule">';
foreach ($this->data['available.rules'] AS $key => $rule) {
	if ($key === $this->data['selected.rule']) {
		echo '<option selected="selected" value="' . $key . '">' . $rule['name'] . '</option>';
	} else {
		echo '<option value="' . $key . '">' . $rule['name'] . '</option>';
	}
}
echo '</select></form>';
echo '</td>';


// Select delimiter
echo '<td style="text-align: right">';
echo '<form style="display: inline">';
echo getBaseURL($this, 'post', 'd');
echo '<select onChange="submit();" name="d">';
foreach ($this->data['availdelimiters'] AS $key => $delim) {

	$delimName = $delim;
	if(array_key_exists($delim, $this->data['delimiterPresentation'])) $delimName = $this->data['delimiterPresentation'][$delim];

	if ($key == '_') {
		echo '<option value="_">Total</option>';
	} elseif (isset($_REQUEST['d']) && $delim == $_REQUEST['d']) {
		echo '<option selected="selected" value="' . htmlspecialchars($delim) . '">' . htmlspecialchars($delimName) . '</option>';
	} else {
		echo '<option  value="' . htmlspecialchars($delim) . '">' . htmlspecialchars($delimName) . '</option>';
	}
}
echo '</select></form>';
echo '</td>';

echo '</table>';

// End report settings




// Select time and date
echo '<table class="selecttime" style="width: 100%; border: 1px solid #ccc; background: #eee; margin: 1px 0px; padding: 0px">';
echo('<tr><td style="width: 50px; padding: 0px"><img style="margin: 0px" src="../../resources/icons/crystal_project/date.32x32.png" alt="Select date and time" /></td>');






if (isset($this->data['available.times.prev'])) {

	echo('<td style=""><a href="' . getBaseURL($this, 'get', 'time', $this->data['available.times.prev']) . '">« Previous</a></td>');
} else {
	echo('<td style="color: #ccc">« Previous</td>');
}


echo '<td style="text-align: right">';
echo '<form style="display: inline">';
echo getBaseURL($this, 'post', 'res');
echo '<select onChange="submit();" name="res">';
foreach ($this->data['available.timeres'] AS $key => $timeresname) {
	if ($key == $this->data['selected.timeres']) {
		echo '<option selected="selected" value="' . $key . '">' . $timeresname . '</option>';
	} else {
		echo '<option  value="' . $key . '">' . $timeresname . '</option>';
	}
}
echo '</select></form>';
echo '</td>';


echo '<td style="text-align: left">';
echo '<form style="display: inline">';
echo getBaseURL($this, 'post', 'time');
echo '<select onChange="submit();" name="time">';
foreach ($this->data['available.times'] AS $key => $timedescr) {
	if ($key == $this->data['selected.time']) {
		echo '<option selected="selected" value="' . $key . '">' . $timedescr . '</option>';
	} else {
		echo '<option  value="' . $key . '">' . $timedescr . '</option>';
	}
}
echo '</select></form>';
echo '</td>';

if (isset($this->data['available.times.next'])) {
	echo('<td style="text-align: right; padding-right: 4px"><a href="' . getBaseURL($this, 'get', 'time', $this->data['available.times.next']) . '">Next »</a></td>');
} else {
	echo('<td style="color: #ccc; text-align: right; padding-right: 4px">Next »</td>');
}




echo '</tr></table>';







echo '<div id="tabdiv"><ul class="tabset_tabs">
   <li><a href="#graph">Graph</a></li>
   <li><a href="#table">Summary table</a></li>
   <li><a href="#debug">Time serie</a></li>
</ul>';
echo '

<div id="graph" class="tabset_content">';


echo '<img src="' . htmlspecialchars($this->data['imgurl']) . '" />';


echo '<form style="display: inline">';
echo('<p style="text-align: right">Compare with total from this dataset ');
echo getBaseURL($this, 'post', 'rule2');
echo '<select onChange="submit();" name="rule2">';
echo '	<option value="_">None</option>';
foreach ($this->data['available.rules'] AS $key => $rule) {
	if ($key === $this->data['selected.rule2']) {
		echo '<option selected="selected" value="' . $key . '">' . $rule['name'] . '</option>';
	} else {
		echo '<option value="' . $key . '">' . $rule['name'] . '</option>';
	}
}
echo '</select></form>';


echo '</div>'; # end graph content.



/**
 * Handle table view - - - - - - 
 */
$classint = array('odd', 'even'); $i = 0;
echo '<div id="table" class="tabset_content">';

if (isset($this->data['pieimgurl'])) {
	echo('<img src="' . $this->data['pieimgurl'] . '" />');
}
echo '<table class="tableview"><tr><th class="value">Value</th><th class="category">Data range</th>';

foreach ( $this->data['summaryDataset'] as $key => $value ) {
	$clint = $classint[$i++ % 2];
	
	$keyName = $key;
	if(array_key_exists($key, $this->data['delimiterPresentation'])) $keyName = $this->data['delimiterPresentation'][$key];

	if ($key === '_') {
	    echo '<tr class="total '  . $clint . '"><td  class="value">' . $value . '</td><td class="category">' . $keyName . '</td></tr>';
    } else {
	    echo '<tr class="' . $clint . '"><td  class="value">' . $value . '</td><td class="category">' . $keyName . '</td></tr>';
    }
}

echo '</table></div>';
//  - - - - - - - End table view - - - - - - - 

echo '<div id="debug" >';
echo '<table class="timeseries" style="">';
echo('<tr><th>Time</th><th>Total</th>');
foreach($this->data['topdelimiters'] AS $key) {
	$keyName = $key;
	if(array_key_exists($key, $this->data['delimiterPresentation'])) $keyName = $this->data['delimiterPresentation'][$key];
 	echo('<th>' . $keyName . '</th>');
}
echo('</tr>');


$i = 0;
foreach ($this->data['debugdata'] AS $slot => $dd) {
	echo('<tr class="' . ((++$i % 2) == 0 ? 'odd' : 'even') . '">');
	echo('<td style="">' . $dd[0] . '</td>');	
	echo('<td class="datacontent">' . $dd[1] . '</td>');

	foreach($this->data['topdelimiters'] AS $key) {
		echo('<td class="datacontent">' . 
			(array_key_exists($key, $this->data['results'][$slot]) ? $this->data['results'][$slot][$key] : '&nbsp;') . 
			'</td>');
	}
	echo('</tr>');
}
echo '</table>';


echo '</div>'; # End debug tab content
echo('</div>'); # End tab div



$this->includeAtTemplateBase('includes/footer.php');

