<?php
/**
 * Contains the form that is used on the login page for TOTP verification
 */
?>
<form action="index.php" role="form" id="verify_2fa_totp" method="post">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>" />
    <input type="hidden" name="do" value="2fa_verify_totp">
    <input type="hidden" name="token" value="<?php echo htmlentities($_GET['token']); ?>">
    <input type="hidden" name="remember_me" value="<?php echo (int)$_GET['remember_me']; ?>">

    <div class="form_info">
        <h2><?php _e('Authenticator verification', 'cftp_admin'); ?></h2>
        <p><?php _e('Enter the 6-digit code from your authenticator app', 'cftp_admin'); ?></p>
    </div>
    <fieldset>
        <div class="form-group row" id="totp_code_inputs">
            <div id="otp_inputs">
                <?php for ($i = 1; $i <= 6; $i++) { ?>
                    <input class="text-center form-control" type="text" name="n<?php echo $i; ?>" id="n<?php echo $i; ?>" maxlength="1" autocomplete="off" required />
                <?php } ?>
            </div>
        </div>

        <?php recaptcha2_render_widget(); ?>

        <div class="inside_form_buttons">
            <button type="submit" id="btn_submit" class="btn btn-wide btn-primary"><?php _e('Verify', 'cftp_admin'); ?></button>
        </div>

    </fieldset>
</form>

<form action="index.php" role="form" id="verify_2fa_backup" method="post" class="backup-code-form" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>" />
    <input type="hidden" name="do" value="2fa_verify_totp">
    <input type="hidden" name="token" value="<?php echo htmlentities($_GET['token']); ?>">
    <input type="hidden" name="remember_me" value="<?php echo (int)$_GET['remember_me']; ?>">
    <input type="hidden" name="is_backup_code" value="1">

    <div class="form_info">
        <h2><?php _e('Use a backup code', 'cftp_admin'); ?></h2>
        <p><?php _e('Enter one of the backup codes you saved when you set up your authenticator app.', 'cftp_admin'); ?></p>
    </div>
    <fieldset>
        <div class="form-group">
            <input type="text" name="backup_code" class="form-control text-center" placeholder="XXXXXX-XXXXXX" autocomplete="off" required />
        </div>

        <?php recaptcha2_render_widget(); ?>

        <div class="inside_form_buttons">
            <button type="submit" class="btn btn-wide btn-primary"><?php _e('Verify', 'cftp_admin'); ?></button>
        </div>
    </fieldset>
</form>

<div id="totp_backup_section">
    <a href="#" id="toggle_backup_code" class="toggle-backup-link"
        data-text-backup="<?php _e('Lost your device? Use a backup code', 'cftp_admin'); ?>"
        data-text-totp="<?php _e('Use authenticator code instead', 'cftp_admin'); ?>"
    ><?php _e('Lost your device? Use a backup code', 'cftp_admin'); ?></a>
</div>

<?php login_form_links(['homepage']); ?>
