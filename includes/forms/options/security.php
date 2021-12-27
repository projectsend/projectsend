<h3><?php _e('Allowed file extensions','cftp_admin'); ?></h3>
<p><?php _e('Be careful when changing this options. They could affect not only the system but the whole server it is installed on.','cftp_admin'); ?><br />
<strong><?php _e('Important','cftp_admin'); ?></strong>: <?php _e('Separate allowed file types with a comma.','cftp_admin'); ?></p>

<div class="form-group">
    <label for="file_types_limit_to" class="col-sm-4 control-label"><?php _e('Limit file types uploading to','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <select class="form-control" name="file_types_limit_to" id="file_types_limit_to" required>
            <option value="noone" <?php echo (get_option('file_types_limit_to') == 'noone') ? 'selected="selected"' : ''; ?>><?php _e('No one','cftp_admin'); ?></option>
            <option value="all" <?php echo (get_option('file_types_limit_to') == 'all') ? 'selected="selected"' : ''; ?>><?php _e('Everyone','cftp_admin'); ?></option>
            <option value="clients" <?php echo (get_option('file_types_limit_to') == 'clients') ? 'selected="selected"' : ''; ?>><?php _e('Clients only','cftp_admin'); ?></option>
        </select>
    </div>
</div>

<div class="form-group">
    <input name="allowed_file_types" id="allowed_file_types" value="<?php echo $allowed_file_types; ?>" required />
</div>

<?php
    if ( isset( $php_allowed_warning ) && $php_allowed_warning == true ) {
        $msg = __('Warning: php extension is allowed. This is a serious security problem. If you are not sure that you need it, please remove it from the list.','cftp_admin');
        echo system_message('danger',$msg);
    }
?>

<div class="options_divide"></div>

<h3><?php _e('SVG files','cftp_admin'); ?></h3>
<div class="form-group">
    <div class="col-sm-8 col-sm-offset-4">
        <label for="svg_show_as_thumbnail">
            <input type="checkbox" value="1" name="svg_show_as_thumbnail" id="svg_show_as_thumbnail" class="checkbox_options" <?php echo (get_option('svg_show_as_thumbnail') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Show thumbnails for SVG files','cftp_admin'); ?>
        </label>
    </div>
</div>


<div class="options_divide"></div>

<h3><?php _e('Passwords','cftp_admin'); ?></h3>
<p><?php _e('When setting up a password for an account, require at least:','cftp_admin'); ?></p>

<div class="form-group">
    <div class="col-sm-8 col-sm-offset-4">
        <label for="pass_require_upper">
            <input type="checkbox" value="1" name="pass_require_upper" id="pass_require_upper" class="checkbox_options" <?php echo (get_option('pass_require_upper') == 1) ? 'checked="checked"' : ''; ?> /> <?php echo $json_strings['validation']['req_upper']; ?>
        </label>
    </div>
</div>

<div class="form-group">
    <div class="col-sm-8 col-sm-offset-4">
        <label for="pass_require_lower">
            <input type="checkbox" value="1" name="pass_require_lower" id="pass_require_lower" class="checkbox_options" <?php echo (get_option('pass_require_lower') == 1) ? 'checked="checked"' : ''; ?> /> <?php echo $json_strings['validation']['req_lower']; ?>
        </label>
    </div>
</div>

<div class="form-group">
    <div class="col-sm-8 col-sm-offset-4">
        <label for="pass_require_number">
            <input type="checkbox" value="1" name="pass_require_number" id="pass_require_number" class="checkbox_options" <?php echo (get_option('pass_require_number') == 1) ? 'checked="checked"' : ''; ?> /> <?php echo $json_strings['validation']['req_number']; ?>
        </label>
    </div>
</div>

<div class="form-group">
    <div class="col-sm-8 col-sm-offset-4">
        <label for="pass_require_special">
            <input type="checkbox" value="1" name="pass_require_special" id="pass_require_special" class="checkbox_options" <?php echo (get_option('pass_require_special') == 1) ? 'checked="checked"' : ''; ?> /> <?php echo $json_strings['validation']['req_special']; ?>
        </label>
    </div>
</div>

<div class="options_divide"></div>

<h3><?php _e('reCAPTCHA','cftp_admin'); ?></h3>
<p><?php _e('Helps prevent SPAM on your login, registration and password forgotten forms.','cftp_admin'); ?></p>

<div class="form-group">
    <div class="col-sm-8 col-sm-offset-4">
        <label for="recaptcha_enabled">
            <input type="checkbox" value="1" name="recaptcha_enabled" id="recaptcha_enabled" class="checkbox_options" <?php echo (get_option('recaptcha_enabled') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Use reCAPTCHA','cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="form-group">
    <label for="recaptcha_site_key" class="col-sm-4 control-label"><?php _e('Site key','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="text" name="recaptcha_site_key" id="recaptcha_site_key" class="form-control" value="<?php echo html_output(get_option('recaptcha_site_key')); ?>" />
    </div>
</div>

<div class="form-group">
    <label for="recaptcha_secret_key" class="col-sm-4 control-label"><?php _e('Secret key','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="text" name="recaptcha_secret_key" id="recaptcha_secret_key" class="form-control" value="<?php echo html_output(get_option('recaptcha_secret_key')); ?>" />
    </div>
</div>

<div class="form-group">
    <div class="col-sm-8 col-sm-offset-4">
        <a href="<?php echo LINK_DOC_RECAPTCHA; ?>" class="external_link" target="_blank"><?php _e('How do I obtain this credentials?','cftp_admin'); ?></a>
    </div>
</div>
