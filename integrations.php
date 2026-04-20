<?php
/**
 * External Storage Integrations Management
 * Allows administrators to manage external storage connections (S3, Google Cloud, etc.)
 */
require_once 'bootstrap.php';
redirect_if_not_logged_in();

// Check for system administration permissions
if (!current_user_can('edit_settings')) {
    exit_with_error_code(403);
}

$active_nav = 'integrations';
$page_title = __('External Storage Integrations', 'cftp_admin');
$page_id = 'integrations';

$integrations_handler = new \ProjectSend\Classes\Integrations();
$available_types = \ProjectSend\Classes\Integrations::getAvailableTypes(true);

// Handle form submissions
global $flash;

// Delete integration
if (isset($_GET['action']) && $_GET['action'] == 'delete' && !empty($_GET['id'])) {
    $integration_id = (int)$_GET['id'];
    $result = $integrations_handler->delete($integration_id);

    if ($result['status'] == 'success') {
        $flash->success($result['message']);
    } else {
        $flash->error($result['message']);
    }

    ps_redirect('integrations.php');
}

// Toggle integration active status
if (isset($_GET['action']) && $_GET['action'] == 'toggle' && !empty($_GET['id'])) {
    $integration_id = (int)$_GET['id'];
    $integration = $integrations_handler->getById($integration_id);

    if ($integration) {
        $new_status = $integration['active'] ? 0 : 1;
        $result = $integrations_handler->update($integration_id, ['active' => $new_status]);

        if ($result['status'] == 'success') {
            $status_text = $new_status ? __('enabled', 'cftp_admin') : __('disabled', 'cftp_admin');
            $flash->success(sprintf(__('Integration %s successfully.', 'cftp_admin'), $status_text));
        } else {
            $flash->error($result['message']);
        }
    }

    ps_redirect('integrations.php');
}

// Test connection
if (isset($_GET['action']) && $_GET['action'] == 'test' && !empty($_GET['id'])) {
    $integration_id = (int)$_GET['id'];
    $test_result = $integrations_handler->testIntegration($integration_id);

    if ($test_result['success']) {
        $flash->success(__('Connection test successful!', 'cftp_admin'));
    } else {
        $error_message = __('Connection test failed: ', 'cftp_admin') . $test_result['message'];

        // Show detailed error information if available
        if (isset($test_result['details']) && !empty($test_result['details'])) {
            $error_message .= '<br><small><strong>' . __('Details:', 'cftp_admin') . '</strong> ' . $test_result['details'] . '</small>';
        }

        $flash->error($error_message);
    }

    ps_redirect('integrations.php');
}

// Get all integrations for display
$integrations = $integrations_handler->getAll();

// Header buttons
$header_action_buttons = [];
if (current_user_can('create_clients') || current_user_can('manage_clients')) {
    $header_action_buttons = [
        [
            'url' => 'integrations-add.php',
            'label' => __('Add Integration', 'cftp_admin'),
        ],
    ];
}

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>

<div class="row">
    <div class="col-12">
        <?php if (empty($integrations)): ?>
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i>
                <?php _e('No integrations configured yet.', 'cftp_admin'); ?>
                <a href="integrations-add.php" class="alert-link"><?php _e('Add your first integration', 'cftp_admin'); ?></a>
            </div>
        <?php else: ?>
            <!-- Debug: Show count -->
            <!-- Found <?php echo count($integrations); ?> integrations -->
            <?php
            // Generate the table using the ProjectSend Table class
            $table = new \ProjectSend\Classes\Layout\Table([
                'id' => 'integrations_tbl',
                'class' => 'footable table',
                'origin' => basename(__FILE__),
            ]);

            $thead_columns = array(
                array(
                    'content' => __('Name', 'cftp_admin'),
                ),
                array(
                    'content' => __('Type', 'cftp_admin'),
                    'hide' => 'phone',
                ),
                array(
                    'content' => __('Status', 'cftp_admin'),
                ),
                array(
                    'content' => __('Created', 'cftp_admin'),
                    'hide' => 'phone',
                ),
                array(
                    'content' => __('Files', 'cftp_admin'),
                ),
                array(
                    'content' => __('Actions', 'cftp_admin'),
                    'hide' => 'phone',
                ),
            );
            $table->thead($thead_columns);

            foreach ($integrations as $integration) {
                $table->addRow();

                // Get file count for this integration
                $file_count_query = "SELECT COUNT(*) FROM " . TABLE_FILES . " WHERE integration_id = :id";
                $file_count_stmt = $dbh->prepare($file_count_query);
                $file_count_stmt->bindParam(':id', $integration['id'], PDO::PARAM_INT);
                $file_count_stmt->execute();
                $file_count = $file_count_stmt->fetchColumn();

                $type_config = $available_types[$integration['type']] ?? null;

                // Name column
                $name_content = '<strong>' . html_output($integration['name']) . '</strong>';

                // Type column
                $type_content = '';
                if ($type_config) {
                    $type_content = '<span class="badge bg-primary">' . html_output($type_config['name']) . '</span>';
                    if (isset($type_config['coming_soon']) && $type_config['coming_soon']) {
                        $type_content .= ' <small class="text-muted">(' . __('Coming Soon', 'cftp_admin') . ')</small>';
                    }
                } else {
                    $type_content = '<span class="badge bg-secondary">' . html_output($integration['type']) . '</span>';
                }

                // Status column
                $status_badge = $integration['active']
                    ? '<span class="badge bg-success">' . __('Active', 'cftp_admin') . '</span>'
                    : '<span class="badge bg-secondary">' . __('Inactive', 'cftp_admin') . '</span>';

                // Created column
                $created_content = date(get_option('timeformat'), strtotime($integration['created_date']));
                $created_by_display = $integration['created_by_name'] ? $integration['created_by_name'] : $integration['created_by_username'];
                if ($created_by_display) {
                    $created_content .= '<br><small class="text-muted">' . __('by', 'cftp_admin') . ' ' . html_output($created_by_display) . '</small>';
                }

                // Files column
                $files_content = $file_count > 0
                    ? '<span class="badge bg-info">' . $file_count . '</span>'
                    : '<span class="text-muted">0</span>';

                // Actions column
                $action_buttons = '';

                // Test Connection button (only for non-coming-soon types)
                if (!isset($type_config['coming_soon']) || !$type_config['coming_soon']) {
                    $action_buttons .= '<a href="integrations.php?action=test&id=' . $integration['id'] . '" class="btn btn-pslight btn-sm"><i class="fa fa-plug"></i><span class="button_label">' . __('Test', 'cftp_admin') . '</span></a>' . "\n";
                }

                // Edit button
                $action_buttons .= '<a href="integrations-edit.php?id=' . $integration['id'] . '" class="btn btn-primary btn-sm"><i class="fa fa-edit"></i><span class="button_label">' . __('Edit', 'cftp_admin') . '</span></a>' . "\n";

                // Toggle active/inactive button
                if ($integration['active']) {
                    $action_buttons .= '<a href="integrations.php?action=toggle&id=' . $integration['id'] . '" class="btn btn-pslight btn-sm"><i class="fa fa-pause"></i><span class="button_label">' . __('Disable', 'cftp_admin') . '</span></a>' . "\n";
                } else {
                    $action_buttons .= '<a href="integrations.php?action=toggle&id=' . $integration['id'] . '" class="btn btn-success btn-sm"><i class="fa fa-play"></i><span class="button_label">' . __('Enable', 'cftp_admin') . '</span></a>' . "\n";
                }

                // Delete button (only if no files are using this integration)
                if ($file_count == 0) {
                    $action_buttons .= '<a href="integrations.php?action=delete&id=' . $integration['id'] . '" class="btn btn-danger btn-sm" onclick="return confirm(\'' . __('Are you sure you want to delete this integration?', 'cftp_admin') . '\')"><i class="fa fa-trash"></i><span class="button_label">' . __('Delete', 'cftp_admin') . '</span></a>' . "\n";
                }

                // Create cells array with proper format
                $tbody_cells = array(
                    array('content' => $name_content),
                    array('content' => $type_content),
                    array('content' => $status_badge),
                    array('content' => $created_content),
                    array('content' => $files_content),
                    array('actions' => true, 'content' => $action_buttons),
                );

                foreach ($tbody_cells as $cell) {
                    $table->addCell($cell);
                }

                $table->end_row();
            }

            echo $table->render();
            ?>
        <?php endif; ?>
    </div>
</div>

<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
?>