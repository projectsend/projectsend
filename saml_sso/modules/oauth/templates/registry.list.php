<?php

$this->data['jquery'] = array('core' => TRUE, 'ui' => TRUE, 'css' => TRUE);
$this->data['head']  = '<link rel="stylesheet" type="text/css" href="/' . $this->data['baseurlpath'] . 'module.php/oauth/resources/style.css" />' . "\n";
$this->includeAtTemplateBase('includes/header.php');


echo('<h1>OAuth Client Registry</h1>');

echo('<p>Here you can register new OAuth Clients. You are successfully logged in as ' . htmlspecialchars($this->data['userid']) . '</p>');

echo('<h2>Your clients</h2>');
echo('<table class="metalist" style="width: 100%">');
$i = 0; $rows = array('odd', 'even');
foreach($this->data['entries']['mine'] AS $entryc ) {
	$entry = $entryc['value'];
	$i++; 
	echo('<tr class="' . $rows[$i % 2] . '">
		<td>' . htmlspecialchars($entry['name']) . '</td>
		<td><tt>' . htmlspecialchars($entry['key']) . '</tt></td>
		<td>
			<a href="registry.edit.php?editkey=' . urlencode($entry['key']) . '">edit</a>
			<a href="registry.php?delete=' . urlencode($entry['key']) . '">delete</a>
		</td></tr>');
}
if ($i == 0) {
	echo('<tr><td colspan="3">No entries registered</td></tr>');
}
echo('</table>');

echo('<p><a href="registry.edit.php">Add new client</a></p>');

echo('<h2>Other clients</h2>');
echo('<table class="metalist" style="width: 100%">');
$i = 0; $rows = array('odd', 'even');
foreach($this->data['entries']['others'] AS $entryc ) {
	$entry = $entryc['value'];
	$i++; 
	echo('<tr class="' . $rows[$i % 2] . '">
		<td>' . htmlspecialchars($entry['name']) . '</td>
		<td><tt>' . htmlspecialchars($entry['key']) . '</tt></td>
		<td>' . (isset($entry['owner']) ? htmlspecialchars($entry['owner']) : 'No owner') . '
		</td></tr>');
}
if ($i == 0) {
	echo('<tr><td colspan="3">No entries registered</td></tr>');
}
echo('</table>');

$this->includeAtTemplateBase('includes/footer.php');

