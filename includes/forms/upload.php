<?php
// Contains the form that is used to upload files
?>
<form action="files-edit.php" name="upload_form" id="upload_form" method="post" enctype="multipart/form-data">
    <?php addCsrf(); ?>
    <input type="hidden" name="uploaded_files" id="uploaded_files" value="" />
    <input type="hidden" name="editor_type" value="new_files" />
    <input type="hidden" name="selected_storage" id="selected_storage" value="" />
    <input type="hidden" name="encrypt_file" id="encrypt_file" value="0" />

    <div id="uploader">
        <div class="message message_error">
            <p><?php _e("Your browser doesn't support HTML5, Flash or Silverlight. Please update your browser or install Adobe Flash or Silverlight to continue.",'cftp_admin'); ?></p>
        </div>
    </div>
