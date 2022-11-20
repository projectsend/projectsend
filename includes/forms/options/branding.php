<h3><?php _e('Current logo','cftp_admin'); ?></h3>
<p><?php _e('Use this page to upload your company logo, or update the currently assigned one. This image will be shown to your clients when they access their file list.','cftp_admin'); ?></p>

<input type="hidden" name="MAX_FILE_SIZE" value="1000000000">

<div id="current_logo">
    <div id="current_logo_img">
        <?php
            if ($logo_file_info['exists'] === true) {
                /** Make the image */
                $logo = make_thumbnail($logo_file_info['dir'], LOGO_MAX_WIDTH, LOGO_MAX_HEIGHT);

                /** If the generator failed, use the original image */
                $img_src = ( !empty( $logo ) ) ? $logo['thumbnail']['url'] : $logo_file_info['url'];
            }
            else {
                $img_src = ASSETS_IMG_URL . '/projectsend-logo.png';
            }
        ?>
        <img src="<?php echo $img_src; ?>">
    </div>
    <p class="mt-3 text-info">
        <?php _e('This preview uses a maximum width of 300px.','cftp_admin'); ?>
    </p>
    <?php if (!empty(get_option('logo_filename'))) { ?>
        <div class="form-grup">
            <a class="btn btn-pslight confirm_generic" href="<?php echo BASE_URI . 'options.php?section=branding&clear=logo'; ?>"><?php _e('Delete current logo'); ?></a>
        </div>
    <?php } ?>
</div>

<div id="form_upload_logo">
    <div class="form-group row">
        <label class="col-sm-4 control-label"><?php _e('Select image to upload','cftp_admin'); ?></label>
        <div class="col-sm-8">
            <input type="file" name="select_logo" class="empty" accept=".jpg, .jpeg, .jpe, .gif, .png, .svg" />
        </div>
    </div>
</div>
