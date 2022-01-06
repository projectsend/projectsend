<?php
/**
 * Contains the form that is used to upload files
 *
 * @package		ProjectSend
 * @subpackage	Files
 *
 */
?>
<form action="files-edit.php" name="upload_form" id="upload_form" method="post" enctype="multipart/form-data">
    <?php addCsrf(); ?>
    <input type="hidden" name="uploaded_files" id="uploaded_files" value="" />
    <input type="hidden" name="editor_type" value="new_files" />
    
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
