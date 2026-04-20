<?php
/**
 * Show the form to add a new custom field.
 */
require_once 'bootstrap.php';
redirect_if_not_logged_in();

// Check for custom fields management permissions
// Always allow System Administrators, check permission for others
if (!current_role_in(['System Administrator']) && !current_user_can('manage_custom_fields')) {
    exit_with_error_code(403);
}

$active_nav = 'clients';
$page_title = __('Add Custom Field', 'cftp_admin');
$page_id = 'custom_fields_form';

global $flash;

// Handle form submission
if ($_POST) {
    $custom_field = new \ProjectSend\Classes\CustomFields();

    $field_data = [
        'field_name' => $_POST['field_name'] ?? '',
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
    $result = $custom_field->create();

    if ($result['status'] === 'success') {
        $flash->success($result['message']);
        ps_redirect(BASE_URI . 'custom-fields-edit.php?id=' . $custom_field->getId());
    } else {
        $flash->error($result['message']);
        // Don't redirect on failure, let the form render with preserved POST data
    }
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

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>

<div class="row">
    <div class="col-12 col-lg-8">
        <div class="ps-card">
            <div class="ps-card-body">
                <form action="custom-fields-add.php" method="post" id="custom_field_form">
                    <?php addCsrf(); ?>

                    <div class="mb-4">
                        <label for="field_label" class="form-label"><?php _e('Field Label', 'cftp_admin'); ?> <span class="required-indicator" aria-label="required">*</span></label>
                        <input type="text" name="field_label" id="field_label" class="form-control"
                               value="<?php echo isset($_POST['field_label']) ? html_output($_POST['field_label']) : ''; ?>"
                               required maxlength="255">
                        <div class="form-text"><?php _e('The label that will be displayed to users (e.g., "Company Name", "Department").', 'cftp_admin'); ?></div>
                    </div>

                    <div class="mb-4">
                        <label for="field_name" class="form-label"><?php _e('Field Name', 'cftp_admin'); ?> <span class="required-indicator" aria-label="required">*</span></label>
                        <input type="text" name="field_name" id="field_name" class="form-control"
                               value="<?php echo isset($_POST['field_name']) ? html_output($_POST['field_name']) : ''; ?>"
                               required maxlength="100" pattern="[a-z0-9_]+">
                        <div class="form-text"><?php _e('Internal field identifier. Use only lowercase letters, numbers, and underscores (e.g., "company_name", "department").', 'cftp_admin'); ?></div>
                    </div>

                    <div class="mb-4">
                        <label for="field_type" class="form-label"><?php _e('Field Type', 'cftp_admin'); ?> <span class="required-indicator" aria-label="required">*</span></label>
                        <select name="field_type" id="field_type" class="form-select" required>
                            <?php foreach ($field_types as $type => $label): ?>
                                <option value="<?php echo $type; ?>"
                                        <?php echo (isset($_POST['field_type']) && $_POST['field_type'] == $type) ? 'selected' : ''; ?>>
                                    <?php echo html_output($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text"><?php _e('Choose the input type for this field.', 'cftp_admin'); ?></div>
                    </div>

                    <div class="mb-4" id="field_options_container" <?php echo (isset($_POST['field_type']) && !in_array($_POST['field_type'], ['select', 'checkbox'])) ? 'style="display: none;"' : ''; ?>>
                        <label for="field_options" class="form-label"><?php _e('Field Options / Label', 'cftp_admin'); ?> <span class="required-indicator" aria-label="required">*</span></label>
                        <textarea name="field_options" id="field_options" class="form-control" rows="5"><?php echo isset($_POST['field_options']) ? html_output($_POST['field_options']) : ''; ?></textarea>
                        <div class="form-text" id="field_options_help">
                            <?php _e('For select fields: Enter one option per line.', 'cftp_admin'); ?><br>
                            <?php _e('For checkbox fields: Enter the checkbox label (e.g., "I agree to the terms").', 'cftp_admin'); ?>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="default_value" class="form-label"><?php _e('Default Value', 'cftp_admin'); ?></label>
                        <input type="text" name="default_value" id="default_value" class="form-control"
                               value="<?php echo isset($_POST['default_value']) ? html_output($_POST['default_value']) : ''; ?>">
                        <div class="form-text"><?php _e('Optional default value that will be pre-filled when creating new users/clients.', 'cftp_admin'); ?></div>
                    </div>

                    <div class="mb-4">
                        <label for="applies_to" class="form-label"><?php _e('Applies To', 'cftp_admin'); ?> <span class="required-indicator" aria-label="required">*</span></label>
                        <select name="applies_to" id="applies_to" class="form-select" required>
                            <?php foreach ($applies_to_options as $value => $label): ?>
                                <option value="<?php echo $value; ?>"
                                        <?php echo (isset($_POST['applies_to']) && $_POST['applies_to'] == $value) || (!isset($_POST['applies_to']) && $value == 'client') ? 'selected' : ''; ?>>
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
                                       <?php echo (isset($_POST['is_required']) && $_POST['is_required']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_required">
                                    <?php _e('Required Field', 'cftp_admin'); ?>
                                </label>
                                <div class="form-text"><?php _e('Users must fill this field.', 'cftp_admin'); ?></div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-check mb-3">
                                <input type="checkbox" name="is_visible_to_client" id="is_visible_to_client" class="form-check-input"
                                       <?php echo (!isset($_POST['is_visible_to_client']) || $_POST['is_visible_to_client']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_visible_to_client">
                                    <?php _e('Visible to Users', 'cftp_admin'); ?>
                                </label>
                                <div class="form-text"><?php _e('Show in registration and profile edit forms.', 'cftp_admin'); ?></div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-check mb-3">
                                <input type="checkbox" name="active" id="active" class="form-check-input"
                                       <?php echo (!isset($_POST['active']) || $_POST['active']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="active">
                                    <?php _e('Active', 'cftp_admin'); ?>
                                </label>
                                <div class="form-text"><?php _e('Field is active and available for use.', 'cftp_admin'); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <?php _e('Create Custom Field', 'cftp_admin'); ?>
                        </button>
                        <a href="custom-fields.php" class="btn btn-light">
                            <?php _e('Cancel', 'cftp_admin'); ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="ps-card">
            <div class="ps-card-body">
                <h5 class="card-title"><?php _e('Field Types', 'cftp_admin'); ?></h5>

                <div class="mb-3">
                    <h6><?php _e('Text Input', 'cftp_admin'); ?></h6>
                    <p class="text-muted small"><?php _e('Single-line text input for short text values.', 'cftp_admin'); ?></p>
                </div>

                <div class="mb-3">
                    <h6><?php _e('Textarea', 'cftp_admin'); ?></h6>
                    <p class="text-muted small"><?php _e('Multi-line text input for longer text values.', 'cftp_admin'); ?></p>
                </div>

                <div class="mb-3">
                    <h6><?php _e('Select Dropdown', 'cftp_admin'); ?></h6>
                    <p class="text-muted small"><?php _e('Dropdown menu with predefined options. Requires field options to be specified.', 'cftp_admin'); ?></p>
                </div>

                <div class="mb-3">
                    <h6><?php _e('Checkbox', 'cftp_admin'); ?></h6>
                    <p class="text-muted small"><?php _e('Simple yes/no checkbox for boolean values.', 'cftp_admin'); ?></p>
                </div>
            </div>
        </div>

        <div class="ps-card">
            <div class="ps-card-body">
                <h5 class="card-title"><?php _e('Visibility Settings', 'cftp_admin'); ?></h5>
                <p class="text-muted small"><?php _e('Visible fields appear in registration forms and user profile editing. Hidden fields are only visible to administrators in the user/client lists.', 'cftp_admin'); ?></p>
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