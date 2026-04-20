<?php
/**
 * Custom Fields Management
 * Allows administrators to manage custom fields for users and clients
 */
require_once 'bootstrap.php';
redirect_if_not_logged_in();

// Check for custom fields management permissions
// Always allow System Administrators, check permission for others
if (!current_role_in(['System Administrator']) && !current_user_can('manage_custom_fields')) {
    exit_with_error_code(403);
}

$active_nav = 'clients';
$page_title = __('Custom Fields', 'cftp_admin');
$page_id = 'custom_fields';

$custom_fields_handler = new \ProjectSend\Classes\CustomFields();

// Handle form submissions
global $flash;

// Delete custom field
if (isset($_GET['action']) && $_GET['action'] == 'delete' && !empty($_GET['id'])) {
    $field_id = (int)$_GET['id'];
    $field = new \ProjectSend\Classes\CustomFields($field_id);

    if ($field->fieldExists()) {
        $result = $field->delete();

        if ($result['status'] == 'success') {
            $flash->success($result['message']);
        } else {
            $flash->error($result['message']);
        }
    } else {
        $flash->error(__('Custom field not found.', 'cftp_admin'));
    }

    ps_redirect('custom-fields.php');
}

// Toggle field active status
if (isset($_GET['action']) && $_GET['action'] == 'toggle' && !empty($_GET['id'])) {
    $field_id = (int)$_GET['id'];
    $field = new \ProjectSend\Classes\CustomFields($field_id);

    if ($field->fieldExists()) {
        $new_status = $field->active ? 0 : 1;
        $field->set(['active' => $new_status]);
        $result = $field->update();

        if ($result['status'] == 'success') {
            $status_text = $new_status ? __('enabled', 'cftp_admin') : __('disabled', 'cftp_admin');
            $flash->success(sprintf(__('Custom field %s successfully.', 'cftp_admin'), $status_text));
        } else {
            $flash->error($result['message']);
        }
    } else {
        $flash->error(__('Custom field not found.', 'cftp_admin'));
    }

    ps_redirect('custom-fields.php');
}

// Get all custom fields for display
$custom_fields = \ProjectSend\Classes\CustomFields::getAll();

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

<!-- Hidden CSRF token for AJAX requests -->
<div style="display: none;">
    <?php addCsrf(); ?>
</div>

<div class="row">
    <div class="col-12">
        <?php if (empty($custom_fields)): ?>
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i>
                <?php _e('No custom fields configured yet.', 'cftp_admin'); ?>
                <a href="custom-fields-add.php" class="alert-link"><?php _e('Add your first custom field', 'cftp_admin'); ?></a>
            </div>
        <?php else: ?>
            <?php
            // Generate the table using the ProjectSend Table class
            $table = new \ProjectSend\Classes\Layout\Table([
                'id' => 'custom_fields_tbl',
                'class' => 'footable table',
                'origin' => basename(__FILE__),
            ]);

            $thead_columns = array(
                array(
                    'content' => '',
                    'class' => 'drag-handle-header text-center',
                    'style' => 'width: 60px;',
                ),
                array(
                    'content' => __('Label', 'cftp_admin'),
                ),
                array(
                    'content' => __('Name', 'cftp_admin'),
                    'hide' => 'phone',
                ),
                array(
                    'content' => __('Type', 'cftp_admin'),
                ),
                array(
                    'content' => __('Applies To', 'cftp_admin'),
                    'hide' => 'phone',
                ),
                array(
                    'content' => __('Required', 'cftp_admin'),
                    'hide' => 'phone',
                ),
                array(
                    'content' => __('Visible', 'cftp_admin'),
                    'hide' => 'phone',
                ),
                array(
                    'content' => __('Status', 'cftp_admin'),
                ),
                array(
                    'content' => __('Actions', 'cftp_admin'),
                    'hide' => 'phone',
                ),
            );
            $table->thead($thead_columns);

            foreach ($custom_fields as $field) {
                // Add class for disabled fields
                $row_class = !$field['active'] ? 'table-secondary text-muted' : '';

                $table->addRow([
                    'data-field-id' => $field['id'],
                    'class' => $row_class,
                ]);

                // Label column
                $label_content = '<strong>' . html_output($field['field_label']) . '</strong>';

                // Name column
                $name_content = '<code>' . html_output($field['field_name']) . '</code>';

                // Type column
                $type_labels = [
                    'text' => __('Text', 'cftp_admin'),
                    'textarea' => __('Textarea', 'cftp_admin'),
                    'select' => __('Select', 'cftp_admin'),
                    'checkbox' => __('Checkbox', 'cftp_admin'),
                ];
                $type_content = '<span class="badge bg-primary">' . ($type_labels[$field['field_type']] ?? $field['field_type']) . '</span>';

                // Applies To column
                $applies_to_labels = [
                    'user' => __('Users Only', 'cftp_admin'),
                    'client' => __('Clients Only', 'cftp_admin'),
                    'both' => __('Users & Clients', 'cftp_admin'),
                ];
                $applies_to_content = '<span class="badge bg-info">' . ($applies_to_labels[$field['applies_to']] ?? $field['applies_to']) . '</span>';

                // Required column
                $required_content = $field['is_required']
                    ? '<span class="badge bg-danger">' . __('Yes', 'cftp_admin') . '</span>'
                    : '<span class="text-muted">' . __('No', 'cftp_admin') . '</span>';

                // Visible column
                $visible_content = $field['is_visible_to_client']
                    ? '<span class="badge bg-success">' . __('Yes', 'cftp_admin') . '</span>'
                    : '<span class="badge bg-secondary">' . __('Hidden', 'cftp_admin') . '</span>';

                // Status column
                $status_badge = $field['active']
                    ? '<span class="badge bg-success">' . __('Active', 'cftp_admin') . '</span>'
                    : '<span class="badge bg-secondary">' . __('Inactive', 'cftp_admin') . '</span>';

                // Actions column
                $action_buttons = '';

                // Edit button
                $action_buttons .= '<a href="custom-fields-edit.php?id=' . $field['id'] . '" class="btn btn-primary btn-sm"><i class="fa fa-edit"></i><span class="button_label">' . __('Edit', 'cftp_admin') . '</span></a>' . "\n";

                // Toggle active/inactive button
                if ($field['active']) {
                    $action_buttons .= '<a href="custom-fields.php?action=toggle&id=' . $field['id'] . '" class="btn btn-pslight btn-sm"><i class="fa fa-pause"></i><span class="button_label">' . __('Disable', 'cftp_admin') . '</span></a>' . "\n";
                } else {
                    $action_buttons .= '<a href="custom-fields.php?action=toggle&id=' . $field['id'] . '" class="btn btn-success btn-sm"><i class="fa fa-play"></i><span class="button_label">' . __('Enable', 'cftp_admin') . '</span></a>' . "\n";
                }

                // Delete button
                $action_buttons .= '<a href="custom-fields.php?action=delete&id=' . $field['id'] . '" class="btn btn-danger btn-sm delete-confirm"><i class="fa fa-trash"></i><span class="button_label">' . __('Delete', 'cftp_admin') . '</span></a>' . "\n";

                // Define all cells using the proper structure
                $tbody_cells = [
                    [
                        'content' => '<i class="fa fa-arrows drag-handle" style="cursor: move;" title="' . __('Drag to reorder', 'cftp_admin') . '"></i><span class="field-id-hidden" style="display:none;">' . $field['id'] . '</span>',
                        'class' => 'text-center drag-handle-cell',
                        'attributes' => [
                            'data-field-id' => $field['id'],
                        ],
                    ],
                    [
                        'content' => $label_content,
                    ],
                    [
                        'content' => $name_content,
                        'hide' => 'phone',
                    ],
                    [
                        'content' => $type_content,
                    ],
                    [
                        'content' => $applies_to_content,
                        'hide' => 'phone',
                    ],
                    [
                        'content' => $required_content,
                        'hide' => 'phone',
                    ],
                    [
                        'content' => $visible_content,
                        'hide' => 'phone',
                    ],
                    [
                        'content' => $status_badge,
                    ],
                    [
                        'content' => $action_buttons,
                        'hide' => 'phone',
                    ],
                ];

                foreach ($tbody_cells as $cell) {
                    $table->addCell($cell);
                }
            }

            echo $table->render();
            ?>
        <?php endif; ?>
    </div>
</div>

<?php include_once ADMIN_VIEWS_DIR . DS . 'footer.php'; ?>

<script>
    // Initialize custom fields page JavaScript
    if (typeof admin !== 'undefined' && admin.pages && admin.pages.custom_fields) {
        admin.pages.custom_fields();
    }
</script>