<?php
/**
 * TOTP (Authenticator App) setup page
 * Users can set up, disable, or regenerate backup codes for TOTP 2FA
 */
require_once 'bootstrap.php';
redirect_if_not_logged_in();

if (!(bool)get_option('two_factor_allow_totp', null, '1')) {
    exit_with_error_code(403);
}

$page_title = __('Authenticator App Setup', 'cftp_admin');
$page_id = 'totp_setup';
$active_nav = 'users';

$totp = new \ProjectSend\Classes\Totp();
$user_id = CURRENT_USER_ID;
$is_enabled = $totp->isEnabledForUser($user_id);
$step = isset($_GET['step']) ? $_GET['step'] : ($is_enabled ? 'manage' : 'intro');

// Handle POST actions
if ($_POST) {
    switch ($_POST['do']) {
        case 'totp_begin':
            // Generate a new secret and store in session
            $secret = $totp->generateSecret();
            $_SESSION['totp_setup_secret'] = $secret;

            ps_redirect(BASE_URI . 'totp-setup.php?step=scan');
            break;

        case 'totp_verify':
            // Verify the code against the session-stored secret
            $secret = $_SESSION['totp_setup_secret'] ?? null;
            if (!$secret) {
                $flash->error(__('Setup session expired. Please start again.', 'cftp_admin'));
                ps_redirect(BASE_URI . 'totp-setup.php');
                break;
            }

            $code = $_POST['totp_code'];
            if ($totp->verifyCode($secret, $code)) {
                // Enable TOTP for the user
                $totp->enableForUser($user_id, $secret);

                // Generate backup codes
                $backup_codes = $totp->generateBackupCodes();
                $totp->storeBackupCodes($user_id, $backup_codes);

                // Store codes temporarily in session for display
                $_SESSION['totp_backup_codes'] = $backup_codes;
                unset($_SESSION['totp_setup_secret']);

                $flash->success(__('Authenticator app has been enabled successfully.', 'cftp_admin'));
                ps_redirect(BASE_URI . 'totp-setup.php?step=backup_codes');
            } else {
                $flash->error(__('Invalid code. Please try again.', 'cftp_admin'));
                ps_redirect(BASE_URI . 'totp-setup.php?step=scan');
            }
            break;

        case 'totp_disable':
            // Require password confirmation
            $user = new \ProjectSend\Classes\Users($user_id);
            if (!password_verify($_POST['password'], $user->getRawPassword())) {
                $flash->error(__('Incorrect password.', 'cftp_admin'));
                ps_redirect(BASE_URI . 'totp-setup.php');
                break;
            }

            $totp->disableForUser($user_id);
            $flash->success(__('Authenticator app has been disabled.', 'cftp_admin'));
            ps_redirect(BASE_URI . 'totp-setup.php');
            break;

        case 'totp_regenerate_backup':
            // Require password confirmation
            $user = new \ProjectSend\Classes\Users($user_id);
            if (!password_verify($_POST['password'], $user->getRawPassword())) {
                $flash->error(__('Incorrect password.', 'cftp_admin'));
                ps_redirect(BASE_URI . 'totp-setup.php');
                break;
            }

            $backup_codes = $totp->generateBackupCodes();
            $totp->storeBackupCodes($user_id, $backup_codes);
            $_SESSION['totp_backup_codes'] = $backup_codes;

            $flash->success(__('New backup codes have been generated.', 'cftp_admin'));
            ps_redirect(BASE_URI . 'totp-setup.php?step=backup_codes');
            break;
    }
}

$csrf_token = getCsrfToken();

// Prepare data for the scan step
if ($step === 'scan') {
    $secret = $_SESSION['totp_setup_secret'] ?? null;
    if (!$secret) {
        ps_redirect(BASE_URI . 'totp-setup.php');
    }

    $user_data = get_user_by_id($user_id);
    $provisioning_uri = $totp->getProvisioningUri($secret, $user_data['email']);
    $qr_data_uri = $totp->generateQrCodeDataUri($provisioning_uri);
}

// Get backup codes from session for display
$backup_codes = null;
if ($step === 'backup_codes') {
    $backup_codes = $_SESSION['totp_backup_codes'] ?? null;
    if ($backup_codes) {
        unset($_SESSION['totp_backup_codes']);
    }
}

// Get remaining backup codes count for manage step
$remaining_codes = 0;
if ($is_enabled) {
    $remaining_codes = $totp->getRemainingBackupCodesCount($user_id);
}

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>
<div class="row">
    <?php if ($step === 'manage') { ?>
    <!-- Sidebar navigation -->
    <div class="col-lg-2 d-none d-lg-block">
        <nav class="options-section-nav mb-3">
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a class="nav-link" href="#section-status"><?php _e('Status', 'cftp_admin'); ?></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#section-backup-codes"><?php _e('Backup codes', 'cftp_admin'); ?></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#section-disable"><?php _e('Disable authenticator', 'cftp_admin'); ?></a>
                </li>
            </ul>
        </nav>
    </div>
    <?php } ?>

    <!-- Main content area -->
    <div class="col-12 col-lg-7">
        <div class="ps-card">
            <div class="ps-card-body totp-setup">

                <?php if ($step === 'intro') { ?>
                    <h3><?php _e('Set up authenticator app', 'cftp_admin'); ?></h3>
                    <p><?php _e('Use an authenticator app like Google Authenticator, Authy, or 1Password to generate verification codes for two-factor authentication.', 'cftp_admin'); ?></p>
                    <p><?php _e('This adds an extra layer of security to your account by requiring a code from your phone in addition to your password.', 'cftp_admin'); ?></p>

                    <form action="totp-setup.php" method="post">
                        <?php addCsrf(); ?>
                        <input type="hidden" name="do" value="totp_begin">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-shield"></i> <?php _e('Begin setup', 'cftp_admin'); ?>
                        </button>
                    </form>

                <?php } elseif ($step === 'scan') { ?>
                    <h3><?php _e('Scan QR code', 'cftp_admin'); ?></h3>
                    <p><?php _e('Scan this QR code with your authenticator app:', 'cftp_admin'); ?></p>

                    <div class="totp-qr-container">
                        <img src="<?php echo $qr_data_uri; ?>" alt="<?php _e('QR Code', 'cftp_admin'); ?>" />
                    </div>

                    <div class="totp-secret-display">
                        <p><?php _e("Can't scan the code? Enter this key manually:", 'cftp_admin'); ?></p>
                        <div class="totp-secret-key">
                            <code id="totp_secret_text"><?php echo htmlspecialchars($secret); ?></code>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="copy_secret_btn" title="<?php _e('Copy to clipboard', 'cftp_admin'); ?>">
                                <i class="fa fa-clipboard"></i>
                            </button>
                        </div>
                    </div>

                    <div class="options_divide"></div>

                    <h3><?php _e('Verify setup', 'cftp_admin'); ?></h3>
                    <p><?php _e('Enter the 6-digit code shown in your authenticator app to verify the setup:', 'cftp_admin'); ?></p>

                    <form action="totp-setup.php" method="post">
                        <?php addCsrf(); ?>
                        <input type="hidden" name="do" value="totp_verify">
                        <div class="form-group row">
                            <label class="col-sm-4 control-label"><?php _e('Verification code', 'cftp_admin'); ?></label>
                            <div class="col-sm-8">
                                <input type="text" name="totp_code" class="form-control totp-code-input" maxlength="6" pattern="[0-9]{6}" placeholder="000000" autocomplete="off" required autofocus />
                            </div>
                        </div>
                        <div class="after_form_buttons">
                            <button type="submit" class="btn btn-wide btn-primary"><?php _e('Verify and enable', 'cftp_admin'); ?></button>
                            <a href="<?php echo BASE_URI; ?>totp-setup.php" class="btn btn-wide btn-outline-secondary"><?php _e('Cancel', 'cftp_admin'); ?></a>
                        </div>
                    </form>

                <?php } elseif ($step === 'backup_codes') { ?>
                    <h3><?php _e('Backup codes', 'cftp_admin'); ?></h3>
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-triangle"></i>
                        <?php _e('Save these backup codes in a safe place. Each code can only be used once. If you lose access to your authenticator app, you can use these codes to sign in.', 'cftp_admin'); ?>
                    </div>

                    <?php if ($backup_codes) { ?>
                        <div class="totp-backup-codes" id="backup_codes_container">
                            <div class="backup-codes-grid">
                                <?php foreach ($backup_codes as $code) { ?>
                                    <div class="backup-code"><code><?php echo htmlspecialchars($code); ?></code></div>
                                <?php } ?>
                            </div>
                        </div>

                        <div class="totp-backup-actions">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="copy_backup_codes_btn">
                                <i class="fa fa-clipboard"></i> <?php _e('Copy all codes', 'cftp_admin'); ?>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="download_backup_codes_btn">
                                <i class="fa fa-download"></i> <?php _e('Download as text file', 'cftp_admin'); ?>
                            </button>
                        </div>
                    <?php } else { ?>
                        <p><?php _e('Backup codes are only shown once when generated. If you need new codes, use the regenerate option.', 'cftp_admin'); ?></p>
                    <?php } ?>

                    <div class="options_divide"></div>
                    <div class="after_form_buttons">
                        <a href="<?php echo BASE_URI; ?>totp-setup.php" class="btn btn-wide btn-primary"><?php _e('Done', 'cftp_admin'); ?></a>
                    </div>

                <?php } elseif ($step === 'manage') { ?>
                    <h3 id="section-status"><?php _e('Status', 'cftp_admin'); ?></h3>
                    <p><?php _e('Your authenticator app is configured and active.', 'cftp_admin'); ?></p>
                    <div class="form-group row">
                        <label class="col-sm-4 control-label"><?php _e('Status', 'cftp_admin'); ?></label>
                        <div class="col-sm-8">
                            <span class="badge bg-success"><i class="fa fa-check"></i> <?php _e('Active', 'cftp_admin'); ?></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-sm-4 control-label"><?php _e('Backup codes remaining', 'cftp_admin'); ?></label>
                        <div class="col-sm-8">
                            <span><?php echo $remaining_codes; ?></span>
                            <?php if ($remaining_codes <= 2) { ?>
                                <span class="text-warning ms-2"><i class="fa fa-exclamation-triangle"></i> <?php _e('Consider regenerating your backup codes.', 'cftp_admin'); ?></span>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="options_divide"></div>

                    <h3 id="section-backup-codes"><?php _e('Regenerate backup codes', 'cftp_admin'); ?></h3>
                    <p><?php _e('Backup codes are single-use codes that allow you to sign in if you lose access to your authenticator app (for example, if your phone is lost, stolen, or reset). Each code can only be used once, and they do not expire until used.', 'cftp_admin'); ?></p>
                    <p><?php _e('Regenerating will immediately invalidate all of your current backup codes and replace them with a new set. Make sure you are in a position to save the new codes before proceeding, as they will only be displayed once.', 'cftp_admin'); ?></p>
                    <div class="after_form_buttons">
                        <button type="button" class="btn btn-wide btn-warning" data-bs-toggle="modal" data-bs-target="#regenerateBackupCodesModal">
                            <?php _e('Regenerate backup codes', 'cftp_admin'); ?>
                        </button>
                    </div>

                    <div class="options_divide"></div>

                    <h3 id="section-disable"><?php _e('Disable authenticator', 'cftp_admin'); ?></h3>
                    <p><?php _e('If you no longer want to use an authenticator app for two-factor authentication, you can disable it here. This will permanently remove the authenticator app link and delete all of your backup codes from the system.', 'cftp_admin'); ?></p>
                    <p><?php _e('After disabling, your account will fall back to email-based verification codes if two-factor authentication is required by your administrator, or no second factor at all if it is optional.', 'cftp_admin'); ?></p>
                    <div class="after_form_buttons">
                        <button type="button" class="btn btn-wide btn-danger" data-bs-toggle="modal" data-bs-target="#disableAuthenticatorModal">
                            <?php _e('Disable authenticator', 'cftp_admin'); ?>
                        </button>
                    </div>
                <?php } ?>

            </div>
        </div>
    </div>
</div>
<?php if ($step === 'manage') { ?>
<!-- Regenerate Backup Codes Modal -->
<div class="modal fade" id="regenerateBackupCodesModal" tabindex="-1" aria-labelledby="regenerateBackupCodesModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="totp-setup.php" method="post">
                <?php addCsrf(); ?>
                <input type="hidden" name="do" value="totp_regenerate_backup">
                <div class="modal-header">
                    <h5 class="modal-title" id="regenerateBackupCodesModalLabel"><?php _e('Regenerate backup codes', 'cftp_admin'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><?php _e('Your current backup codes will be invalidated and replaced with a new set. Enter your password to confirm.', 'cftp_admin'); ?></p>
                    <div class="form-group">
                        <label for="regenerate_password"><?php _e('Password', 'cftp_admin'); ?></label>
                        <input type="password" name="password" id="regenerate_password" class="form-control mt-1" required />
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?php _e('Cancel', 'cftp_admin'); ?></button>
                    <button type="submit" class="btn btn-warning"><?php _e('Regenerate', 'cftp_admin'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Disable Authenticator Modal -->
<div class="modal fade" id="disableAuthenticatorModal" tabindex="-1" aria-labelledby="disableAuthenticatorModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="totp-setup.php" method="post">
                <?php addCsrf(); ?>
                <input type="hidden" name="do" value="totp_disable">
                <div class="modal-header">
                    <h5 class="modal-title" id="disableAuthenticatorModalLabel"><?php _e('Disable authenticator', 'cftp_admin'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><?php _e('Your authenticator app and all backup codes will be removed from your account. Enter your password to confirm.', 'cftp_admin'); ?></p>
                    <div class="form-group">
                        <label for="disable_password"><?php _e('Password', 'cftp_admin'); ?></label>
                        <input type="password" name="password" id="disable_password" class="form-control mt-1" required />
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?php _e('Cancel', 'cftp_admin'); ?></button>
                    <button type="submit" class="btn btn-danger"><?php _e('Disable', 'cftp_admin'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php } ?>

<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
