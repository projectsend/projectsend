<?php
$this->data['header'] = $this->t('{aggregator:aggregator:aggregator_header}');
$this->includeAtTemplateBase('includes/header.php');

echo('<h1>Metarefresh fetch</h1>');


if (!empty($this->data['logentries'])) {
	
	echo '<pre style="border: 1px solid #aaa; padding: .5em; overflow: scroll">';
	foreach($this->data['logentries'] AS $l) {
		echo $l . "\n";		
	}
	echo '</pre>';
	
} else {
	echo 'No output from metarefresh.';
}



$this->includeAtTemplateBase('includes/footer.php');
