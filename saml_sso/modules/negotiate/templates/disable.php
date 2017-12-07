<?php

/**
 *
 *
 * @author Mathias Meisfjordskar, University of Oslo.
 *         <mathias.meisfjordskar@usit.uio.no>
 * @package SimpleSAMLphp
 */
$this->includeAtTemplateBase('includes/header.php');
?>
<h1><?php echo $this->t('{negotiate:negotiate:disable_title}'); ?></h1>
<?php
$url = SimpleSAML_Module::getModuleURL('negotiate/enable.php');
?>
<?php echo $this->t('{negotiate:negotiate:disable_info_pre}', array('URL' => htmlspecialchars($url))); ?>

<?php echo $this->t('{negotiate:negotiate:info_post}'); ?>

<?php $this->includeAtTemplateBase('includes/footer.php');
