<?php

$this->data['jquery'] = array('core' => TRUE, 'ui' => TRUE, 'css' => TRUE);
$this->data['head']  = '<link rel="stylesheet" type="text/css" href="/' . $this->data['baseurlpath'] . 'module.php/metaedit/resources/style.css" />' . "\n";
$this->data['head'] .= '<script type="text/javascript">
$(document).ready(function() {
	$("#tabdiv").tabs();
});
</script>';

$this->includeAtTemplateBase('includes/header.php');


echo('<h1>OAuth Client</h1>');

echo($this->data['form']);

echo('<p style="float: right"><a href="registry.php">Return to entity listing <strong>without saving...</strong></a></p>');

$this->includeAtTemplateBase('includes/footer.php');

