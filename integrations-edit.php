<?php
/**
 * Show the form to edit an existing external storage integration.
 */
require_once 'bootstrap.php';
redirect_if_not_logged_in();

// Check for system administration permissions
if (!current_user_can('edit_settings')) {
    exit_with_error_code(403);
}

$active_nav = 'integrations';
$page_title = __('Edit External Storage Integration', 'cftp_admin');
$page_id = 'integration_form';

global $flash;
$integrations_handler = new \ProjectSend\Classes\Integrations();
$available_types = \ProjectSend\Classes\Integrations::getAvailableTypes(true);

// Check if the id parameter is on the URI
if (!isset($_GET['id'])) {
    exit_with_error_code(403);
}

$integration_id = (int)$_GET['id'];
$integration = $integrations_handler->getById($integration_id);

if (!$integration) {
    exit_with_error_code(404);
}

// Decrypt credentials for form display (but don't expose secrets)
$credentials = [];
if ($integration['credentials_encrypted']) {
    try {
        $key = hash('sha256', 'projectsend-external-storage-key-' . ROOT_DIR);
        $decrypted = openssl_decrypt(base64_decode($integration['credentials_encrypted']), 'AES-256-CBC', $key, 0, substr($key, 0, 16));
        $credentials = json_decode($decrypted, true) ?: [];
    } catch (Exception $e) {
        // If decryption fails, start with empty credentials
        $credentials = [];
    }
}

// Handle form submission
if ($_POST) {
    $integration_data = [
        'name' => $_POST['name'] ?? '',
        'active' => isset($_POST['active']) ? 1 : 0,
        'test_connection' => isset($_POST['test_connection']) ? true : false,
    ];

    // Only update credentials if provided
    if (!empty($_POST['update_credentials'])) {
        $integration_data['credentials'] = [];

        // Collect credentials based on type
        if (isset($available_types[$integration['type']]['fields'])) {
            $type_config = $available_types[$integration['type']];
            foreach ($type_config['fields'] as $field => $config) {
                if (isset($_POST['credentials'][$field])) {
                    $integration_data['credentials'][$field] = $_POST['credentials'][$field];
                }
            }
        }
    }

    $result = $integrations_handler->update($integration_id, $integration_data);

    if ($result['status'] === 'success') {
        $flash->success($result['message']);

        // Show connection test results if performed
        if (isset($result['connection_test'])) {
            if ($result['connection_test']['success']) {
                $flash->success(__('Connection test passed!', 'cftp_admin'));
            } else {
                $flash->warning(__('Integration updated but connection test failed: ', 'cftp_admin') . $result['connection_test']['message']);
            }
        }

        // Reload integration data
        $integration = $integrations_handler->getById($integration_id);
    } else {
        $flash->error($result['message']);
    }
}

// Get file count for this integration
global $dbh;
$file_count_query = "SELECT COUNT(*) FROM " . TABLE_FILES . " WHERE integration_id = :id";
$file_count_stmt = $dbh->prepare($file_count_query);
$file_count_stmt->bindParam(':id', $integration_id, PDO::PARAM_INT);
$file_count_stmt->execute();
$file_count = $file_count_stmt->fetchColumn();

$type_config = $available_types[$integration['type']] ?? null;

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>

<div class="row">
    <div class="col-12 col-lg-8">
        <div class="ps-card">
            <div class="ps-card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3><?php echo html_output($integration['name']); ?></h3>
                        <div class="d-flex align-items-center gap-3">
                            <?php if ($type_config): ?>
                                <span class="badge bg-primary"><?php echo html_output($type_config['name']); ?></span>
                            <?php endif; ?>
                            <span class="badge bg-<?php echo $integration['active'] ? 'success' : 'secondary'; ?>">
                                <?php echo $integration['active'] ? __('Active', 'cftp_admin') : __('Inactive', 'cftp_admin'); ?>
                            </span>
                            <?php if ($file_count > 0): ?>
                                <span class="badge bg-info"><?php echo $file_count; ?> <?php _e('files', 'cftp_admin'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="btn-group" role="group">
                        <a href="<?php echo BASE_URI; ?>integrations.php?action=test&id=<?php echo $integration_id; ?>"
                           class="btn btn-outline-info btn-sm">
                            <i class="fa fa-plug"></i> <?php _e('Test Connection', 'cftp_admin'); ?>
                        </a>
                    </div>
                </div>

                <form action="integrations-edit.php?id=<?php echo $integration_id; ?>" method="post" id="integration_form">
                    <?php addCsrf(); ?>

                    <div class="mb-4">
                        <label for="name" class="form-label"><?php _e('Integration Name', 'cftp_admin'); ?> *</label>
                        <input type="text" name="name" id="name" class="form-control"
                               value="<?php echo html_output($integration['name']); ?>"
                               required maxlength="100">
                        <div class="form-text"><?php _e('A descriptive name for this integration.', 'cftp_admin'); ?></div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check">
                            <input type="checkbox" name="active" id="active" class="form-check-input" value="1"
                                   <?php echo $integration['active'] ? 'checked' : ''; ?>>
                            <label for="active" class="form-check-label">
                                <?php _e('Enable integration', 'cftp_admin'); ?>
                            </label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="card">
                            <div class="card-header">
                                <div class="form-check mb-0">
                                    <input type="checkbox" name="update_credentials" id="update_credentials" class="form-check-input">
                                    <label for="update_credentials" class="form-check-label">
                                        <strong><?php _e('Update Connection Credentials', 'cftp_admin'); ?></strong>
                                    </label>
                                </div>
                                <small class="text-muted">
                                    <?php _e('Check this box to update the connection credentials. Leave unchecked to keep existing credentials.', 'cftp_admin'); ?>
                                </small>
                            </div>
                            <div class="card-body" id="credentials_section" style="display: none;">
                                <?php if ($type_config && isset($type_config['fields'])): ?>
                                    <div class="row">
                                        <?php foreach ($type_config['fields'] as $field => $field_config): ?>
                                            <div class="col-md-6 mb-3">
                                                <label for="credentials_<?php echo $field; ?>" class="form-label">
                                                    <?php echo html_output($field_config['label']); ?>
                                                    <?php if ($field_config['required']): ?>
                                                        <span class="text-danger">*</span>
                                                    <?php endif; ?>
                                                </label>

                                                <?php if ($field_config['type'] === 'select' && isset($field_config['options'])): ?>
                                                    <select name="credentials[<?php echo $field; ?>]" id="credentials_<?php echo $field; ?>" class="form-select">
                                                        <option value=""><?php _e('Choose...', 'cftp_admin'); ?></option>
                                                        <?php foreach ($field_config['options'] as $option_value => $option_label): ?>
                                                            <option value="<?php echo $option_value; ?>"
                                                                    <?php echo (isset($credentials[$field]) && $credentials[$field] == $option_value) ? 'selected' : ''; ?>>
                                                                <?php echo html_output($option_label); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                <?php else: ?>
                                                    <?php
                                                    $input_type = $field_config['type'] === 'password' ? 'password' : 'text';
                                                    $current_value = '';
                                                    // For password fields, don't show the actual value for security
                                                    if ($field_config['type'] !== 'password' && isset($credentials[$field])) {
                                                        $current_value = $credentials[$field];
                                                    }
                                                    ?>
                                                    <input type="<?php echo $input_type; ?>"
                                                           name="credentials[<?php echo $field; ?>]"
                                                           id="credentials_<?php echo $field; ?>"
                                                           class="form-control"
                                                           value="<?php echo html_output($current_value); ?>"
                                                           <?php if ($field_config['type'] === 'password'): ?>
                                                               placeholder="<?php _e('Enter new value to update', 'cftp_admin'); ?>"
                                                           <?php endif; ?>>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="mt-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="test_connection" id="test_connection" class="form-check-input" value="1">
                                        <label for="test_connection" class="form-check-label">
                                            <?php _e('Test connection after updating credentials', 'cftp_admin'); ?>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-3">
                        <button type="submit" class="btn btn-primary">
                            <?php _e('Update Integration', 'cftp_admin'); ?>
                        </button>
                        <a href="integrations.php" class="btn btn-outline-secondary">
                            </i> <?php _e('Back to List', 'cftp_admin'); ?>
                        </a>
                        <?php if ($file_count == 0): ?>
                            <a href="integrations.php?action=delete&id=<?php echo $integration_id; ?>"
                               class="btn btn-outline-danger ms-auto"
                               onclick="return confirm('<?php _e('Are you sure you want to delete this integration?', 'cftp_admin'); ?>')">
                                <?php _e('Delete Integration', 'cftp_admin'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="ps-card">
            <div class="ps-card-body">
                <h4><?php _e('Integration Details', 'cftp_admin'); ?></h4>

                <dl class="row">
                    <dt class="col-5"><?php _e('Type', 'cftp_admin'); ?>:</dt>
                    <dd class="col-7">
                        <?php if ($type_config): ?>
                            <?php echo html_output($type_config['name']); ?>
                        <?php else: ?>
                            <?php echo html_output($integration['type']); ?>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-5"><?php _e('Status', 'cftp_admin'); ?>:</dt>
                    <dd class="col-7">
                        <span class="badge bg-<?php echo $integration['active'] ? 'success' : 'secondary'; ?>">
                            <?php echo $integration['active'] ? __('Active', 'cftp_admin') : __('Inactive', 'cftp_admin'); ?>
                        </span>
                    </dd>

                    <dt class="col-5"><?php _e('Created', 'cftp_admin'); ?>:</dt>
                    <dd class="col-7">
                        <?php echo date(get_option('timeformat'), strtotime($integration['created_date'])); ?>
                        <br>
                        <?php if (!empty($integration['created_by'])): ?>
                            <small class="text-muted">
                                <?php _e('by', 'cftp_admin'); ?> <?php echo html_output($integration['created_by']); ?>
                            </small>
                        <?php endif; ?>
                    </dd>

                    <?php if ($integration['updated_date']): ?>
                        <dt class="col-5"><?php _e('Updated', 'cftp_admin'); ?>:</dt>
                        <dd class="col-7">
                            <?php echo date(get_option('timeformat'), strtotime($integration['updated_date'])); ?>
                        </dd>
                    <?php endif; ?>

                    <dt class="col-5"><?php _e('Files', 'cftp_admin'); ?>:</dt>
                    <dd class="col-7">
                        <?php if ($file_count > 0): ?>
                            <span class="badge bg-info"><?php echo $file_count; ?></span>
                            <a href="manage-files.php?integration=<?php echo $integration_id; ?>" class="ms-2">
                                <?php _e('View files', 'cftp_admin'); ?>
                            </a>
                        <?php else: ?>
                            <span class="text-muted"><?php _e('No files', 'cftp_admin'); ?></span>
                        <?php endif; ?>
                    </dd>
                </dl>

                <?php if ($file_count > 0): ?>
                    <div class="alert alert-info">
                        <small>
                            <i class="fa fa-info-circle"></i>
                            <?php _e('This integration cannot be deleted because it has associated files.', 'cftp_admin'); ?>
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="ps-card mt-4">
            <div class="ps-card-body">
                <h4><?php _e('Quick Actions', 'cftp_admin'); ?></h4>

                <div class="d-grid gap-2">
                    <a href="import-external.php?integration=<?php echo $integration_id; ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fa fa-download"></i> <?php _e('Import Files', 'cftp_admin'); ?>
                    </a>

                    <?php if ($integration['active']): ?>
                        <a href="integrations.php?action=toggle&id=<?php echo $integration_id; ?>" class="btn btn-outline-warning btn-sm">
                            <i class="fa fa-pause"></i> <?php _e('Disable', 'cftp_admin'); ?>
                        </a>
                    <?php else: ?>
                        <a href="integrations.php?action=toggle&id=<?php echo $integration_id; ?>" class="btn btn-outline-success btn-sm">
                            <i class="fa fa-play"></i> <?php _e('Enable', 'cftp_admin'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>


<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
?>