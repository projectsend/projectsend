<?php
/**
 * Serves the public downloads.
 *
 * @package		ProjectSend
 *
 */
$allowed_levels = array(9,8,7,0);
require_once('sys.includes.php');

$page_title = __('Download','cftp_admin');

$dont_redirect_if_logged = 1;

include('header-unlogged.php');

	if (!empty($_GET['token']) && !empty($_GET['id'])) {
		$got_token		= $_GET['token'];
		$got_file_id	= $_GET['id'];

		$can_download = true;

		/**
		 * Get the user's id
		 */
		$statement = $dbh->prepare( "SELECT * FROM " . TABLE_FILES . " WHERE id = :file_id AND public_allow = '1' AND BINARY public_token = :token" );
		$statement->bindParam(':token', $got_token);
		$statement->bindParam(':file_id', $got_file_id, PDO::PARAM_INT);
		$statement->execute();

		if ( $statement->rowCount() > 0 ){
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			$got_url	= $statement->fetch();

			$expires		= $got_url['expires'];
			$expiry_date	= $got_url['expiry_date'];
			
			if ($expires == '1' && time() > strtotime($expiry_date)) {
				$can_download = false;
			}
		}
		else {
			$can_download = false;
		}
		
		if ($can_download == true) {
			$real_file_url	= $got_url['url'];

			if (!isset($_GET['download'])) {
				$download_link = BASE_URI . 'download.php?id=' . $got_file_id . '&token=' . $got_token . '&download';
			}
			else {
				// DOWNLOAD
				$real_file = UPLOADED_FILES_FOLDER.$real_file_url;
				if (file_exists($real_file)) {
					session_write_close(); 
					while (ob_get_level()) ob_end_clean();
					header('Content-Type: application/octet-stream');
					header('Content-Disposition: attachment; filename='.basename($real_file));
					header('Expires: 0');
					header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
					header('Pragma: public');
					header('Cache-Control: private',false);
					header('Content-Length: ' . get_real_size($real_file));
					header('Connection: close');
					//readfile($real_file);
					
					$context = stream_context_create();
					$file = fopen($real_file, 'rb', FALSE, $context);
					while ( !feof( $file ) ) {
						//usleep(1000000); //Reduce download speed
						echo stream_get_contents($file, 2014);
					}
					
					fclose( $file );
					die();
				}
			}
		}
		else {
			$errorstate = 'token_invalid';
		}
	} else {
		$errorstate = 'token_invalid';
	}
?>

		<h2><?php echo $page_title; ?></h2>

		<div class="container">
			<div class="row">
				<div class="col-xs-12 col-xs-offset-0 col-sm-8 col-sm-offset-2 col-md-4 col-md-offset-4 white-box">
					<div class="white-box-interior">
						<?php
							/**
							 * Show status message
							 */
							if (isset($errorstate)) {
								switch ($errorstate) {
									case 'token_invalid':
										$login_err_message = __("The request is not valid.",'cftp_admin');
										break;
								}
				
								echo system_message('error',$login_err_message,'login_error');
							}
							
							if (isset($download_link)) {
							?>
								<div class="text-center">
									<p><?php _e('The following file is now ready for you to download:','cftp_admin'); ?><br /><strong><?php echo $real_file_url; ?></strong></p>
									<a href="<?php echo $download_link; ?>" class="btn btn-primary">
										<?php _e('Download file','cftp_admin'); ?>
									</a>
								</div>
							<?php
							}
						?>

						<div class="login_form_links">
							<p><a href="<?php echo BASE_URI; ?>" target="_self"><?php _e('Go back to the homepage.','cftp_admin'); ?></a></p>
						</div>

					</div>
				</div>
			</div>
		</div> <!-- container -->
	</div> <!-- main (from header) -->

	<?php
		default_footer_info( false );

		load_js_files();
	?>

</body>
</html>
<?php
	ob_end_flush();
?>