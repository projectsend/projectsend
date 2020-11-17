<form action="reset-password.php?token=<?php echo html_output($got_token); ?>&user=<?php echo html_output($got_user); ?>" name="reset_password_enter_new" id="reset_password_enter_new" method="post" role="form">
    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>" />
    <fieldset>
        <input type="hidden" name="form_type" id="form_type" value="new_password" />

        <div class="form-group">
            <label for="reset_password_new"><?php _e('New password','cftp_admin'); ?></label>
            <div class="input-group">
                <input type="password" name="password" id="password" class="form-control password_toggle required" required />
                <div class="input-group-btn password_toggler">
                    <button type="button" class="btn pass_toggler_show"><i class="glyphicon glyphicon-eye-open"></i></button>
                </div>
            </div>
            <button type="button" name="generate_password" id="generate_password" class="btn btn-default btn-sm btn_generate_password" data-ref="reset_password_new" data-min="<?php echo MAX_GENERATE_PASS_CHARS; ?>" data-max="<?php echo MAX_GENERATE_PASS_CHARS; ?>"><?php _e('Generate','cftp_admin'); ?></button>
        </div>
        <?php echo password_notes(); ?>
        
        <p><?php _e("Please enter your desired new password. After that, you will be able to log in normally.",'cftp_admin'); ?></p>

        <div class="inside_form_buttons">
            <button type="submit" name="submit" class="btn btn-wide btn-primary"><?php _e('Set new password','cftp_admin'); ?></button>
        </div>
    </fieldset>
</form>
