<?php
/**
 * Embeddable TOTP setup section for user/client edit pages
 * Shows current TOTP status and links to the setup page
 */
$totp_handler = new \ProjectSend\Classes\Totp();
$totp_user_id = CURRENT_USER_ID;
$totp_is_enabled = $totp_handler->isEnabledForUser($totp_user_id);
$totp_allow_totp = (bool)get_option('two_factor_allow_totp', null, '1');

if ($totp_allow_totp) {
?>
<div class="form-group row">
    <div class="col">
        <h3><?php _e('Two-factor authentication', 'cftp_admin'); ?></h3>

        <?php if ($totp_is_enabled) { ?>
            <div class="totp-status-enabled">
                <span class="badge bg-success"><i class="fa fa-check"></i> <?php _e('Authenticator app is active', 'cftp_admin'); ?></span>
                <?php
                    $remaining = $totp_handler->getRemainingBackupCodesCount($totp_user_id);
                    echo '<p class="small text-muted mt-2">' . sprintf(__('%d backup code(s) remaining', 'cftp_admin'), $remaining) . '</p>';
                ?>
                <a href="<?php echo BASE_URI; ?>totp-setup.php" class="btn btn-sm btn-outline-secondary mt-1">
                    <i class="fa fa-cog"></i> <?php _e('Manage authenticator', 'cftp_admin'); ?>
                </a>
            </div>
        <?php } else { ?>
            <div class="totp-status-disabled">
                <p class="text-muted"><?php _e('Add an extra layer of security by using an authenticator app.', 'cftp_admin'); ?></p>
                <a href="<?php echo BASE_URI; ?>totp-setup.php" class="btn btn-sm btn-primary">
                    <i class="fa fa-shield"></i> <?php _e('Set up authenticator app', 'cftp_admin'); ?>
                </a>
            </div>
        <?php } ?>
    </div>
</div>
<?php
}
