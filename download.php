<?php
/**
 * Serves the public downloads.
 *
 * @package		ProjectSend
 *
 */
$allowed_levels = array(9,8,7,0);
require_once 'bootstrap.php';

$page_title = __('File information','cftp_admin');

$dont_redirect_if_logged = 1;

include_once ADMIN_VIEWS_DIR . DS . 'header-unlogged.php';

	if (!empty($_GET['token']) && !empty($_GET['id'])) {
		$got_token		= $_GET['token'];
		$got_file_id	= $_GET['id'];

		$can_download = true;
		$can_view = false; // Can only view information about the file, not download it

		/**
		 * Get the user's id
		 */
		$sql_query = "SELECT * FROM " . TABLE_FILES . " WHERE id = :file_id AND BINARY public_token = :token";
		if ( ENABLE_LANDING_FOR_ALL_FILES != '1' ) {
			$sql_query .= " AND public_allow = '1'";
		}
		$statement = $dbh->prepare( $sql_query );
		$statement->bindParam(':token', $got_token);
		$statement->bindParam(':file_id', $got_file_id, PDO::PARAM_INT);
		$statement->execute();

		if ( $statement->rowCount() > 0 ){
			$statement->setFetchMode(PDO::FETCH_ASSOC);
			$got_url	= $statement->fetch();
			
			$is_public		= $got_url['public_allow'];
			$expires		= $got_url['expires'];
			$expiry_date	= $got_url['expiry_date'];

			$file_title			= htmlentities($got_url['filename']);
			$file_description	= htmlentities_allowed($got_url['description']);
			
			if ($expires == '1' && time() > strtotime($expiry_date)) {
				$can_download = false;
			}

			$real_file_url	= (!empty( $got_url['original_url'] ) ) ? $got_url['original_url'] : $got_url['url'];
			$file_on_disk	= $got_url['url'];
		}
		else {
			$can_download = false;
		}

		/** If landing for all files is enabled but the file is not public, do not allow download */
		if ( ENABLE_LANDING_FOR_ALL_FILES == '1' && $is_public == '0' ) {
			$can_download = false;
			$can_view = true;
		}
		
		if ($can_download == true) {
			if (!isset($_GET['download'])) {
				$download_link = BASE_URI . 'download.php?id=' . $got_file_id . '&token=' . $got_token . '&download';
			}
			else {
				/** Add the download row */
				$statement = $dbh->prepare("INSERT INTO " . TABLE_DOWNLOADS . " (file_id, remote_ip, remote_host, anonymous)"
											." VALUES (:file_id, :remote_ip, :remote_host, :anonymous)");
				$statement->bindParam(':file_id', $got_file_id, PDO::PARAM_INT);
				$statement->bindParam(':remote_ip', $_SERVER['REMOTE_ADDR']);
				$statement->bindParam(':remote_host', $_SERVER['REMOTE_HOST']);
				$statement->bindValue(':anonymous', 1, PDO::PARAM_INT);
				$statement->execute();

				/** Record the action log */
				$logger = new \ProjectSend\Classes\ActionsLog();
				$log_action_args = array(
										'action'				=> 37,
										'owner_id'				=> 0,
										'affected_file'			=> (int)$got_file_id,
										'affected_file_name'	=> $real_file_url,
									);
				$new_record_action = $logger->addEntry($log_action_args);

				// DOWNLOAD
				$real_file = UPLOADED_FILES_DIR.DS.basename($real_file_url);
				$random_file = realpath(UPLOADED_FILES_DIR.DS.basename($file_on_disk));
				if (file_exists($random_file)) {
					session_write_close();
					while (ob_get_level()) ob_end_clean();
					header('Content-Type: application/octet-stream');
					header('Content-Disposition: attachment; filename='.basename($real_file));
					header('Expires: 0');
					header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
					header('Pragma: public');
					header('Cache-Control: private',false);
					header('Content-Length: ' . get_real_size($random_file));
					header('Connection: close');
					//readfile($real_file);
					
					$context = stream_context_create();
					$file = fopen($random_file, 'rb', FALSE, $context);
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
	
	if ( $can_view && isset( $errorstate ) ) {
		unset( $errorstate );
	}
?>

<div class="col-xs-12 col-sm-12 col-lg-6 col-lg-offset-3">

	<?php echo get_branding_layout(true); ?>

	<div class="white-box">
		<div class="white-box-interior">
			<div class="text-center">
				<h3><?php echo $page_title; ?></h3>
			</div>

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
	
					echo system_message('danger',$login_err_message,'login_error');
				}
				
				if (isset($download_link)) {
				?>
					<div class="text-center">
						<p><?php _e('The following file is now ready for you to download:','cftp_admin'); ?><br /><strong><?php echo $real_file_url; ?></strong></p>
						<h3><?php echo $file_title; ?></h3>
						<div class="download_description">
							<?php echo $file_description; ?>
						</div>
						<a href="<?php echo $download_link; ?>" class="btn btn-primary">
							<?php _e('Download file','cftp_admin'); ?>
						</a>
					</div>
				<?php
				}

				if ( $can_view ) {
				?>
					<div class="text-center">
						<p><strong><?php echo $real_file_url; ?></strong></p>
						<h3><?php echo $file_title; ?></h3>
						<div class="download_description">
							<?php echo $file_description; ?>
						</div>
					</div>
				<?php
				}
			?>

		</div>
	</div>

	<div class="login_form_links">
		<p><a href="<?php echo BASE_URI; ?>" target="_self"><?php _e('Go back to the homepage.','cftp_admin'); ?></a></p>
	</div>
</div>

<?php
	include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
