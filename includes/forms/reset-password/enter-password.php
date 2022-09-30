<form action="reset-password.php?token=<?php echo html_output($_GET['token']); ?>&user=<?php echo html_output($_GET['user']); ?>" name="reset_password_enter_new" id="reset_password_enter_new" method="post" role="form">
    <input type="hidden" name="token" value="<?php echo html_output($_GET['token']); ?>">
    <input type="hidden" name="user" value="<?php echo html_output($_GET['user']); ?>">

    <?php addCsrf(); ?>
    <fieldset>
        <input type="hidden" name="form_type" id="form_type" value="new_password" />

        <div class="form-group row">
            <label for="reset_password_new"><?php _e('New password','cftp_admin'); ?></label>
            <div class="input-group">
                <input type="password" name="password" id="password" class="form-control attach_password_toggler required" required />
            </div>
            <button type="button" name="generate_password" id="generate_password" class="btn btn-light btn-sm btn_generate_password" data-ref="reset_password_new" data-min="<?php echo MAX_GENERATE_PASS_CHARS; ?>" data-max="<?php echo MAX_GENERATE_PASS_CHARS; ?>"><?php _e('Generate','cftp_admin'); ?></button>
        </div>
        <?php echo password_notes(); ?>
        
        <p><?php _e("Please enter your desired new password. After that, you will be able to log in normally.",'cftp_admin'); ?></p>

        <div class="inside_form_buttons">
            <button type="submit" name="submit" class="btn btn-wide btn-primary"><?php _e('Set new password','cftp_admin'); ?></button>
        </div>
    </fieldset>
</form>
