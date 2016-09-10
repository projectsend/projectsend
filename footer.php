	<?php
	/**
	 * Footer for the backend. Outputs the default mark up and
	 * information generated on functions.php.
	 *
	 * @package ProjectSend
	 */
		default_footer_info();
		//echo $dbh->GetCount(); // Print the total count of queries made by PDO

		load_js_files();
	?>

	</body>
</html>
<?php ob_end_flush(); ?>