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
$load_scripts	= array(
						'plupload',
					); 

require_once('sys.includes.php');

$active_nav = 'files';
$cc_active_page = 'Send File';

$page_title = __('Upload files', 'cftp_admin');

$allowed_levels = array(9,8,7);

if (CLIENTS_CAN_UPLOAD == 1) {
	$allowed_levels[] = 0;
}
include('header.php');
/**
 * Get the user level to determine if the uploader is a
 * system user or a client.
 */
$current_level = get_current_user_level();
?>

<div id="main">
<div id="content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-md-12">
        <h2><i class="fa fa-upload" aria-hidden="true"></i>&nbsp;<?php echo $page_title; ?></h2>
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
				$msg = __('Click on Add files to select all the files that you want to upload, and then click continue. On the next step, you will be able to set a name and description for each uploaded file. Remember that the maximum allowed file size (in mb.) is ','cftp_admin') . ' <strong>'.MAX_FILESIZE.'</strong>';
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
						url:	'includes/ajax-keep-alive.php',
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
					url : 'process-upload.php',
					max_file_size : '2048mb',
					chunk_size : '1mb',
					multipart : true,
					<?php
						if ( false === CAN_UPLOAD_ANY_FILE_TYPE ) {
					?>
							filters : [
								{title : "Allowed files", extensions : "<?php echo $options_values['allowed_file_types']; ?>"}
							],
					<?php
						}
					?>
					flash_swf_url : 'includes/plupload/js/plupload.flash.swf',
					silverlight_xap_url : 'includes/plupload/js/plupload.silverlight.xap',
					preinit: {
						Init: function (up, info) {
							$('#uploader_container').removeAttr("title");
						}
					}
					/*
					,init : {
						QueueChanged: function(up) {
							var uploader = $('#uploader').pluploadQueue();
							uploader.start();
						}
					}
					*/
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
							var new_file_field = '<input type="hidden" name="finished_files[]" value="'+obj.NewFileName+'" />'
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
        <form action="upload-process-form.php" name="upload_by_client" id="upload_by_client" method="post" enctype="multipart/form-data">
          <input type="hidden" name="uploaded_files" id="uploaded_files" value="" />
          <div id="uploader">
            <div class="message message_error">
              <p>
                <?php _e("Your browser doesn't support HTML5, Flash or Silverlight. Please update your browser or install Adobe Flash or Silverlight to continue.",'cftp_admin'); ?>
              </p>
            </div>
          </div>
          <div class="cc-text-right after_form_buttons">
            <button type="submit" name="Submit" class="btn btn-wide btn-primary" id="btn-submit">
            <?php _e('Upload files','cftp_admin'); ?>
            </button>
          </div>
          <div class="message message_info message_uploading">
            <p>
              <?php _e("Your files are being uploaded! Progress indicators may take a while to update, but work is still being done behind the scenes.",'cftp_admin'); ?>
            </p>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
</div>
<?php
	include('footer.php');
?>
