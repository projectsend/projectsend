<?php
/**
 * Show the form to edit an existing custom field.
 */
require_once 'bootstrap.php';
redirect_if_not_logged_in();

// Check for custom fields management permissions
// Always allow System Administrators, check permission for others
if (!current_role_in(['System Administrator']) && !current_user_can('manage_custom_fields')) {
    exit_with_error_code(403);
}

// Check if the id parameter is on the URI.
if (!isset($_GET['id'])) {
    exit_with_error_code(403);
}

$field_id = (int)$_GET['id'];
$custom_field = new \ProjectSend\Classes\CustomFields($field_id);

if (!$custom_field->fieldExists()) {
    exit_with_error_code(403);
}

$active_nav = 'clients';
$page_title = __('Edit Custom Field', 'cftp_admin');
$page_id = 'custom_fields_form';

global $flash;

// Get field properties first
$field_properties = $custom_field->getProperties();

// Handle form submission
if ($_POST) {
    // Preserve the original field_name - never allow it to be changed
    $field_data = [
        'field_name' => $field_properties['field_name'], // Always use existing field_name
        'field_label' => $_POST['field_label'] ?? '',
        'field_type' => $_POST['field_type'] ?? 'text',
        'field_options' => $_POST['field_options'] ?? '',
        'default_value' => $_POST['default_value'] ?? '',
        'is_required' => isset($_POST['is_required']) ? 1 : 0,
        'is_visible_to_client' => isset($_POST['is_visible_to_client']) ? 1 : 0,
        'applies_to' => $_POST['applies_to'] ?? 'client',
        'active' => isset($_POST['active']) ? 1 : 0,
    ];

    $custom_field->set($field_data);
    $result = $custom_field->update();

    if ($result['status'] === 'success') {
        $flash->success($result['message']);
    } else {
        $flash->error($result['message']);
    }

    ps_redirect('custom-fields-edit.php?id=' . $field_id);
}

$field_types = [
    'text' => __('Text Input', 'cftp_admin'),
    'textarea' => __('Textarea', 'cftp_admin'),
    'select' => __('Select Dropdown', 'cftp_admin'),
    'checkbox' => __('Checkbox', 'cftp_admin'),
];

$applies_to_options = [
    'client' => __('Clients Only', 'cftp_admin'),
    'user' => __('Users Only', 'cftp_admin'),
    'both' => __('Both Users and Clients', 'cftp_admin'),
];

// Header buttons
$header_action_buttons = [];
if (current_user_can('manage_custom_fields')) {
    $header_action_buttons = [
        [
            'url' => 'custom-fields-add.php',
            'label' => __('Add Custom Field', 'cftp_admin'),
        ],
    ];
}

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>

<div class="row">
    <div class="col-12 col-lg-8">
        <div class="ps-card">
            <div class="ps-card-body">
                <form action="custom-fields-edit.php?id=<?php echo $field_id; ?>" method="post" id="custom_field_form">
                    <?php addCsrf(); ?>

                    <div class="mb-4">
                        <label for="field_label" class="form-label"><?php _e('Field Label', 'cftp_admin'); ?> <span class="required-indicator" aria-label="required">*</span></label>
                        <input type="text" name="field_label" id="field_label" class="form-control"
                               value="<?php echo html_output($field_properties['field_label']); ?>"
                               required maxlength="255">
                        <div class="form-text"><?php _e('The label that will be displayed to users (e.g., "Company Name", "Department").', 'cftp_admin'); ?></div>
                    </div>

                    <div class="mb-4">
                        <label for="field_name" class="form-label"><?php _e('Field Name', 'cftp_admin'); ?></label>
                        <input type="text" name="field_name_display" id="field_name" class="form-control"
                               value="<?php echo html_output($field_properties['field_name']); ?>"
                               readonly disabled>
                        <input type="hidden" name="field_name" value="<?php echo html_output($field_properties['field_name']); ?>">
                        <div class="form-text text-muted"><?php _e('Field names cannot be changed after creation to maintain data integrity.', 'cftp_admin'); ?></div>
                    </div>

                    <div class="mb-4">
                        <label for="field_type" class="form-label"><?php _e('Field Type', 'cftp_admin'); ?> <span class="required-indicator" aria-label="required">*</span></label>
                        <select name="field_type" id="field_type" class="form-select" required>
                            <?php foreach ($field_types as $type => $label): ?>
                                <option value="<?php echo $type; ?>"
                                        <?php echo ($field_properties['field_type'] == $type) ? 'selected' : ''; ?>>
                                    <?php echo html_output($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text"><?php _e('Choose the input type for this field.', 'cftp_admin'); ?></div>
                    </div>

                    <div class="mb-4" id="field_options_container" <?php echo (!in_array($field_properties['field_type'], ['select', 'checkbox'])) ? 'style="display: none;"' : ''; ?>>
                        <label for="field_options" class="form-label"><?php _e('Field Options / Label', 'cftp_admin'); ?> <span class="required-indicator" aria-label="required">*</span></label>
                        <textarea name="field_options" id="field_options" class="form-control" rows="5"><?php echo html_output($field_properties['field_options']); ?></textarea>
                        <div class="form-text" id="field_options_help">
                            <?php _e('For select fields: Enter one option per line.', 'cftp_admin'); ?><br>
                            <?php _e('For checkbox fields: Enter the checkbox label (e.g., "I agree to the terms").', 'cftp_admin'); ?>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="default_value" class="form-label"><?php _e('Default Value', 'cftp_admin'); ?></label>
                        <input type="text" name="default_value" id="default_value" class="form-control"
                               value="<?php echo html_output($field_properties['default_value']); ?>">
                        <div class="form-text"><?php _e('Optional default value that will be pre-filled when creating new users/clients.', 'cftp_admin'); ?></div>
                    </div>

                    <div class="mb-4">
                        <label for="applies_to" class="form-label"><?php _e('Applies To', 'cftp_admin'); ?> <span class="required-indicator" aria-label="required">*</span></label>
                        <select name="applies_to" id="applies_to" class="form-select" required>
                            <?php foreach ($applies_to_options as $value => $label): ?>
                                <option value="<?php echo $value; ?>"
                                        <?php echo ($field_properties['applies_to'] == $value) ? 'selected' : ''; ?>>
                                    <?php echo html_output($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text"><?php _e('Choose whether this field applies to users, clients, or both.', 'cftp_admin'); ?></div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-check mb-3">
                                <input type="checkbox" name="is_required" id="is_required" class="form-check-input"
                                       <?php echo $field_properties['is_required'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_required">
                                    <?php _e('Required Field', 'cftp_admin'); ?>
                                </label>
                                <div class="form-text"><?php _e('Users must fill this field.', 'cftp_admin'); ?></div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-check mb-3">
                                <input type="checkbox" name="is_visible_to_client" id="is_visible_to_client" class="form-check-input"
                                       <?php echo $field_properties['is_visible_to_client'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_visible_to_client">
                                    <?php _e('Visible to Users', 'cftp_admin'); ?>
                                </label>
                                <div class="form-text"><?php _e('Show in registration and profile edit forms.', 'cftp_admin'); ?></div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-check mb-3">
                                <input type="checkbox" name="active" id="active" class="form-check-input"
                                       <?php echo $field_properties['active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="active">
                                    <?php _e('Active', 'cftp_admin'); ?>
                                </label>
                                <div class="form-text"><?php _e('Field is active and available for use.', 'cftp_admin'); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <?php _e('Update Custom Field', 'cftp_admin'); ?>
                        </button>
                        <a href="custom-fields.php" class="btn btn-light">
                            <?php _e('Back to List', 'cftp_admin'); ?>
                        </a>
                        <a href="custom-fields.php?action=delete&id=<?php echo $field_id; ?>" class="btn btn-danger delete-confirm">
                            <?php _e('Delete Field', 'cftp_admin'); ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="ps-card">
            <div class="ps-card-body">
                <h5 class="card-title"><?php _e('Field Information', 'cftp_admin'); ?></h5>

                <div class="mb-3">
                    <strong><?php _e('Created:', 'cftp_admin'); ?></strong><br>
                    <span class="text-muted"><?php echo format_date($field_properties['created_date']); ?></span>
                </div>

                <div class="mb-3">
                    <strong><?php _e('Sort Order:', 'cftp_admin'); ?></strong><br>
                    <span class="text-muted"><?php echo $field_properties['sort_order']; ?></span>
                </div>
            </div>
        </div>

        <div class="ps-card">
            <div class="ps-card-body">
                <h5 class="card-title"><?php _e('Field Usage', 'cftp_admin'); ?></h5>
                <p class="text-muted small"><?php _e('This field can be used in forms based on its visibility and "applies to" settings. Changes to the field type may affect existing data.', 'cftp_admin'); ?></p>

                <?php
                // Get count of users/clients with values for this field
                $values_handler = new \ProjectSend\Classes\CustomFieldValues();
                global $dbh;
                $count_stmt = $dbh->prepare("SELECT COUNT(*) FROM " . TABLE_CUSTOM_FIELD_VALUES . " WHERE field_id = :field_id");
                $count_stmt->bindParam(':field_id', $field_id, PDO::PARAM_INT);
                $count_stmt->execute();
                $usage_count = $count_stmt->fetchColumn();

                if ($usage_count > 0) {
                    echo '<p class="text-info small">';
                    echo sprintf(__('This field has values for %d users/clients.', 'cftp_admin'), $usage_count);
                    echo '</p>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<?php include_once ADMIN_VIEWS_DIR . DS . 'footer.php'; ?>

<script>
    // Initialize custom fields form page JavaScript
    if (typeof admin !== 'undefined' && admin.pages && admin.pages.custom_fields_form) {
        admin.pages.custom_fields_form();
    }
</script>