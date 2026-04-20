<?php
/**
 * Edit an existing user role
 * Only accessible to Super Administrators (level 9)
 */
require_once 'bootstrap.php';
check_access_enhanced(['edit_settings']);

$active_nav = 'roles';

// Get role from URL
if (empty($_GET['role'])) {
    $flash->error(__('No role specified.', 'cftp_admin'));
    ps_redirect('roles.php');
}

$role_id = (int)$_GET['role'];
$role = new \ProjectSend\Classes\Roles($role_id);

if (!$role->exists()) {
    $flash->error(__('Role not found.', 'cftp_admin'));
    ps_redirect('roles.php');
}

$page_title = sprintf(__('Edit Role: %s', 'cftp_admin'), $role->name);
$page_id = 'roles_edit';

// Check if this is a system role and handle different editing levels
$is_client_role = ($role->name === 'Client');
$is_system_role = $role->is_system_role;

// Different editing permissions for different role types
if ($is_client_role) {
    // Client role is completely read-only
    $view_only = true;
    $can_edit_name = false;
} elseif ($is_system_role) {
    // Other system roles can edit description/status but not name
    $view_only = false;
    $can_edit_name = false;
} else {
    // Custom roles are fully editable
    $view_only = false;
    $can_edit_name = true;
}

// Process form submission
if ($_POST && !$is_client_role) {
    $validation_errors = [];

    // Validate required fields (only for custom roles that can change name)
    if ($can_edit_name && empty($_POST['name'])) {
        $validation_errors[] = __('Role name is required.', 'cftp_admin');
    }

    // Prevent name changes for system roles
    if (!$can_edit_name && isset($_POST['name']) && $_POST['name'] !== $role->name) {
        $validation_errors[] = __('System role names cannot be changed.', 'cftp_admin');
    }

    if (empty($validation_errors)) {
        $role_data = [
            'description' => $_POST['description'] ?? '',
            'active' => isset($_POST['active']) ? 1 : 0
        ];

        // Only include name for custom roles
        if ($can_edit_name) {
            $role_data['name'] = $_POST['name'];
        }

        // Role level changes removed - roles use auto-generated IDs

        $result = $role->update($role_data);

        if ($result) {
            $flash->success(__('Role updated successfully.', 'cftp_admin'));

            // Redirect to updated role using role ID
            ps_redirect('roles-edit.php?role=' . $role->id);
        } else {
            $flash->error(__('Could not update role. Please try again.', 'cftp_admin'));
        }
    } else {
        foreach ($validation_errors as $error) {
            $flash->error($error);
        }
    }
}

// Role levels are no longer used - roles use auto-generated IDs

// Get user count for this role
$user_count = $role->getUserCount();

// Header buttons
$header_action_buttons = [
    [
        'url' => 'role-permissions.php?role='.$role->id,
        'label' => __('Manage permissions', 'cftp_admin'),
        'icon' => 'fa fa-key'
    ],
    [
        'url' => 'roles.php',
        'label' => __('Back to Roles', 'cftp_admin'),
        'type' => 'light',
        'icon' => 'fa fa-arrow-left',
    ],
];

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>

<div class="row">
    <div class="col-12 col-lg-8">
        <div class="ps-card">
            <div class="ps-card-body">
                <h5>
                    <?php _e('Role Information', 'cftp_admin'); ?>
                    <?php if ($role->is_system_role): ?>
                        <span class="badge bg-primary ms-2"><?php _e('System Role', 'cftp_admin'); ?></span>
                    <?php endif; ?>
                </h5>
                <?php if ($is_system_role): ?>
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i>
                        <?php if ($is_client_role): ?>
                            <?php _e('The Client role is a core system role and cannot be edited.', 'cftp_admin'); ?>
                        <?php else: ?>
                            <?php _e('This is a system role. The role name cannot be changed as it may break system functionality.', 'cftp_admin'); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($is_client_role): ?>
                    <!-- View-only mode for Client role -->
                    <div class="form-group row">
                        <label class="col-sm-3 control-label"><?php _e('Role Name', 'cftp_admin'); ?></label>
                        <div class="col-sm-9">
                            <p class="form-control-static"><?php echo html_output($role->name); ?></p>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-3 control-label"><?php _e('Description', 'cftp_admin'); ?></label>
                        <div class="col-sm-9">
                            <p class="form-control-static"><?php echo html_output($role->description); ?></p>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-3 control-label"><?php _e('Status', 'cftp_admin'); ?></label>
                        <div class="col-sm-9">
                            <p class="form-control-static">
                                <?php if ($role->active): ?>
                                    <span class="badge bg-success"><?php _e('Active', 'cftp_admin'); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?php _e('Inactive', 'cftp_admin'); ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Editable form for other roles -->
                    <form action="" method="post" class="form-horizontal" id="role_form">
                        <?php addCsrf(); ?>

                        <div class="form-group row">
                            <label for="name" class="col-sm-3 control-label"><?php _e('Role Name', 'cftp_admin'); ?></label>
                            <div class="col-sm-9">
                                <input type="text" name="name" id="name" class="form-control required"
                                       value="<?php echo html_output($role->name); ?>"
                                       maxlength="255" required />
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="description" class="col-sm-3 control-label"><?php _e('Description', 'cftp_admin'); ?></label>
                            <div class="col-sm-9">
                                <textarea name="description" id="description" class="form-control" rows="3"><?php echo html_output($role->description); ?></textarea>
                            </div>
                        </div>

                        <!-- Role level fields removed - roles now use auto-generated IDs -->

                        <div class="form-group row">
                            <div class="col-sm-9 offset-sm-3">
                                <div class="form-check">
                                    <input type="checkbox" name="active" id="active" class="form-check-input" value="1"
                                           <?php echo $role->active ? 'checked' : ''; ?> />
                                    <label for="active" class="form-check-label"><?php _e('Active', 'cftp_admin'); ?></label>
                                    <small class="form-text text-muted"><?php _e('Inactive roles cannot be assigned to users', 'cftp_admin'); ?></small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-sm-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-check"></i> <?php _e('Update Role', 'cftp_admin'); ?>
                                </button>
                                <a href="roles.php" class="btn btn-secondary">
                                    <?php _e('Cancel', 'cftp_admin'); ?>
                                </a>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="ps-card">
            <div class="ps-card-body">
                <h5><?php _e('Role Statistics', 'cftp_admin'); ?></h5>
                <div class="row text-center">
                    <div class="col-6">
                        <h2 class="text-primary"><?php echo $user_count; ?></h2>
                        <p class="text-muted mb-0"><?php _e('Users', 'cftp_admin'); ?></p>
                    </div>
                    <div class="col-6">
                        <h2 class="text-info"><?php echo count($role->permissions); ?></h2>
                        <p class="text-muted mb-0"><?php _e('Permissions', 'cftp_admin'); ?></p>
                    </div>
                </div>

                <?php if ($user_count > 0): ?>
                    <hr>
                    <?php if ($is_client_role): ?>
                        <a href="clients.php" class="btn btn-sm btn-primary">
                            <i class="fa fa-users"></i> <?php _e('View Clients', 'cftp_admin'); ?>
                        </a>
                    <?php else: ?>
                        <a href="users.php?role=<?php echo $role->id; ?>" class="btn btn-sm btn-primary">
                            <i class="fa fa-users"></i> <?php _e('View Users with this Role', 'cftp_admin'); ?>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$role->is_system_role && $user_count == 0): ?>
            <div class="ps-card mt-3">
                <div class="ps-card-body">
                    <h5><?php _e('Quick Actions', 'cftp_admin'); ?></h5>
                    <div class="d-grid gap-2">
                        <?php if (!$role->is_system_role && $user_count == 0): ?>
                            <button type="button" class="btn btn-danger" id="delete-role">
                                <i class="fa fa-trash"></i> <?php _e('Delete Role', 'cftp_admin'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($role->is_system_role): ?>
            <div class="ps-card mt-3">
                <div class="ps-card-body">
                    <h5><?php _e('System Role Info', 'cftp_admin'); ?></h5>
                    <p class="text-muted mb-0"><?php _e('System roles are built into ProjectSend and provide core functionality. They cannot be deleted and have limited editability.', 'cftp_admin'); ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>


<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
?>