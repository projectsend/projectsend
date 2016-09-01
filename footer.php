	<?php
	/**
	 * Footer for the backend. Outputs the default mark up and
	 * information generated on functions.php.
	 *
	 * @package ProjectSend
	 */
	default_footer_info();
	
	//echo $dbh->GetCount(); // Print the total count of queries made by PDO

	?>

	<script src="<?php echo BASE_URI; ?>assets/bootstrap/js/bootstrap.min.js"></script>
	<script src="<?php echo BASE_URI; ?>includes/js/jquery.validations.js"></script>
	<script src="<?php echo BASE_URI; ?>includes/js/jquery.psendmodal.js"></script>

	<?php if (isset($datepicker)) { ?>
		<script src="<?php echo BASE_URI; ?>includes/js/bootstrap-datepicker/js/bootstrap-datepicker.js"></script>
	<?php } ?>

	<?php if (isset($spinedit)) { ?>
		<script src="<?php echo BASE_URI; ?>includes/js/bootstrap-spinedit/bootstrap-spinedit.js"></script>
	<?php } ?>

	<?php if (isset($footable)) { ?>
		<script src="<?php echo BASE_URI; ?>includes/js/footable/footable.all.min.js"></script>
	<?php } ?>

	<?php if (isset($jquery_tags_input)) { ?>
		<script src="<?php echo BASE_URI; ?>includes/js/jquery-tags-input/jquery.tagsinput.min.js"></script>
	<?php } ?>

	<?php if (isset($multiselect)) { ?>
		<script src="<?php echo BASE_URI; ?>includes/js/chosen/chosen.jquery.min.js"></script>
	<?php } ?>
	

	<?php if (isset($plupload)) { ?>
		<script src="<?php echo BASE_URI; ?>includes/js/browserplus-min.js"></script>
		<script src="<?php echo BASE_URI; ?>includes/plupload/js/plupload.full.js"></script>
		<script src="<?php echo BASE_URI; ?>includes/plupload/js/jquery.plupload.queue/jquery.plupload.queue.js"></script>
	<?php } ?>

	<?php if (isset($flot)) { ?>
		<!--[if lt IE 9]><script language="javascript" type="text/javascript" src="<?php echo BASE_URI; ?>includes/flot/excanvas.js"></script><![endif]-->
		<script src="<?php echo BASE_URI; ?>includes/flot/jquery.flot.min.js"></script>
		<script src="<?php echo BASE_URI; ?>includes/flot/jquery.flot.resize.min.js"></script>
		<script src="<?php echo BASE_URI; ?>includes/flot/jquery.flot.time.min.js"></script>
	<?php } ?>

	</body>
</html>
<?php ob_end_flush(); ?>