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
$plupload = 1;
require_once('sys.includes.php');

$active_nav = 'files';

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

$database->MySQLDB();
?>

<div id="main">
	<h2><?php echo $page_title; ?></h2>
	
	<?php
		/** Count the clients to show an error or the form */
		$sql = $database->query("SELECT * FROM tbl_users WHERE level = '0'");
		$count = mysql_num_rows($sql);
		if (!$count) {
			/** Echo the no clients default message */
			message_no_clients();
		}
		else { 
	?>
			<p>
				<?php
					_e('Click on Add files to select all the files that you want to upload, and then click continue. On the next step, you will be able to set a name and description for each uploaded file. Remember that the maximum allowed file size (in mb.) is ','cftp_admin');
					echo '<strong>'.MAX_FILESIZE.'</strong>.';
				?>
			</p>

			<?php
				/**
				 * Load a plupload translation file, if the ProjectSend language
				 * on sys.config.php is set to anything other than "en", and the
				 * corresponding plupload file exists.
				 */
				if(SITE_LANG != 'en') {
					$plupload_lang_file = 'includes/plupload/js/i18n/'.SITE_LANG.'.js';
					if(file_exists($plupload_lang_file)) {
						echo '<script type="text/javascript" src="'.BASE_URI.$plupload_lang_file.'"></script>';
					}
				}
			?>

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
						max_file_size : '<?php echo MAX_FILESIZE; ?>mb',
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
                            //IE & Firefox
                            if (e) {
                                e.returnValue = '<?php _e("Are you sure? Files currently being uploaded will be discarded if you leave this page.",'cftp_admin'); ?>';
                            }

                            // For Safari
                            return '<?php _e("Are you sure? Files currently being uploaded will be discarded if you leave this page.",'cftp_admin'); ?>';
                        }

                    };
				});
			</script>
			
			<form action="upload-process-form.php" name="upload_by_client" id="upload_by_client" method="post" enctype="multipart/form-data">
				<input type="hidden" name="uploaded_files" id="uploaded_files" value="" />
				<div id="uploader">
					<div class="message message_error">
						<p><?php _e("Your browser doesn't support HTML5, Flash or Silverlight. Please update your browser or install Adobe Flash or Silverlight to continue.",'cftp_admin'); ?></p>
					</div>
				</div>
				<div class="after_form_buttons">
					<button type="submit" name="Submit" class="btn btn-wide btn-primary" id="btn-submit"><?php _e('Upload files','cftp_admin'); ?></button>
				</div>
				<div class="message message_info message_uploading">
					<p><?php _e("Your files are being uploaded! Progress indicators may take a while to update, but work is still being done behind the scenes.",'cftp_admin'); ?></p>
				</div>
			</form>
	
	<?php
		/** End if for users count */
		}
	?>

</div>

<?php
	$database->Close();
	include('footer.php');
?>