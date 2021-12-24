<form action="reset-password.php" name="reset_password_enter_email" id="reset_password_enter_email" method="post" role="form">
    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>" />
    <fieldset>
        <input type="hidden" name="form_type" id="form_type" value="new_request" />

        <div class="form-group">
            <label for="email"><?php _e('E-mail','cftp_admin'); ?></label>
            <input type="email" name="email" id="email" class="form-control" required />
        </div>

        <p><?php _e("Please enter your account's e-mail address. You will receive a link to continue the process.",'cftp_admin'); ?></p>

        <?php recaptcha2RenderWidget(); ?>

        <div class="inside_form_buttons">
            <button type="submit" name="submit" class="btn btn-wide btn-primary"><?php _e('Get a new password','cftp_admin'); ?></button>
        </div>
    </fieldset>
</form>
