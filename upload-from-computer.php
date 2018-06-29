<?php
/**
 * Uploading files from computer, step 1
 * Shows the plupload form that handles the uploads and moves
 * them to a temporary folder. When the queue is empty, the user
 * is redirected to step 2, and prompted to enter the name,
 * description and client for each uploaded file.
 *
 * @package ProjectSend
 * @subpackage Upload
 */
require_once('bootstrap.php');

$active_nav = 'files';

$page_title = __('Upload files', 'cftp_admin');

$allowed_levels = array(9,8,7);
if (CLIENTS_CAN_UPLOAD == 1) {
	$allowed_levels[] = 0;
}

include_once ADMIN_TEMPLATES_DIR . DS . 'header.php';
?>

<div class="col-xs-12">
	<?php
		/** Count the clients to show an error or the form */
		$statement		= $dbh->query("SELECT id FROM " . TABLE_USERS . " WHERE level = '0'");
		$count_clients	= $statement->rowCount();
		$statement		= $dbh->query("SELECT id FROM " . TABLE_GROUPS);
		$count_groups	= $statement->rowCount();

		if ( ( !$count_clients or $count_clients < 1 ) && ( !$count_groups or $count_groups < 1 ) ) {
			message_no_clients();
		}
	?>
		<p>
			<?php
				$msg = __('Click on Add files to select all the files that you want to upload, and then click continue. On the next step, you will be able to set a name and description for each uploaded file. Remember that the maximum allowed file size (in mb.) is ','cftp_admin') . ' <strong>'.UPLOAD_MAX_FILESIZE.'</strong>';
				echo system_message('info', $msg);
			?>
		</p>

		<script type="text/javascript">
			$(document).ready(function() {
				setInterval(function(){
					// Send a keep alive action every 1 minute
					var timestamp = new Date().getTime()
					$.ajax({
						type:	'GET',
						cache:	false,
						url:	'<?php echo INCLUDES_URI; ?>/session.keep.alive.php',
						data:	'timestamp='+timestamp,
						success: function(result) {
							var dummy = result;
						}
					});
				},1000*60);
			});

			$(function() {
				$("#uploader").pluploadQueue({
					runtimes : 'html5,flash,silverlight,html4',
					url : 'app/includes/upload.process.php',
					chunk_size : '1mb',
					rename : true,
					dragdrop: true,
					multipart : true,
					filters : {
						max_file_size : '<?php echo UPLOAD_MAX_FILESIZE; ?>mb'
						<?php
							if ( false === CAN_UPLOAD_ANY_FILE_TYPE ) {
						?>
								,mime_types: [
									{title : "Allowed files", extensions : "<?php echo ALLOWED_FILE_TYPES; ?>"}
								]
						<?php
							}
						?>
					},
					flash_swf_url : 'vendor/moxiecode/plupload/js/Moxie.swf',
					silverlight_xap_url : 'vendor/moxiecode/plupload/js/Moxie.xap',
					preinit: {
						Init: function (up, info) {
							//$('#uploader_container').removeAttr("title");
						}
					}
					,init : {
						/*
						FilesAdded: function(up, files) {
					   uploader.start();
					 }
						QueueChanged: function(up) {
							var uploader = $('#uploader').pluploadQueue();
							uploader.start();
						}
						*/
					}
				});

				var uploader = $('#uploader').pluploadQueue();

				$('form').submit(function(e) {

					if (uploader.files.length > 0) {
						uploader.bind('StateChanged', function() {
							if (uploader.files.length === (uploader.total.uploaded + uploader.total.failed)) {
								$('form')[0].submit();
							}
						});

						uploader.start();

						$("#btn-submit").hide();
						$(".message_uploading").fadeIn();

						uploader.bind('FileUploaded', function (up, file, info) {
							var obj = JSON.parse(info.response);
							var new_file_field = '<input type="hidden" name="files[]" value="'+obj.NewFileName+'" />'
							$('form').append(new_file_field);
						});

						return false;
					} else {
						alert('<?php _e("You must select at least one file to upload.",'cftp_admin'); ?>');
					}

					return false;
				});

				window.onbeforeunload = function (e) {
					var e = e || window.event;

					console.log('state? ' + uploader.state);

					// if uploading
					if(uploader.state === 2) {
						<?php
							$confirmation_msg = "Are you sure? Files currently being uploaded will be discarded if you leave this page.";
						?>
						//IE & Firefox
						if (e) {
							e.returnValue = '<?php _e($confirmation_msg,'cftp_admin'); ?>';
						}

						// For Safari
						return '<?php _e($confirmation_msg,'cftp_admin'); ?>';
					}

				};
			});
		</script>

		<?php include_once FORMS_DIR . DS . 'upload.php'; ?>
</div>

<?php
	include_once ADMIN_TEMPLATES_DIR . DS . 'footer.php';
