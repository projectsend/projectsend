<h3><?php _e('Privacy','cftp_admin'); ?></h3>

<div class="form-group">
    <div class="col-sm-8 col-sm-offset-4">
        <label for="privacy_noindex_site">
            <input type="checkbox" value="1" name="privacy_noindex_site" id="privacy_noindex_site" class="checkbox_options" <?php echo (PRIVACY_NOINDEX_SITE == 1) ? 'checked="checked"' : ''; ?> /> <?php _e("Prevent search engines from indexing this site",'cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="form-group">
    <div class="col-sm-8 col-sm-offset-4">
        <label for="enable_landing_for_all_files">
            <input type="checkbox" value="1" name="enable_landing_for_all_files" id="enable_landing_for_all_files" class="checkbox_options" <?php echo (ENABLE_LANDING_FOR_ALL_FILES == 1) ? 'checked="checked"' : ''; ?> /> <?php _e("Enable information page for private files",'cftp_admin'); ?>
            <p class="field_note"><?php _e("If enabled, the file information landing page will be available even for files that are not marked as private. Downloading them will stay restricted.",'cftp_admin'); ?></p>
        </label>
    </div>
</div>

<div class="options_divide"></div>

<h3><?php _e('Public groups and files listings page','cftp_admin'); ?></h3>

<div class="form-group">
    <div class="col-sm-8 col-sm-offset-4">
        <label for="public_listing_page_enable">
            <input type="checkbox" value="1" name="public_listing_page_enable" id="public_listing_page_enable" class="checkbox_options" <?php echo (PUBLIC_LISTING_PAGE_ENABLE == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Enable page','cftp_admin'); ?>
        </label>
        <p class="field_note"><?php _e('The url for the listings page is','cftp_admin'); ?><br>
        <a href="<?php echo PUBLIC_LANDING_URI; ?>" target="_blank">
            <?php echo PUBLIC_LANDING_URI; ?>
        </a></p>
    </div>
</div>

<div class="form-group">
    <div class="col-sm-8 col-sm-offset-4">
        <label for="public_listing_logged_only">
            <input type="checkbox" value="1" name="public_listing_logged_only" id="public_listing_logged_only" class="checkbox_options" <?php echo (PUBLIC_LISTING_LOGGED_ONLY == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Only for logged in clients','cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="form-group">
    <div class="col-sm-8 col-sm-offset-4">
        <label for="public_listing_show_all_files">
            <input type="checkbox" value="1" name="public_listing_show_all_files" id="public_listing_show_all_files" class="checkbox_options" <?php echo (PUBLIC_LISTING_SHOW_ALL_FILES == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Inside groups show all files, including those that are not marked as public.','cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="form-group">
    <div class="col-sm-8 col-sm-offset-4">
        <label for="public_listing_use_download_link">
            <input type="checkbox" value="1" name="public_listing_use_download_link" id="public_listing_use_download_link" class="checkbox_options" <?php echo (PUBLIC_LISTING_USE_DOWNLOAD_LINK == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('On public files, show the download link.','cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="options_divide"></div>
