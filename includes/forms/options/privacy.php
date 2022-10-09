<h3><?php _e('Privacy','cftp_admin'); ?></h3>

<div class="form-group row">
    <div class="col-sm-8 offset-sm-4">
        <label for="privacy_noindex_site">
            <input type="checkbox" value="1" name="privacy_noindex_site" id="privacy_noindex_site" class="checkbox_options" <?php echo (get_option('privacy_noindex_site') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e("Prevent search engines from indexing this site",'cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="form-group row">
    <div class="col-sm-8 offset-sm-4">
        <label for="enable_landing_for_all_files">
            <input type="checkbox" value="1" name="enable_landing_for_all_files" id="enable_landing_for_all_files" class="checkbox_options" <?php echo (get_option('enable_landing_for_all_files') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e("Enable information page for private files",'cftp_admin'); ?>
            <p class="field_note form-text"><?php _e("If enabled, the file information landing page will be available even for files that are not marked as private. Downloading them will stay restricted.",'cftp_admin'); ?></p>
        </label>
    </div>
</div>

<div class="options_divide"></div>

<h3><?php _e('Downloads','cftp_admin'); ?></h3>

<div class="form-group row">
    <label for="privacy_record_downloads_ip_address" class="col-sm-4 control-label"><?php _e('Log IP address and host for:','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <select class="form-select" name="privacy_record_downloads_ip_address" id="privacy_record_downloads_ip_address" required>
            <?php
                $orphan_options = [
                    'all' => __('All downloads','cftp_admin'),
                    'anonymous' => __('Anonymous users only','cftp_admin'),
                    'none' => __('Never record IP address and host','cftp_admin'),
                ];

                foreach ( $orphan_options as $value => $label ) {
            ?>
                    <option value="<?php echo $value; ?>"
                        <?php
                            if (get_option('privacy_record_downloads_ip_address') == $value) {
                                echo 'selected="selected"';
                            }
                        ?>
                        ><?php echo $label; ?>
                    </option>
            <?php
                }
            ?>
        </select>
    </div>
</div>

<div class="options_divide"></div>

<h3><?php _e('Public groups and files listings page','cftp_admin'); ?></h3>

<div class="form-group row">
    <div class="col-sm-8 offset-sm-4">
        <label for="public_listing_page_enable">
            <input type="checkbox" value="1" name="public_listing_page_enable" id="public_listing_page_enable" class="checkbox_options" <?php echo (get_option('public_listing_page_enable') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Enable page','cftp_admin'); ?>
        </label>
        <p class="field_note form-text"><?php _e('The url for the listings page is','cftp_admin'); ?><br>
        <a href="<?php echo PUBLIC_LANDING_URI; ?>" target="_blank" id="public_landing_uri"><?php echo PUBLIC_LANDING_URI; ?></a> <i class="fa fa-copy copy_text" data-target="public_landing_uri"></i></p>
    </div>
</div>

<div class="form-group row">
    <div class="col-sm-8 offset-sm-4">
        <label for="public_listing_logged_only">
            <input type="checkbox" value="1" name="public_listing_logged_only" id="public_listing_logged_only" class="checkbox_options" <?php echo (get_option('public_listing_logged_only') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Only for logged in clients','cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="form-group row">
    <div class="col-sm-8 offset-sm-4">
        <label for="public_listing_show_all_files">
            <input type="checkbox" value="1" name="public_listing_show_all_files" id="public_listing_show_all_files" class="checkbox_options" <?php echo (get_option('public_listing_show_all_files') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Inside groups show all files, including those that are not marked as public.','cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="form-group row">
    <div class="col-sm-8 offset-sm-4">
        <label for="public_listing_use_download_link">
            <input type="checkbox" value="1" name="public_listing_use_download_link" id="public_listing_use_download_link" class="checkbox_options" <?php echo (get_option('public_listing_use_download_link') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('On public files, show the download link.','cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="form-group row">
    <div class="col-sm-8 offset-sm-4">
        <label for="public_listing_enable_preview">
            <input type="checkbox" value="1" name="public_listing_enable_preview" id="public_listing_enable_preview" class="checkbox_options" <?php echo (get_option('public_listing_enable_preview') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Enable files previews','cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="options_divide"></div>
