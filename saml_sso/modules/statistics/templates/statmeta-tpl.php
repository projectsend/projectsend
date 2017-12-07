<?php
$this->data['header'] = 'SimpleSAMLphp Statistics Metadata';
$this->includeAtTemplateBase('includes/header.php');

?>


<?php

echo('<table style="width: 100%">');

if (isset($this->data['metadata'])) {

	if (isset($this->data['metadata']['lastrun'] )) {
		echo('<tr><td>Aggregator last run at</td><td>' . 
			date('l jS \of F Y H:i:s', $this->data['metadata']['lastrun']) . 
			'</td></tr>');
	}

	if (isset($this->data['metadata']['notBefore'] )) {
		echo('<tr><td>Aggregated data until</td><td>' . 
			date('l jS \of F Y H:i:s', $this->data['metadata']['notBefore']) . 
			'</td></tr>');
	}
	
	if (isset($this->data['metadata']['memory'] )) {
		echo('<tr><td>Memory usage</td><td>' . 
			number_format($this->data['metadata']['memory'] / (1024*1024), 2) . ' MB' . 
			'</td></tr>');
	}
	if (isset($this->data['metadata']['time'] )) {
		echo('<tr><td>Execution time</td><td>' . 
			$this->data['metadata']['time'] . ' seconds' .
			'</td></tr>');
	}
	if (isset($this->data['metadata']['lastlinehash'] )) {
		echo('<tr><td>SHA1 of last processed logline</td><td>' . 
			$this->data['metadata']['lastlinehash'] .
			'</td></tr>');
	}
	if (isset($this->data['metadata']['lastline'] )) {
		echo('<tr><td>Last processed logline</td><td>' . 
			$this->data['metadata']['lastline'] .
			'</td></tr>');
	}
	
	
} else {
	echo('<tr><td>No metadata found</td></tr>');
}

echo('</table>');

echo('<p>[ <a href="showstats.php">Show statistics</a> ] </p>');

$this->includeAtTemplateBase('includes/footer.php');
