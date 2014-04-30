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
		$got_token		= mysql_real_escape_string($_GET['token']);
		$got_file_id	= $_GET['id'];

		$can_download = true;

		/**
		 * Get the user's id
		 */
		$query_file		= $database->query("SELECT * FROM tbl_files WHERE id = '" . (int)$got_file_id . "' AND public_allow = '1' AND BINARY public_token = '" . $got_token . "'");
		$count_request	= mysql_num_rows($query_file);

		if ($count_request > 0){
			$got_url		= mysql_fetch_array($query_file);

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

			if (isset($_POST['download'])) {
				// DOWNLOAD
				//Scarico se non ce la password, oppure se la password è uguale a quella impostata
				if(($got_url['password']=='')||($got_url['password'] == $_POST['download'])) {
					$real_file = UPLOADED_FILES_FOLDER.$real_file_url;
					if (file_exists($real_file)) {
						while (ob_get_level()) ob_end_clean();
						header('Content-Type: application/octet-stream');
						header('Content-Disposition: attachment; filename='.basename($real_file));
						header('Expires: 0');
						header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
						header('Pragma: public');
						header('Cache-Control: private',false);
						header('Content-Length: ' . get_real_size($real_file));
						header('Connection: close');
						readfile($real_file);
						die();
					}
				} else {
					$errorstate = 'password_invalid';
				}
			}
		}
		else {
			$errorstate = 'token_invalid';
		}
	}
?>

		<h2><?php echo $page_title; ?></h2>

		<div class="container">
			<div class="row">
				<div class="span4 offset4 white-box">
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
									case 'password_invalid':
										$login_err_message = __("The password is not valid.",'cftp_admin');
										break;
								}
				
								echo system_message('error',$login_err_message,'login_error');
							}
							
							if ($can_download) {
							?>
								<div class="text-center">
									<p><?php _e('The following file is now ready for you to download:','cftp_admin'); ?><br /><strong><?php echo $real_file_url; ?></strong></p>
									<form method="post" action="<?php echo BASE_URI . 'download.php?id=' . $got_file_id . '&token=' . $got_token ?>">
										<?php if($got_url['password']!=''): ?>
											<input type="text" placeholder="Insert password" name="download"><br>
										<?php else: ?>
											<input type="hidden" name="download" value="1">
										<?php endif ?>
										<!--<input type="hidden" name="id" value="<?php echo $got_file_id ?>">
										<input type="hidden" name="token" value="<?php echo $got_token ?>">-->
										<button type="submit" class="btn btn-primary"><?php _e('Download file','cftp_admin'); ?></button>
									</form>
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

	<?php default_footer_info(false); ?>

</body>
</html>
<?php
	$database->Close();
	ob_end_flush();
?>
