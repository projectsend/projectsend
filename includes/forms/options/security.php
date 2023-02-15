<h3><?php _e('Allowed file extensions','cftp_admin'); ?></h3>
<p><?php _e('Be careful when changing this options. They could affect not only the system but the whole server it is installed on.','cftp_admin'); ?><br />
<strong><?php _e('Important','cftp_admin'); ?></strong>: <?php _e('Separate allowed file types with a comma.','cftp_admin'); ?></p>

<div class="form-group row">
    <label for="file_types_limit_to" class="col-sm-4 control-label"><?php _e('Limit file types uploading to','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <select class="form-select" name="file_types_limit_to" id="file_types_limit_to" required>
            <option value="noone" <?php echo (get_option('file_types_limit_to') == 'noone') ? 'selected="selected"' : ''; ?>><?php _e('No one','cftp_admin'); ?></option>
            <option value="all" <?php echo (get_option('file_types_limit_to') == 'all') ? 'selected="selected"' : ''; ?>><?php _e('Everyone','cftp_admin'); ?></option>
            <option value="clients" <?php echo (get_option('file_types_limit_to') == 'clients') ? 'selected="selected"' : ''; ?>><?php _e('Clients only','cftp_admin'); ?></option>
        </select>
    </div>
</div>

<div class="form-group row">
    <input name="allowed_file_types" id="allowed_file_types" value="<?php echo get_option('allowed_file_types'); ?>" required />
</div>

<div class="options_divide"></div>

<h3><?php _e('SVG files','cftp_admin'); ?></h3>
<div class="form-group row">
    <div class="col-sm-8 offset-sm-4">
        <label for="svg_show_as_thumbnail">
            <input type="checkbox" value="1" name="svg_show_as_thumbnail" id="svg_show_as_thumbnail" class="checkbox_options" <?php echo (get_option('svg_show_as_thumbnail') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Show thumbnails for SVG files','cftp_admin'); ?>
        </label>
    </div>
</div>


<div class="options_divide"></div>

<h3><?php _e('Passwords','cftp_admin'); ?></h3>
<p><?php _e('When setting up a password for an account, require at least:','cftp_admin'); ?></p>

<div class="form-group row">
    <div class="col-sm-8 offset-sm-4">
        <label for="pass_require_upper">
            <input type="checkbox" value="1" name="pass_require_upper" id="pass_require_upper" class="checkbox_options" <?php echo (get_option('pass_require_upper') == 1) ? 'checked="checked"' : ''; ?> /> <?php echo $json_strings['validation']['req_upper']; ?>
        </label>
    </div>
</div>

<div class="form-group row">
    <div class="col-sm-8 offset-sm-4">
        <label for="pass_require_lower">
            <input type="checkbox" value="1" name="pass_require_lower" id="pass_require_lower" class="checkbox_options" <?php echo (get_option('pass_require_lower') == 1) ? 'checked="checked"' : ''; ?> /> <?php echo $json_strings['validation']['req_lower']; ?>
        </label>
    </div>
</div>

<div class="form-group row">
    <div class="col-sm-8 offset-sm-4">
        <label for="pass_require_number">
            <input type="checkbox" value="1" name="pass_require_number" id="pass_require_number" class="checkbox_options" <?php echo (get_option('pass_require_number') == 1) ? 'checked="checked"' : ''; ?> /> <?php echo $json_strings['validation']['req_number']; ?>
        </label>
    </div>
</div>

<div class="form-group row">
    <div class="col-sm-8 offset-sm-4">
        <label for="pass_require_special">
            <input type="checkbox" value="1" name="pass_require_special" id="pass_require_special" class="checkbox_options" <?php echo (get_option('pass_require_special') == 1) ? 'checked="checked"' : ''; ?> /> <?php echo $json_strings['validation']['req_special']; ?>
        </label>
    </div>
</div>

<div class="options_divide"></div>

<h3><?php _e('reCAPTCHA','cftp_admin'); ?></h3>
<p><?php _e('Helps prevent SPAM on your login, registration and password forgotten forms.','cftp_admin'); ?></p>

<div class="form-group row">
    <div class="col-sm-8 offset-sm-4">
        <label for="recaptcha_enabled">
            <input type="checkbox" value="1" name="recaptcha_enabled" id="recaptcha_enabled" class="checkbox_options" <?php echo (get_option('recaptcha_enabled') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Use reCAPTCHA','cftp_admin'); ?> <?php _e('(v2 currently supported)','cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="form-group row">
    <label for="recaptcha_site_key" class="col-sm-4 control-label"><?php _e('Site key','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="text" name="recaptcha_site_key" id="recaptcha_site_key" class="form-control" value="<?php echo html_output(get_option('recaptcha_site_key')); ?>" />
    </div>
</div>

<div class="form-group row">
    <label for="recaptcha_secret_key" class="col-sm-4 control-label"><?php _e('Secret key','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="text" name="recaptcha_secret_key" id="recaptcha_secret_key" class="form-control" value="<?php echo html_output(get_option('recaptcha_secret_key')); ?>" />
    </div>
</div>

<div class="form-group row">
    <div class="col-sm-8 offset-sm-4">
        <a href="<?php echo LINK_DOC_RECAPTCHA; ?>" class="external_link" target="_blank"><?php _e('How do I obtain this credentials?','cftp_admin'); ?></a>
    </div>
</div>

<div class="options_divide"></div>

<h3><?php _e('Authentication','cftp_admin'); ?></h3>
<div class="form-group row">
    <div class="col-sm-8 offset-sm-4">
        <label for="authentication_require_email_code">
            <input type="checkbox" value="1" name="authentication_require_email_code" id="authentication_require_email_code" class="checkbox_options" <?php echo (get_option('authentication_require_email_code') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Enable simple 2 factor authentication','cftp_admin'); ?>
            <p class="field_note form-text"><?php _e('If enabled, an email with a one time use verification code will be sent to the user after they enter their credentials.','cftp_admin'); ?></p>
        </label>
    </div>
</div>

<div class="options_divide"></div>

<h3><?php _e('Log in throttle','cftp_admin'); ?></h3>
<p><?php _e('Multiple failed log in attempts will increase timeouts for the originating IP address. Helps prevent brute force attacks.','cftp_admin'); ?></p>

<div class="form-group row">
    <label for="ip_whitelist" class="col-sm-4 control-label"><?php _e('IP whitelist','cftp_admin'); ?></label>
    <div class="col-sm-8 offset-sm-4">
        <textarea name="ip_whitelist" id="ip_whitelist" class="form-control textarea_medium"><?php echo html_output(get_option('ip_whitelist')); ?></textarea>
        <p class="field_note form-text"><?php _e('Enter one IP address per line','cftp_admin'); ?>.
    </div>
</div>

<div class="form-group row">
    <label for="ip_blacklist" class="col-sm-4 control-label"><?php _e('IP blacklist','cftp_admin'); ?></label>
    <div class="col-sm-8 offset-sm-4">
        <textarea name="ip_blacklist" id="ip_blacklist" class="form-control textarea_medium"><?php echo html_output(get_option('ip_blacklist')); ?></textarea>
        <p class="field_note form-text"><?php _e('Enter one IP address per line','cftp_admin'); ?>.
    </div>
</div>
