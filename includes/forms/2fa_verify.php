<?php
/**
 * Contains the form that is used on the login page
 *
 * @package		ProjectSend
 */
?>
<form action="index.php" role="form" id="verify_2fa" method="post">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>" />
    <input type="hidden" name="do" value="2fa_verify">
    <input type="hidden" name="token" value="<?php echo htmlentities($_GET['token']); ?>">

    <div class="form_info">
        <h2><?php _e('Verify login code','cftp_admin'); ?></h2>
        <p><?php echo sprintf(__('An email with a 6-digit code was sent to your email address at %s','cftp_admin'), $masked_email); ?></p>
        <p><?php _e('Please enter the code to access your account','cftp_admin'); ?></p>
    </div>
    <fieldset>
        <div class="form-group">
            <div id="otp_inputs">
                <?php for ($i = 1; $i <= 6; $i++) { ?>
                    <input class="text-center form-control" type="text" name="n<?php echo $i; ?>" id="n<?php echo $i; ?>" maxlength="1" required />
                <?php } ?>
            </div>
        </div>

        <?php recaptcha2_render_widget(); ?>

        <div class="inside_form_buttons">
            <button type="submit" id="btn_submit" class="btn btn-wide btn-primary"><?php _e('Verify','cftp_admin'); ?></button>
        </div>
    </fieldset>
</form>

<div class="login_form_links">
    <p><a href="<?php echo BASE_URI; ?>"><?php _e('Go back','cftp_admin'); ?></a></p>
</div>
