<?php
/**
 * Show the form to request a drop-off
 *
 * @package		ProjectSend
 * @subpackage Upload
 * @subpackage	Request a drop-off
 *
 */

 
$allowed_levels = array(9,8,7,0);

require_once('sys.includes.php');
$active_nav = 'dropoff';
if (CLIENTS_CAN_UPLOAD == 1) {
	$allowed_levels[] = 0;
}
$page_title = __('Request a Drop-off','cftp_admin');

//include('header.php');
include('header-unlogged.php');


$auth = isset($_GET['auth']) ? htmlspecialchars($_GET['auth'],ENT_QUOTES, 'UTF-8') : '';
if(!empty($auth)){
	$sql = $dbh->prepare( 'SELECT * FROM '.TABLE_DROPOFF.' WHERE auth_key = "'.$auth.'"' );	

	$sql->execute();
	$sql->setFetchMode(PDO::FETCH_ASSOC);
	$grow = $sql->fetch();

	if($grow) {

	
			$from_id = $grow["from_id"];
			$to_name = $grow["to_name"];
			$to_subject_request = $grow["to_subject_request"];
			$from_organization = $grow["from_organization"];
			$to_email = $grow["to_email"];
			$to_note_request = $grow["to_note_request"];
			$status = $grow["status"];
		//var_dump($grow['from_id']);
	// upload form
	//-----------------start-------------------------------
?>
<div id="main">
	<div id="content"> 
    
    <!-- Added by B) -------------------->
    <div class="container-fluid">
      <div class="row">
        <div class="col-md-12">
  <h2><?php echo $page_title; ?></h2>
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
					url : 'dropoff-process-upload.php',
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

					if (uploader.files.length >= 1) {
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
							//console.log('dddddd');
						});

						return false;
					} else {
						alert('<?php _e("You must select at most one file to upload.",'cftp_admin'); ?>');
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
  <form action="upload-process-dropoff.php" name="upload_by_client" id="upload_by_client" method="post" enctype="multipart/form-data">
    <input type="hidden" name="uploaded_files" id="uploaded_files" value="" />
    <div id="uploader">
      <div class="message message_error">
        <p>
          <?php _e("Your browser doesn't support HTML5, Flash or Silverlight. Please update your browser or install Adobe Flash or Silverlight to continue.",'cftp_admin'); ?>
        </p>
      </div>
    </div>
    <input type="hidden" value="<?php echo $grow['to_email']; ?>" name="to_email" />
    <input type="hidden" value="<?php echo $grow['from_id']; ?>" name="fromid" />
    <input type="hidden" value="<?php echo $grow['to_name']; ?>" name="to_name" />
    <input type="hidden" value="<?php echo $grow['to_subject_request']; ?>" name="to_subject_request" />
    
    <div class="after_form_buttons">
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
	}
	else {
		header('Location:'.SYSTEM_URI);
		//header('Location:http://localhost:3333/frank-old/index.php');
	}
	//-----------------end---------------------------------
}
else {
	header('Location:'.SYSTEM_URI);
}

include('footer.php');

