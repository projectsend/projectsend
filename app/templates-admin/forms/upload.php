<?php
/**
 * Contains the form that is used to upload files
 *
 * @package		ProjectSend
 * @subpackage	Files
 *
 */
?>
<form action="upload-process-form.php" name="upload_by_client" id="upload_by_client" method="post" enctype="multipart/form-data">
	<input type="hidden" name="files" id="files" value="" />
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