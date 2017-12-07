<?php

$this->data['head'] = '<style>
	table.statustable td, table.statustable th { 
		border: 1px solid #eee;
		padding: 2px 6px;
	}
	table.statustable {
		border-collapse: collapse;
	}
	.bmax {
		border: 1px solid #555;
		background: #eee;
	}
	.bused {
		border-right: 1px solid #555;
		border-bottom: 1px solid #555;
		color: white;
		background: #833;
	}
</style>
';



$this->includeAtTemplateBase('includes/header.php');

$title = $this->data['title'];
$table = $this->data['table'];


// Identify column headings
$column_titles = array();
foreach($table as $row_title => $row_data) {
	foreach($row_data as $ct => $foo) {
		if(!in_array($ct, $column_titles)) {
			$column_titles[] = $ct;
		}
	}
}

?>

<h2><?php echo htmlspecialchars($title); ?></h2>

<table class="statustable">

<tr>
<th></th>
<?php
foreach($column_titles as $ct) {
	echo '<th>' . htmlspecialchars($ct) . '</th>' . "\n";
}
?>
</tr>

<?php
foreach($table as $row_title => $row_data) {
	echo '<tr>' . "\n";
	echo '<th class="rowtitle" style="text-align: right">' . $this->t('{memcacheMonitor:memcachestat:' . $row_title . '}') . '</th>' . "\n";

	foreach($column_titles as $ct) {
		echo '<td>';

		if(array_key_exists($ct, $row_data)) {
			echo htmlspecialchars($row_data[$ct]);
		}

		echo '</td>' . "\n";
	}

	echo '</tr>' . "\n";
}
?>

</table>

<?php
if (array_key_exists('bytes', $this->data['statsraw']) && array_key_exists('limit_maxbytes', $this->data['statsraw'])) {
	foreach($this->data['statsraw']['bytes'] as $key => $row_data) {
		echo ('<h3>Storage usage on [' . $key . ']</h3>');
		$maxpix = 400;
		$pix = floor($this->data['statsraw']['bytes'][$key]*$maxpix / $this->data['statsraw']['limit_maxbytes'][$key]);
		
		echo('<div class="bmax" style="width: ' .  $maxpix. 'px"><div class="bused" style="width: ' . $pix . 'px">
		Used: ' . $table['bytes'][$key] . '
		</div>Total available: ' . $table['limit_maxbytes'][$key] . '</div>');
	}
}

$this->includeAtTemplateBase('includes/footer.php');
