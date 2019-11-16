<h3><?php _e('"From" information','cftp_admin'); ?></h3>

<div class="form-group">
    <label for="admin_email_address" class="col-sm-4 control-label"><?php _e('E-mail address','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="text" name="admin_email_address" id="admin_email_address" class="form-control" value="<?php echo html_output(ADMIN_EMAIL_ADDRESS); ?>" required />
    </div>
</div>

<div class="form-group">
    <label for="mail_from_name" class="col-sm-4 control-label"><?php _e('Name','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="text" name="mail_from_name" id="mail_from_name" class="form-control" value="<?php echo html_output(MAIL_FROM_NAME); ?>" required />
    </div>
</div>

<div class="options_divide"></div>

<h3><?php _e('Send copies','cftp_admin'); ?></h3>

<div class="form-group">
    <div class="col-sm-8 col-sm-offset-4">
        <label for="mail_copy_user_upload">
            <input type="checkbox" value="1" name="mail_copy_user_upload" id="mail_copy_user_upload" <?php echo (MAIL_COPY_USER_UPLOAD == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('When a system user uploads files','cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="form-group">
    <div class="col-sm-8 col-sm-offset-4">
        <label for="mail_copy_client_upload">
            <input type="checkbox" value="1" name="mail_copy_client_upload" id="mail_copy_client_upload" <?php echo (MAIL_COPY_CLIENT_UPLOAD == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('When a client uploads files','cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="options_nested_note">
    <p><?php _e('Define here who will receive copies of this emails. These are sent as BCC so neither recipient will see the other addresses.','cftp_admin'); ?></p>
</div>

<div class="form-group">
    <div class="col-sm-8 col-sm-offset-4">
        <label for="mail_copy_main_user">
            <input type="checkbox" value="1" name="mail_copy_main_user" class="mail_copy_main_user" <?php echo (MAIL_COPY_MAIN_USER == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Address supplied above (on "From")','cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="form-group">
    <label for="mail_copy_addresses" class="col-sm-4 control-label"><?php _e('Also to this addresses','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="text" name="mail_copy_addresses" id="mail_copy_addresses" class="mail_data form-control" value="<?php echo html_output(MAIL_COPY_ADDRESSES); ?>" />
        <p class="field_note"><?php _e('Separate e-mail addresses with a comma.','cftp_admin'); ?></p>
    </div>
</div>

<div class="options_divide"></div>

<h3><?php _e('Expiration','cftp_admin'); ?></h3>

<div class="form-group">
    <label for="notifications_max_tries" class="col-sm-4 control-label"><?php _e('Maximum sending attemps','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="number" name="notifications_max_tries" id="notifications_max_tries" class="form-control" value="<?php echo NOTIFICATIONS_MAX_TRIES; ?>" min="1" max="10" step="1" required />
        <p class="field_note"><?php _e('Define how many times will the system attemp to send each notification.','cftp_admin'); ?></p>
    </div>
</div>

<div class="form-group">
    <label for="notifications_max_days" class="col-sm-4 control-label"><?php _e('Days before expiring','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="number" name="notifications_max_days" id="notifications_max_days" class="form-control" value="<?php echo NOTIFICATIONS_MAX_DAYS; ?>" min="0" max="365" step="1" required />
        <p class="field_note"><?php _e('Notifications older than this will not be sent.','cftp_admin'); ?><br /><strong><?php _e('Set to 0 to disable.','cftp_admin'); ?></strong></p>
    </div>
</div>

<div class="options_divide"></div>

<h3><?php _e('E-mail sending options','cftp_admin'); ?></h3>
<p><?php _e('Here you can select which mail system will be used when sending the notifications. If you have a valid e-mail account, SMTP is the recommended option.','cftp_admin'); ?></p>

<div class="form-group">
    <label for="mail_system_use" class="col-sm-4 control-label"><?php _e('Mailer','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <select class="form-control" name="mail_system_use" id="mail_system_use" required>
            <option value="mail" <?php echo (MAIL_SYSTEM_USE == 'mail') ? 'selected="selected"' : ''; ?>>PHP Mail (basic)</option>
            <option value="smtp" <?php echo (MAIL_SYSTEM_USE == 'smtp') ? 'selected="selected"' : ''; ?>>SMTP</option>
            <option value="gmail" <?php echo (MAIL_SYSTEM_USE == 'gmail') ? 'selected="selected"' : ''; ?>>Gmail</option>
            <option value="sendmail" <?php echo (MAIL_SYSTEM_USE == 'sendmail') ? 'selected="selected"' : ''; ?>>Sendmail</option>
        </select>
    </div>
</div>

<div class="options_divide"></div>

<h3><?php _e('SMTP & Gmail shared options','cftp_admin'); ?></h3>
<p><?php _e('You need to include your username (usually your e-mail address) and password if you have selected either SMTP or Gmail as your mailer.','cftp_admin'); ?></p>

<div class="form-group">
    <label for="mail_smtp_user" class="col-sm-4 control-label"><?php _e('Username','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="text" name="mail_smtp_user" id="mail_smtp_user" class="mail_data form-control" value="<?php echo html_output(MAIL_SMTP_USER); ?>" />
    </div>
</div>

<div class="form-group">
    <label for="mail_smtp_pass" class="col-sm-4 control-label"><?php _e('Password','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="password" name="mail_smtp_pass" id="mail_smtp_pass" class="mail_data form-control" value="<?php echo html_output(MAIL_SMTP_PASS); ?>" />
    </div>
</div>

<div class="options_divide"></div>

<h3><?php _e('SMTP options','cftp_admin'); ?></h3>
<p><?php _e('If you selected SMTP as your mailer, please complete these options.','cftp_admin'); ?></p>

<div class="form-group">
    <label for="mail_smtp_host" class="col-sm-4 control-label"><?php _e('Host','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="text" name="mail_smtp_host" id="mail_smtp_host" class="mail_data form-control" value="<?php echo html_output(MAIL_SMTP_HOST); ?>" />
    </div>
</div>

<div class="form-group">
    <label for="mail_smtp_port" class="col-sm-4 control-label"><?php _e('Port','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="text" name="mail_smtp_port" id="mail_smtp_port" class="mail_data form-control" value="<?php echo html_output(MAIL_SMTP_PORT); ?>" />
    </div>
</div>

<div class="form-group">
    <label for="mail_smtp_auth" class="col-sm-4 control-label"><?php _e('Authentication','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <select class="form-control" name="mail_smtp_auth" id="mail_smtp_auth" required>
            <option value="none" <?php echo (MAIL_SMTP_AUTH == 'none') ? 'selected="selected"' : ''; ?>><?php _e('None','cftp_admin'); ?></option>
            <option value="ssl" <?php echo (MAIL_SMTP_AUTH == 'ssl') ? 'selected="selected"' : ''; ?>>SSL</option>
            <option value="tls" <?php echo (MAIL_SMTP_AUTH == 'tls') ? 'selected="selected"' : ''; ?>>TLS</option>
        </select>
    </div>
</div>
