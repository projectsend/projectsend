	<?php
	/**
	 * Footer for the backend. Outputs the default mark up and
	 * information generated on functions.php.
	 *
	 * @package ProjectSend
	 */
		default_footer_info();
		//echo $dbh->GetCount(); // Print the total count of queries made by PDO

		if ( !empty( $load_compat_js_files ) ) {
			foreach ( $load_compat_js_files as $index => $info ) {
	?>
				<!--[if <?php echo $info['cond']; ?>]><script language="javascript" type="text/javascript" src="<?php echo $info['file']; ?>"></script><![endif]-->
	<?php
			}
		}

		if ( !empty( $load_js_files ) ) {
			foreach ( $load_js_files as $file ) {
	?>
				<script src="<?php echo $file; ?>"></script>
	<?php
			}
		}
	?>

	</body>
</html>
<?php ob_end_flush(); ?>