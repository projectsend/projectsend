<?php
/**
 * Show the form to add a new external storage integration.
 */
require_once 'bootstrap.php';
redirect_if_not_logged_in();

// Check for system administration permissions
if (!current_user_can('edit_settings')) {
    exit_with_error_code(403);
}

$active_nav = 'integrations';
$page_title = __('Add External Storage Integration', 'cftp_admin');
$page_id = 'integration_form';

global $flash;
$integrations_handler = new \ProjectSend\Classes\Integrations();
$available_types = \ProjectSend\Classes\Integrations::getAvailableTypes(true);

// Handle form submission
if ($_POST) {
    $integration_data = [
        'type' => $_POST['type'] ?? '',
        'name' => $_POST['name'] ?? '',
        'active' => isset($_POST['active']) ? 1 : 0,
        'test_connection' => isset($_POST['test_connection']) ? true : false,
        'credentials' => []
    ];

    // Collect credentials based on type
    if (!empty($_POST['type']) && isset($available_types[$_POST['type']]['fields'])) {
        $type_config = $available_types[$_POST['type']];
        foreach ($type_config['fields'] as $field => $config) {
            if (isset($_POST['credentials'][$field])) {
                $integration_data['credentials'][$field] = $_POST['credentials'][$field];
            }
        }
    }

    $result = $integrations_handler->create($integration_data);

    if ($result['status'] === 'success') {
        $flash->success($result['message']);

        // Show connection test results if performed
        if (isset($result['connection_test'])) {
            if ($result['connection_test']['success']) {
                $flash->success(__('Connection test passed!', 'cftp_admin'));
            } else {
                $flash->warning(__('Integration created but connection test failed: ', 'cftp_admin') . $result['connection_test']['message']);
            }
        }

        $redirect_to = BASE_URI . 'integrations-edit.php?id=' . $result['integration_id'];
    } else {
        $flash->error($result['message']);
        $redirect_to = BASE_URI . 'integrations-add.php';
    }

    ps_redirect($redirect_to);
}

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>

<div class="row">
    <div class="col-12 col-lg-8">
        <div class="ps-card">
            <div class="ps-card-body">
                <form action="integrations-add.php" method="post" id="integration_form" data-available-types="<?php echo html_output(json_encode($available_types)); ?>">
                    <?php addCsrf(); ?>

                    <div class="mb-4">
                        <label for="type" class="form-label"><?php _e('Integration Type', 'cftp_admin'); ?> *</label>
                        <select name="type" id="type" class="form-select" required>
                            <option value=""><?php _e('Select integration type...', 'cftp_admin'); ?></option>
                            <?php foreach ($available_types as $type => $config): ?>
                                <option value="<?php echo $type; ?>"
                                        data-coming-soon="<?php echo isset($config['coming_soon']) && $config['coming_soon'] ? 'true' : 'false'; ?>"
                                        <?php echo (isset($_POST['type']) && $_POST['type'] == $type) ? 'selected' : ''; ?>
                                        <?php echo (isset($config['coming_soon']) && $config['coming_soon']) ? 'disabled' : ''; ?>>
                                    <?php echo html_output($config['name']); ?>
                                    <?php if (isset($config['coming_soon']) && $config['coming_soon']): ?>
                                        (<?php _e('Coming Soon', 'cftp_admin'); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text"><?php _e('Choose the external storage service you want to integrate.', 'cftp_admin'); ?></div>
                    </div>

                    <div class="mb-4">
                        <label for="name" class="form-label"><?php _e('Integration Name', 'cftp_admin'); ?> *</label>
                        <input type="text" name="name" id="name" class="form-control"
                               value="<?php echo isset($_POST['name']) ? html_output($_POST['name']) : ''; ?>"
                               required maxlength="100">
                        <div class="form-text"><?php _e('A descriptive name for this integration (e.g., "Main S3 Bucket", "Backup Storage").', 'cftp_admin'); ?></div>
                    </div>

                    <!-- Dynamic credential fields will be populated here -->
                    <div id="credentials_fields"></div>

                    <div class="mb-4">
                        <div class="form-check">
                            <input type="checkbox" name="active" id="active" class="form-check-input" value="1"
                                   <?php echo (isset($_POST['active']) || !isset($_POST['type'])) ? 'checked' : ''; ?>>
                            <label for="active" class="form-check-label">
                                <?php _e('Enable integration', 'cftp_admin'); ?>
                            </label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check">
                            <input type="checkbox" name="test_connection" id="test_connection" class="form-check-input" value="1" checked>
                            <label for="test_connection" class="form-check-label">
                                <?php _e('Test connection after creating', 'cftp_admin'); ?>
                            </label>
                        </div>
                    </div>

                    <div class="d-flex gap-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-check"></i> <?php _e('Create Integration', 'cftp_admin'); ?>
                        </button>
                        <a href="integrations.php" class="btn btn-outline-secondary">
                            <i class="fa fa-arrow-left"></i> <?php _e('Cancel', 'cftp_admin'); ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="ps-card">
            <div class="ps-card-body">
                <h4><?php _e('Integration Types', 'cftp_admin'); ?></h4>

                <?php foreach ($available_types as $type => $config): ?>
                    <div class="integration-type-info mb-3" data-type="<?php echo $type; ?>" style="display: none;">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6><?php echo html_output($config['name']); ?></h6>
                            <?php if (isset($config['coming_soon']) && $config['coming_soon']): ?>
                                <span class="badge bg-warning text-dark"><?php _e('Coming Soon', 'cftp_admin'); ?></span>
                            <?php else: ?>
                                <span class="badge bg-success"><?php _e('Available', 'cftp_admin'); ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="text-muted small"><?php echo html_output($config['description']); ?></p>

                        <?php if (isset($config['fields'])): ?>
                            <h6 class="mt-3"><?php _e('Required Information:', 'cftp_admin'); ?></h6>
                            <ul class="small">
                                <?php foreach ($config['fields'] as $field => $field_config): ?>
                                    <li>
                                        <?php echo html_output($field_config['label']); ?>
                                        <?php if ($field_config['required']): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div id="default_info">
                    <p class="text-muted"><?php _e('Select an integration type to see specific requirements and information.', 'cftp_admin'); ?></p>
                </div>
            </div>
        </div>

        <div class="ps-card mt-4">
            <div class="ps-card-body">
                <h4><?php _e('Security Notice', 'cftp_admin'); ?></h4>
                <div class="alert alert-info">
                    <small>
                        <i class="fa fa-lock"></i>
                        <?php _e('All credentials are encrypted before being stored in the database. Only users with system administration permissions can manage integrations.', 'cftp_admin'); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>


<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
?>