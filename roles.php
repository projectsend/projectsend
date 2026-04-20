<?php
/**
 * Show the list of user roles and their permissions
 * Only accessible to Super Administrators (level 9)
 */
require_once 'bootstrap.php';
check_access_enhanced(['edit_settings']);

$active_nav = 'roles';
$page_title = __('User Roles Management', 'cftp_admin');
$page_id = 'roles';

// Get all roles
$roles = get_all_roles();

// Results count and form actions
$elements_found_count = count($roles);
$bulk_actions_items = []; // No bulk actions for roles

// Header buttons
$header_action_buttons = [];
if (custom_roles_enabled()) {
    $header_action_buttons = [
        [
            'url' => 'roles-add.php',
            'label' => __('Add new role', 'cftp_admin'),
        ],
    ];
}

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>

<div class="row">
    <div class="col-12">
        <?php include_once LAYOUT_DIR . DS . 'form-counts-actions.php'; ?>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <?php if (!custom_roles_enabled()): ?>
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i>
                <?php _e('Custom roles are currently disabled. Only the default system roles are available.', 'cftp_admin'); ?>
                <a href="options.php?section=advanced" class="btn btn-sm btn-light ms-2">
                    <?php _e('Enable Custom Roles', 'cftp_admin'); ?>
                </a>
            </div>
        <?php endif; ?>

        <?php if (empty($roles)): ?>
            <p class="text-muted"><?php _e('No roles found.', 'cftp_admin'); ?></p>
        <?php else: ?>
            <?php
            // Generate the table using the class.
            $table = new \ProjectSend\Classes\Layout\Table([
                'id' => 'roles_tbl',
                'class' => 'footable table',
                'origin' => basename(__FILE__),
            ]);

            $thead_columns = array(
                array(
                    'content' => __('Role Name', 'cftp_admin'),
                ),
                array(
                    'content' => __('Description', 'cftp_admin'),
                    'hide' => 'phone',
                ),
                array(
                    'content' => __('Users', 'cftp_admin'),
                ),
                array(
                    'content' => __('Permissions', 'cftp_admin'),
                    'hide' => 'phone',
                ),
                array(
                    'content' => __('Type', 'cftp_admin'),
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

            foreach ($roles as $role) {
                $table->addRow();

                $role_obj = new \ProjectSend\Classes\Roles($role['id']);
                $user_count = $role_obj->getUserCount();
                $permissions = get_role_permissions($role['id']);

                // Determine role type badge
                $role_badge = '';
                $badge_class = 'secondary';
                if ($role['name'] == 'System Administrator') {
                    $role_badge = 'Super Admin';
                    $badge_class = 'danger';
                } elseif ($role['name'] == 'Account Manager') {
                    $role_badge = 'Admin';
                    $badge_class = 'warning';
                } elseif ($role['name'] == 'Uploader') {
                    $role_badge = 'User';
                    $badge_class = 'info';
                } elseif ($role['name'] == 'Client') {
                    $role_badge = 'Client';
                    $badge_class = 'primary';
                } else {
                    $role_badge = 'Custom';
                    $badge_class = 'success';
                }

                // Status badge
                $status_badge_label = $role['active'] ? __('Active', 'cftp_admin') : __('Inactive', 'cftp_admin');
                $status_badge_class = $role['active'] ? 'bg-success' : 'bg-secondary';

                // Type badge
                $type_badge = $role['is_system_role'] ? __('System', 'cftp_admin') : __('Custom', 'cftp_admin');
                $type_badge_class = $role['is_system_role'] ? 'bg-primary' : 'bg-success';

                // Build action buttons
                $action_buttons = '';

                // Permissions button - different styles based on editability
                // if ($role['permissions_editable']) {
                if ($role['name'] == 'System Administrator') {
                    $action_buttons .= '<a href="role-permissions.php?role=' . $role['id'] . '" class="btn btn-pslight btn-sm"><i class="fa fa-key"></i><span class="button_label">' . __('View Permissions', 'cftp_admin') . '</span></a>' . "\n";
                } else {
                    $action_buttons .= '<a href="role-permissions.php?role=' . $role['id'] . '" class="btn btn-primary btn-sm"><i class="fa fa-key"></i><span class="button_label">' . __('Permissions', 'cftp_admin') . '</span></a>' . "\n";
                }

                // View Users/Clients button
                if ($role['name'] === 'Client') {
                    $action_buttons .= '<a href="clients.php" class="btn btn-primary btn-sm"><i class="fa fa-users"></i><span class="button_label">' . __('View', 'cftp_admin') . '</span></a>' . "\n";
                } else {
                    if ($user_count > 0) {
                        $action_buttons .= '<a href="users.php?role=' . $role['id'] . '" class="btn btn-primary btn-sm"><i class="fa fa-users"></i><span class="button_label">' . __('View', 'cftp_admin') . '</span></a>' . "\n";
                    } else {
                        $action_buttons .= '<a href="~" class="btn btn-pslight btn-sm disabled" disabled><i class="fa fa-users"></i><span class="button_label">' . __('View', 'cftp_admin') . '</span></a>' . "\n";
                    }
                }

                // Edit/View button
                if (!$role['is_system_role'] && custom_roles_enabled()) {
                    $action_buttons .= '<a href="roles-edit.php?role=' . $role['id'] . '" class="btn btn-primary btn-sm"><i class="fa fa-pencil"></i><span class="button_label">' . __('Edit', 'cftp_admin') . '</span></a>' . "\n";
                } else {
                    $action_buttons .= '<a href="roles-edit.php?role=' . $role['id'] . '" class="btn btn-pslight btn-sm"><i class="fa fa-eye"></i><span class="button_label">' . __('View', 'cftp_admin') . '</span></a>' . "\n";
                }

                // Delete button
                if (!$role['is_system_role'] && custom_roles_enabled()) {
                    $action_buttons .= '<button type="button" class="btn btn-danger btn-sm delete-role" data-role="' . $role['id'] . '" data-name="' . html_output($role['name']) . '" data-user-count="' . $user_count . '"><i class="fa fa-trash"></i><span class="button_label">' . __('Delete', 'cftp_admin') . '</span></button>' . "\n";
                }

                // Add the cells to the row
                $tbody_cells = array(
                    array(
                        'content' => '<strong>' . html_output($role['name']) . '</strong> <span class="d-none badge bg-' . $badge_class . ' ms-2">' . $role_badge . '</span>',
                    ),
                    array(
                        'content' => html_output($role['description']),
                    ),
                    array(
                        'content' => '<span class="badge bg-light text-dark">' . $user_count . '</span>',
                    ),
                    array(
                        'content' => '<span class="badge bg-info">' . count($permissions) . '</span>',
                    ),
                    array(
                        'content' => '<span class="badge ' . $type_badge_class . '">' . $type_badge . '</span>',
                    ),
                    array(
                        'content' => '<span class="badge ' . $status_badge_class . '">' . $status_badge_label . '</span>',
                    ),
                    array(
                        'actions' => true,
                        'content' => $action_buttons,
                    ),
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

<!-- User Reassignment Modal -->
<div class="modal fade" id="reassignUsersModal" tabindex="-1" role="dialog" aria-labelledby="reassignUsersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reassignUsersModalLabel"><?php _e('Delete Role - Reassign Users', 'cftp_admin'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fa fa-warning"></i>
                    <?php _e('This role has users assigned to it. You must reassign these users to another role before deleting this role.', 'cftp_admin'); ?>
                </div>

                <div class="mb-3">
                    <label for="new-role-select" class="form-label"><?php _e('Reassign users to:', 'cftp_admin'); ?></label>
                    <select class="form-select" id="new-role-select" required>
                        <option value=""><?php _e('Select a role...', 'cftp_admin'); ?></option>
                        <!-- Options populated via JavaScript -->
                    </select>
                </div>

                <div class="mb-3">
                    <h6><?php _e('Users to be reassigned:', 'cftp_admin'); ?></h6>
                    <div id="users-list" class="border rounded p-3 bg-light">
                        <!-- Users populated via JavaScript -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php _e('Cancel', 'cftp_admin'); ?></button>
                <button type="button" class="btn btn-danger" id="confirm-role-delete" disabled><?php _e('Reassign Users & Delete Role', 'cftp_admin'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form for CSRF token -->
<form style="display: none;" id="csrf-form">
    <?php addCsrf(); ?>
</form>

<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
?>