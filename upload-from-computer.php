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

$page_title = __('Upload Files', 'cftp_admin');

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
				$msg = __('Click on Add files to select all the files that you want to upload, and then the next step click continue. On, you will be able to set a name and description for each uploaded file. Remember that the file may not be empty (0 KB) and the maximum allowed file size (in mb.) is ','cftp_admin') . ' <strong>'.MAX_FILESIZE.'</strong>';
				echo system_message('info', $msg);
			?>
        </p>
        <script type="text/javascript">
			$(document).ready(function() {
				$("form input[type=submit]").click(function() {
					//Seting up an event to emit an attribute clicked on clicking of submit button
					    $("input[type=submit]", $(this).parents("form")).removeAttr("clicked");
					    $(this).attr("clicked", "true");
					});
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

					// Setup html5 version
					$("#uploader").pluploadQueue({
						// General settings
						runtimes : 'html5,silverlight,html4',
						url : 'process-upload.php',
						chunk_size: '20mb',
						rename : true,
						dragdrop: true,

						filters : {
							mime_types : [
								{title : 'Files ', extensions :'7z,ace,ai,avi,bin,bmp,cdr,doc,docm,docx,eps,fla,flv,gif,htm,html,iso,jpeg,jpg,mp3,mp4,mpg,odt,oog,ppt,pptx,pptm,pps,ppsx,pdf,png,psd,rar,rtf,tar,tif,tiff,txt,wav,xls,xlsm,xlsx,zip'}
							],
							// Maximum file size
							max_file_size : '2048mb'}
					});


				var uploader = $('#uploader').pluploadQueue();

				$('form').submit(function(e) {
					var uptype = $("input[type=submit][clicked=true]").val();
					if (uptype== "Batch Upload**") {
						var zipfield = '<input type="hidden" id="zipload" name="batching" value="1" />';
						$('form').append(zipfield);
						$('form').attr('action', 'upload-zip.php');
					}
					// e.preventDefault();
				//	var fcount = $("#uploader_filelist").children().length;  $("#filecount").val(fcount);

						uploader.start();

						$("#btn-submit").hide();
						$("#zip-submit").hide();
					        $("#uploadbtnsnotes").hide();
						$(".message_uploading").fadeIn();

						uploader.bind('FileUploaded', function (up, file, info) {
							var obj = JSON.parse(info.response);
							var fname= obj.NewFileName;
							var ext = fname.substr( (fname.lastIndexOf('.') +1) );
							if(ext =='zip'){
								var uptype = $("input[type=submit][clicked=true]").val();
								if (uptype== "Batch Upload**") {
									$("#btn-submit").show();
									$("#zip-submit").show();
									$("#uploadbtnsnotes").show();
									var batchornot = confirm("Compressed files are not allowed in Batch upload. Continuing normal upload..!! ");
									if(batchornot == true){
										$("form")[0].reset();
										$('form').attr('action', 'upload-process-form.php');
									}
								}
							}
								var new_file_field = '<input type="hidden" name="finished_files[]" value="'+obj.NewFileName+'" />';
								$('form#upload_by_client').append(new_file_field);


						if (uploader.files.length > 0) {

							uploader.bind('StateChanged', function() {
								if (uploader.files.length === (uploader.total.uploaded + uploader.total.failed)) {
								 $('form')[0].submit();
								}
							});

						return false;
					} else {
						alert('<?php _e("You must select at least one file to upload.",'cftp_admin'); ?>');
					}
					});
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
          <input type="hidden" name="zipping" id="zipping" value="0" />
          <div id="uploader">
            <div class="message message_error">
              <p>
                <?php _e("Your browser doesn't support HTML5 or Silverlight. Please update your browser or install Silverlight to continue.",'cftp_admin'); ?>
              </p>
            </div>
          </div>
          <div class="cc-text-right after_form_buttons">
						<!-- <input id="filecount" type="hidden" name="filecount" > -->
            <input type="submit" class="btn btn-wide btn-primary" value="Upload Files*" id="btn-submit">
            <input type="submit" class="btn btn-wide btn-success" value="Batch Upload**" id="zip-submit">
	    <div id="uploadbtnsnotes">
               <p style="font-size: 11px">*Note: Nested compressed files not allowed.<br>**Note: Compressed files not allowed.</p>
            </div>
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
