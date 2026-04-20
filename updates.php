<?php
/**
 * System Update Page
 * Allows users with manage_updates permission to update the system
 */
require_once 'bootstrap.php';

// Check permissions
if (!current_user_can('manage_updates')) {
    exit_with_error_code(403);
}

$page_title = __('System Update', 'cftp_admin');
$page_id = 'updates';
$active_nav = 'dashboard';

// Get update information
$update_data = json_decode(get_latest_version_data());

// Check if update is available
$update_available = $update_data && $update_data->update_available == '1';

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>

<?php
// Show error message if redirected from update process
if (isset($_GET['error'])) {
    $flash->error($_GET['error']);
}
?>

<?php if (!$update_available): ?>
    <!-- Latest Version Card -->
    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="ps-card mb-4">
                <div class="ps-card-body text-center">
                    <i class="fa fa-check-circle fa-4x text-success mb-3"></i>
                    <h3><?php _e('You\'re up to date!', 'cftp_admin'); ?></h3>
                    <p class="text-muted mb-4">
                        <?php _e('ProjectSend is already running the latest version.', 'cftp_admin'); ?>
                    </p>

                    <?php if ($update_data): ?>
                        <div class="version-info">
                            <dl class="row">
                                <dt class="col-sm-6"><?php _e('Current version', 'cftp_admin'); ?></dt>
                                <dd class="col-sm-6"><strong><?php echo $update_data->local_version; ?></strong></dd>
                            </dl>
                        </div>
                    <?php endif; ?>

                    <a href="<?php echo BASE_URI; ?>dashboard.php" class="btn btn-primary">
                        <i class="fa fa-arrow-left"></i> <?php _e('Back to Dashboard', 'cftp_admin'); ?>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="ps-card">
                <div class="ps-card-body">
                    <h4><?php _e('Stay Updated', 'cftp_admin'); ?></h4>
                    <p><?php _e('ProjectSend automatically checks for updates. You\'ll be notified when a new version is available.', 'cftp_admin'); ?></p>

                    <div class="alert alert-info">
                        <p><strong><?php _e('Tip', 'cftp_admin'); ?>:</strong> <?php _e('Regular updates include security fixes and new features.', 'cftp_admin'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>

<div class="row">
    <div class="col-12 col-lg-8">
        <!-- Progress Section Card (hidden initially) -->
        <div id="update-progress-card" class="ps-card mb-4" style="display: none;">
            <div class="ps-card-body">
                <h4><?php _e('Update Progress', 'cftp_admin'); ?></h4>

                <div class="update-steps mb-3">
                    <div class="row text-center">
                        <div class="col">
                            <div class="step-indicator" data-step="download">
                                <i class="fa fa-download fa-2x"></i>
                                <p><?php _e('Download', 'cftp_admin'); ?></p>
                            </div>
                        </div>
                        <div class="col">
                            <div class="step-indicator" data-step="backup">
                                <i class="fa fa-save fa-2x"></i>
                                <p><?php _e('Backup', 'cftp_admin'); ?></p>
                            </div>
                        </div>
                        <div class="col">
                            <div class="step-indicator" data-step="extract">
                                <i class="fa fa-file-archive-o fa-2x"></i>
                                <p><?php _e('Install', 'cftp_admin'); ?></p>
                            </div>
                        </div>
                        <div class="col">
                            <div class="step-indicator" data-step="finalize">
                                <i class="fa fa-check fa-2x"></i>
                                <p><?php _e('Finalize', 'cftp_admin'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="progress" style="height: 30px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div id="progress-status" class="mt-3 text-muted"></div>

                <!-- Error message area -->
                <div id="update-error" class="alert alert-danger mt-3" style="display: none;">
                    <strong><?php _e('Update Failed', 'cftp_admin'); ?></strong>
                    <p id="error-message"></p>
                </div>

                <!-- Success message area -->
                <div id="update-success" class="alert alert-success mt-3" style="display: none;">
                    <h4><?php _e('Update Completed Successfully', 'cftp_admin'); ?></h4>
                    <p><?php _e('ProjectSend has been updated to the latest version.', 'cftp_admin'); ?></p>
                    <p><?php _e('Redirecting to dashboard in 5 seconds...', 'cftp_admin'); ?></p>
                </div>
            </div>
        </div>

        <!-- Version Information Card -->
        <div class="ps-card mb-4">
            <div class="ps-card-body">
                <!-- Version Information -->
                <div class="version-info mb-4">
                    <dl class="row">
                        <dt class="col-sm-4"><?php _e('Current version', 'cftp_admin'); ?></dt>
                        <dd class="col-sm-8"><?php echo $update_data->local_version; ?></dd>
                        <dt class="col-sm-4"><?php _e('New version', 'cftp_admin'); ?></dt>
                        <dd class="col-sm-8"><strong><?php echo $update_data->latest_version; ?></strong></dd>
                        <?php if (!empty($update_data->sha256)) { ?>
                            <dt class="col-sm-4"><?php _e('SHA256 Hash', 'cftp_admin'); ?></dt>
                            <dd class="col-sm-8">
                                <code class="text-muted small"><?php echo html_output($update_data->sha256); ?></code>
                            </dd>
                        <?php } ?>
                    </dl>

                    <?php if (!empty($update_data->diff)) { ?>
                        <h5><?php _e('Changes', 'cftp_admin'); ?></h5>
                        <div class="d-flex flex-wrap gap-3 mb-4">
                            <span>
                                <?php if (!empty($update_data->diff->security) && $update_data->diff->security > 0) { ?>
                                    <span class="badge bg-success text-white"><?php _e('YES', 'cftp_admin'); ?></span>
                                <?php } else { ?>
                                    <span class="badge bg-secondary text-white"><?php _e('NO', 'cftp_admin'); ?></span>
                                <?php } ?>
                                <?php _e('Security fixes', 'cftp_admin'); ?>
                            </span>
                            <span>
                                <?php if (!empty($update_data->diff->features) && $update_data->diff->features > 0) { ?>
                                    <span class="badge bg-success text-white"><?php _e('YES', 'cftp_admin'); ?></span>
                                <?php } else { ?>
                                    <span class="badge bg-secondary text-white"><?php _e('NO', 'cftp_admin'); ?></span>
                                <?php } ?>
                                <?php _e('New features', 'cftp_admin'); ?>
                            </span>
                            <span>
                                <?php if (!empty($update_data->diff->important) && $update_data->diff->important > 0) { ?>
                                    <span class="badge bg-success text-white"><?php _e('YES', 'cftp_admin'); ?></span>
                                <?php } else { ?>
                                    <span class="badge bg-secondary text-white"><?php _e('NO', 'cftp_admin'); ?></span>
                                <?php } ?>
                                <?php _e('Important updates', 'cftp_admin'); ?>
                            </span>
                        </div>
                    <?php } ?>

                    <?php if (!empty($update_data->chlog)) { ?>
                        <a href="<?php echo $update_data->chlog; ?>" target="_blank" class="btn btn-sm btn-outline-primary"><?php _e('View full changelog', 'cftp_admin'); ?></a>
                    <?php } ?>
                </div>
            </div>
        </div>

        <!-- System Requirements Card -->
        <div id="requirements-card" class="ps-card">
            <div class="ps-card-body">
                <!-- System Requirements -->
                <div class="requirements-section mb-4">
                    <h3><?php _e('System Requirements Check', 'cftp_admin'); ?></h3>
                    <div id="requirements-check">
                        <div class="text-center p-3">
                            <i class="fa fa-spinner fa-spin fa-2x"></i>
                            <p class="mt-2"><?php _e('Checking system requirements...', 'cftp_admin'); ?></p>
                        </div>
                        <ul class="list-unstyled requirements-list" style="display: none;">
                            <!-- Populated via JavaScript -->
                        </ul>
                    </div>
                </div>

                <!-- Update Actions -->
                <div class="update-actions">
                    <button id="start-update" class="btn btn-primary btn-wide" disabled>
                        <i class="fa fa-download"></i> <?php _e('Start Update', 'cftp_admin'); ?>
                    </button>
                    <a href="<?php echo BASE_URI; ?>dashboard.php" class="btn btn-outline-secondary">
                        <?php _e('Cancel', 'cftp_admin'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Information Panel -->
    <div class="col-12 col-lg-4">
        <div class="ps-card">
            <div class="ps-card-body">
                <h4><?php _e('Important Information', 'cftp_admin'); ?></h4>
                <div class="alert alert-warning">
                    <p><strong><?php _e('Before updating', 'cftp_admin'); ?>:</strong></p>
                    <ul>
                        <li><?php _e('Make sure you have a recent backup of your database', 'cftp_admin'); ?></li>
                        <li><?php _e('The update process may take several minutes', 'cftp_admin'); ?></li>
                        <li><?php _e('Do not close this page during the update', 'cftp_admin'); ?></li>
                    </ul>
                </div>

                <h5><?php _e('Update Process', 'cftp_admin'); ?></h5>
                <p><?php _e('The update will:', 'cftp_admin'); ?></p>
                <ol>
                    <li><?php _e('Download the latest version', 'cftp_admin'); ?></li>
                    <li><?php _e('Create a backup of current files', 'cftp_admin'); ?></li>
                    <li><?php _e('Extract and install new files', 'cftp_admin'); ?></li>
                    <li><?php _e('Update the database if needed', 'cftp_admin'); ?></li>
                </ol>

                <div class="alert alert-info">
                    <p><strong><?php _e('Note', 'cftp_admin'); ?>:</strong> <?php _e('Your uploaded files will not be affected by this update.', 'cftp_admin'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- Hidden CSRF token for JavaScript access -->
<?php addCsrf(); ?>

<?php if ($update_available): ?>
<script type="text/javascript">
    var update_download_url = '<?php echo addslashes($update_data->url); ?>';
    var update_data = <?php echo json_encode($update_data); ?>;
    var json_strings = <?php echo json_encode($json_strings); ?>;
</script>
<?php endif; ?>

<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
?>