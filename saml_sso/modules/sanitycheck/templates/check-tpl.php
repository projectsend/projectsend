<?php
$this->data['header'] = 'Sanity check';
$this->includeAtTemplateBase('includes/header.php');

?>

<h2><?php echo($this->data['header']); ?></h2>

<?php
if (count($this->data['errors']) > 0) {
?>
<div style="border: 1px solid #800; background: #caa; margin: 1em; padding: .5em">
<p><?php echo '<img class="float-r" src="/' . $this->data['baseurlpath'] . 'resources/icons/silk/delete.png" alt="Failed" />'; ?>	
These checks failed:</p>
<?php

	echo '<ul>';
	foreach ($this->data['errors'] AS $err) {
		echo '<li>' . $err . '</li>';
	}
	echo '</ul>';

echo '</div>';
}
?>

<?php
if (count($this->data['info']) > 0) {
?>
<div style="border: 1px solid #ccc; background: #eee; margin: 1em; padding: .5em">
<p><?php echo '<img class="float-r" src="/' . $this->data['baseurlpath'] . 'resources/icons/silk/accept.png" alt="OK" />'; ?>	
These checks succeeded:</p>
<?php
	echo '<ul>';
	foreach ($this->data['info'] AS $i) {
		echo '<li>' . $i . '</li>';
	}
	echo '</ul>';


echo '</div>';
}
?>

<?php $this->includeAtTemplateBase('includes/footer.php'); ?>