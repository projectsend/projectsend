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

    <div id="browser_encrypt_data">
        <div class="form-group">
            <label for="browser_encrypt">
                <input data-toggle="toggle" type="checkbox" value="1" name="browser_encrypt" id="browser_encrypt" class="checkbox_options" checked /> <?php _e('Use browser encryption during this upload session','cftp_admin'); ?>
            </label>
        </div>

        <div id="keys_list">
            <ul id="keys"></ul>
        </div>
    </div>

    <div class="after_form_buttons">
        <button type="button" class="btn btn-wide btn-primary" id="btn-submit"><?php _e('Upload files','cftp_admin'); ?></button>
    </div>

    <div class="message message_info message_uploading">
        <p><?php _e("Your files are being uploaded! Progress indicators may take a while to update, but work is still being done behind the scenes.",'cftp_admin'); ?></p>
    </div>
</form>

<div class="encrypt_warning hidden">
    <div class="alert alert-warning">
        <p><?php _e('Files will be encrypted during this upload session. Make sure to copy and safely store the random key for each file that will be used during download to decrypt them. ProjectSend does not record this keys anywhere.', 'cftp_admin'); ?></p>
    </div>
    <table class="files_keys">
        <thead>
            <tr>
                <th>File name</th>
                <th>Random key</th>
                <th></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
    <div class="encrypt_actions">
        <button id="upload_encrypt_continue" class="btn btn-wide btn-primary btn-lg"><?php _e('Encrypt and upload files','cftp_admin'); ?></button>
        <button id="upload_encrypt_cancel" class="btn btn-wide btn-default btn-lg"><?php _e('Modify files list','cftp_admin'); ?></button>
    </div>
</div>