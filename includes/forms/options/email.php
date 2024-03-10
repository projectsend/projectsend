<h3><?php _e('"From" information','cftp_admin'); ?></h3>

<div class="form-group row">
    <label for="admin_email_address" class="col-sm-4 control-label"><?php _e('E-mail address','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="text" name="admin_email_address" id="admin_email_address" class="form-control" value="<?php echo html_output(get_option('admin_email_address')); ?>" required />
    </div>
</div>

<div class="form-group row">
    <label for="mail_from_name" class="col-sm-4 control-label"><?php _e('Name','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="text" name="mail_from_name" id="mail_from_name" class="form-control" value="<?php echo html_output(get_option('mail_from_name')); ?>" required />
    </div>
</div>

<div class="options_divide"></div>

<h3><?php _e('System performance','cftp_admin'); ?></h3>

<div class="form-group row">
    <div class="col-sm-8 offset-sm-4">
        <label for="notifications_send_when_saving_files">
            <input type="checkbox" value="1" name="notifications_send_when_saving_files" id="notifications_send_when_saving_files" <?php echo (get_option('notifications_send_when_saving_files') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Send "New file" email notifications during the file saving process.','cftp_admin'); ?>
        </label>
        <p class="field_note form-text">
            <?php _e('By unchecking this option, notifications are not sent during the file uploading and editing operations which results in much faster page loading and a better user experience.','cftp_admin'); ?><br>
            <strong><?php _e('Warning: only disable this setting if you have a cron job that takes care of sending the notifications.','cftp_admin'); ?></strong>
        </p>
    </div>
</div>

<div class="options_divide"></div>

<h3><?php _e('Send copies','cftp_admin'); ?></h3>

<div class="form-group row">
    <div class="col-sm-8 offset-sm-4">
        <label for="mail_copy_user_upload">
            <input type="checkbox" value="1" name="mail_copy_user_upload" id="mail_copy_user_upload" <?php echo (get_option('mail_copy_user_upload') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('When a system user uploads files','cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="form-group row">
    <div class="col-sm-8 offset-sm-4">
        <label for="mail_copy_client_upload">
            <input type="checkbox" value="1" name="mail_copy_client_upload" id="mail_copy_client_upload" <?php echo (get_option('mail_copy_client_upload') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('When a client uploads files','cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="options_nested_note">
    <p><?php _e('Define here who will receive copies of this emails. These are sent as BCC so neither recipient will see the other addresses.','cftp_admin'); ?></p>
</div>

<div class="form-group row">
    <div class="col-sm-8 offset-sm-4">
        <label for="mail_copy_main_user">
            <input type="checkbox" value="1" name="mail_copy_main_user" class="mail_copy_main_user" <?php echo (get_option('mail_copy_main_user') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Address supplied above (on "From")','cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="form-group row">
    <label for="mail_copy_addresses" class="col-sm-4 control-label"><?php _e('Also to this addresses','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="text" name="mail_copy_addresses" id="mail_copy_addresses" class="mail_data form-control" value="<?php echo html_output(get_option('mail_copy_addresses')); ?>" />
        <p class="field_note form-text"><?php _e('Separate e-mail addresses with a comma.','cftp_admin'); ?></p>
    </div>
</div>

<div class="options_divide"></div>

<h3><?php _e('Expiration','cftp_admin'); ?></h3>

<div class="form-group row">
    <label for="notifications_max_tries" class="col-sm-4 control-label"><?php _e('Maximum sending attempts','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="number" name="notifications_max_tries" id="notifications_max_tries" class="form-control" value="<?php echo get_option('notifications_max_tries'); ?>" min="1" max="10" step="1" required />
        <p class="field_note form-text"><?php _e('Define how many times the system will attempt to send each notification.','cftp_admin'); ?></p>
    </div>
</div>

<div class="form-group row">
    <label for="notifications_max_days" class="col-sm-4 control-label"><?php _e('Days before expiring','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="number" name="notifications_max_days" id="notifications_max_days" class="form-control" value="<?php echo get_option('notifications_max_days'); ?>" min="0" max="365" step="1" required />
        <p class="field_note form-text"><?php _e('Notifications older than this will not be sent.','cftp_admin'); ?><br /><strong><?php _e('Set to 0 to disable.','cftp_admin'); ?></strong></p>
    </div>
</div>

<div class="form-group row">
    <label for="notifications_max_emails_at_once" class="col-sm-4 control-label"><?php _e('Max. emails to send at once','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="number" name="notifications_max_emails_at_once" id="notifications_max_emails_at_once" class="form-control" value="<?php echo get_option('notifications_max_emails_at_once'); ?>" min="0" max="10000" step="1" required />
        <p class="field_note form-text"><?php _e('Sending too many emails at once can lead to issues. If you set up a notifications cron job, you can set this to a convenient, safe amount of emails to attempt to send per run (ie: 20).','cftp_admin'); ?><br /><strong><?php _e('Set to 0 to disable.','cftp_admin'); ?></strong></p>
    </div>
</div>

<div class="options_divide"></div>

<h3><?php _e('E-mail sending options','cftp_admin'); ?></h3>
<p><?php _e('Here you can select which mail system will be used when sending the notifications. If you have a valid e-mail account, SMTP is the recommended option.','cftp_admin'); ?></p>

<div class="form-group row">
    <label for="mail_system_use" class="col-sm-4 control-label"><?php _e('Mailer','cftp_admin'); ?></label>
    <div class="col-sm-8">
      <?php if ($_ENV['MAIL_SYSTEM_USE'] || $_ENV['MAIL_SMTP_HOST'])
        echo '
            <div class="alert alert-warning" role="alert">
              Settings overwritten by enviroment variables.
            </div>';
      ?>
        <select class="form-select" name="mail_system_use" id="mail_system_use" required>
            <option value="mail" <?php echo (get_option('mail_system_use') == 'mail') ? 'selected="selected"' : ''; ?>>PHP Mail (basic)</option>
            <option value="smtp" <?php echo (get_option('mail_system_use') == 'smtp') ? 'selected="selected"' : ''; ?>>SMTP</option>
            <option value="gmail" <?php echo (get_option('mail_system_use') == 'gmail') ? 'selected="selected"' : ''; ?>>Gmail</option>
            <option value="sendmail" <?php echo (get_option('mail_system_use') == 'sendmail') ? 'selected="selected"' : ''; ?>>Sendmail</option>
        </select>
    </div>
</div>

<div class="options_divide"></div>

<h3><?php _e('SMTP & Gmail shared options','cftp_admin'); ?></h3>
<p><?php _e('You need to include your username (usually your e-mail address) and password if you have selected either SMTP or Gmail as your mailer.','cftp_admin'); ?></p>

<div class="form-group row">
    <label for="mail_smtp_user" class="col-sm-4 control-label"><?php _e('Username','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="text" name="mail_smtp_user" id="mail_smtp_user" class="mail_data form-control" value="<?php echo html_output(get_option('mail_smtp_user')); ?>" />
    </div>
</div>

<div class="form-group row">
    <label for="mail_smtp_pass" class="col-sm-4 control-label"><?php _e('Password','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="password" name="mail_smtp_pass" id="mail_smtp_pass" class="mail_data form-control" value="<?php echo html_output(get_option('mail_smtp_pass')); ?>" />
    </div>
</div>

<div class="options_divide"></div>

<h3><?php _e('SMTP options','cftp_admin'); ?></h3>
<p><?php _e('If you selected SMTP as your mailer, please complete these options.','cftp_admin'); ?></p>

<div class="form-group row">
    <label for="mail_smtp_host" class="col-sm-4 control-label"><?php _e('Host','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="text" name="mail_smtp_host" id="mail_smtp_host" class="mail_data form-control" value="<?php echo html_output(get_option('mail_smtp_host')); ?>" />
    </div>
</div>

<div class="form-group row">
    <label for="mail_smtp_port" class="col-sm-4 control-label"><?php _e('Port','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <input type="text" name="mail_smtp_port" id="mail_smtp_port" class="mail_data form-control" value="<?php echo html_output(get_option('mail_smtp_port')); ?>" />
    </div>
</div>

<div class="form-group row">
    <label for="mail_smtp_auth" class="col-sm-4 control-label"><?php _e('Authentication','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <select class="form-select" name="mail_smtp_auth" id="mail_smtp_auth" required>
            <option value="none" <?php echo (get_option('mail_smtp_auth') == 'none') ? 'selected="selected"' : ''; ?>><?php _e('None','cftp_admin'); ?></option>
            <option value="ssl" <?php echo (get_option('mail_smtp_auth') == 'ssl') ? 'selected="selected"' : ''; ?>>SSL</option>
            <option value="tls" <?php echo (get_option('mail_smtp_auth') == 'tls') ? 'selected="selected"' : ''; ?>>TLS</option>
        </select>
    </div>
</div>

<div class="options_divide"></div>

<h3><?php _e('SSL options','cftp_admin'); ?></h3>

<div class="form-group row">
    <div class="col-sm-8 offset-sm-4">
        <label for="mail_ssl_verify_peer">
            <input type="checkbox" value="1" name="mail_ssl_verify_peer" class="mail_ssl_verify_peer" <?php echo (get_option('mail_ssl_verify_peer') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Verify peer','cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="form-group row">
    <div class="col-sm-8 offset-sm-4">
        <label for="mail_ssl_verify_peer_name">
            <input type="checkbox" value="1" name="mail_ssl_verify_peer_name" class="mail_ssl_verify_peer_name" <?php echo (get_option('mail_ssl_verify_peer_name') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Verify peer name','cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="form-group row">
    <div class="col-sm-8 offset-sm-4">
        <label for="mail_ssl_allow_self_signed">
            <input type="checkbox" value="1" name="mail_ssl_allow_self_signed" class="mail_ssl_allow_self_signed" <?php echo (get_option('mail_ssl_allow_self_signed') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Allow self signed','cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="options_divide"></div>

<p class="warning">
    <a href="email-test.php">
        <?php _e('After saving your options, you can test your configuration here', 'cftp_admin'); ?>
    </a>
</p>
