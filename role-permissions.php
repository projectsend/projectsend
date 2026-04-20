<?php
/**
 * Manage permissions for a specific role
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

$page_title = sprintf(__('Manage Permissions: %s', 'cftp_admin'), $role->name);
$page_id = 'role_permissions';

// Define Client role editable permissions
$client_editable_permissions = [
    'upload',
    'delete_files',
    'set_file_expiration_date',
    'upload_public',
    'upload_to_public_folders',
    'set_file_categories'
];

// Process form submission
if ($_POST) {
    // Check if role permissions can be edited
    if (!$role->permissions_editable && $role->name !== 'Client') {
        if ($role->name == 'System Administrator') {
            $flash->error(__('System Administrator permissions cannot be modified. System Admin always has ALL permissions.', 'cftp_admin'));
        } else {
            $flash->error(__('This role\'s permissions cannot be modified.', 'cftp_admin'));
        }
        ps_redirect('role-permissions.php?role=' . $role->id);
    }

    // For Client role, only allow editing specific permissions
    if ($role->name == 'Client') {
        $submitted_permissions = $_POST['permissions'] ?? [];
        $current_permissions = $role->permissions;

        // Start with current permissions
        $new_permissions = $current_permissions;

        // Update only the editable permissions
        foreach ($client_editable_permissions as $perm) {
            if (in_array($perm, $submitted_permissions)) {
                if (!in_array($perm, $new_permissions)) {
                    $new_permissions[] = $perm;
                }
            } else {
                $new_permissions = array_diff($new_permissions, [$perm]);
            }
        }

        $_POST['permissions'] = $new_permissions;
    }

    $new_permissions = $_POST['permissions'] ?? [];

    // Ensure it's an array
    if (!is_array($new_permissions)) {
        $new_permissions = [];
    }

    $result = $role->setPermissions($new_permissions);

    if ($result) {
        $flash->success(__('Permissions updated successfully.', 'cftp_admin'));
        ps_redirect('role-permissions.php?role=' . $role->id);
    } else {
        $flash->error(__('Could not update permissions. Please try again.', 'cftp_admin'));
    }
}

// Get all permissions grouped by category
$permissions_grouped = get_permissions_grouped_by_category();
$permission_categories = get_permission_categories();

// Get current role permissions
$current_permissions = $role->permissions;

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="row">
            <div class="col-xs-12 col-sm-12 col-lg-6">
                <?php if ($role->is_system_role): ?>
                    <span class="badge bg-primary ms"><?php _e('System Role', 'cftp_admin'); ?></span>
                <?php endif; ?>
            </div>
            <div class="col-xs-12 col-sm-12 col-lg-6 text-end">
                <a href="roles-edit.php?role=<?php echo $role->id; ?>" class="btn btn-secondary">
                    <i class="fa fa-edit"></i> <?php _e('Edit Role', 'cftp_admin'); ?>
                </a>
                <a href="roles.php" class="btn btn-secondary">
                    <i class="fa fa-arrow-left"></i> <?php _e('Back to Roles', 'cftp_admin'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="ps-card">
            <div class="ps-card-body">
                <div class="row align-items-center mb-3">
                    <div class="col">
                    </div>
                    <?php if ($role->permissions_editable): ?>
                    <div class="col-auto">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-success" id="select-all">
                                <?php _e('Select All', 'cftp_admin'); ?>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" id="select-none">
                                <?php _e('Select None', 'cftp_admin'); ?>
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <form action="" method="post" id="permissions_form">
                    <?php addCsrf(); ?>

                    <div class="row">
                        <div class="col-12">
                            <?php if (!$role->permissions_editable && $role->name !== 'Client'): ?>
                                <div class="alert alert-info">
                                    <i class="fa fa-lock"></i>
                                    <?php if ($role->name == 'System Administrator'): ?>
                                        <strong><?php _e('System Administrator Role', 'cftp_admin'); ?></strong><br>
                                        <?php _e('System Administrator permissions cannot be modified. This role automatically has ALL permissions for security and system integrity.', 'cftp_admin'); ?>
                                    <?php else: ?>
                                        <strong><?php _e('Protected Role', 'cftp_admin'); ?></strong><br>
                                        <?php _e('This role\'s permissions cannot be modified.', 'cftp_admin'); ?>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($role->name == 'Client'): ?>
                                <div class="alert alert-warning">
                                    <i class="fa fa-info-circle"></i>
                                    <strong><?php _e('Client Role - Limited Editing', 'cftp_admin'); ?></strong><br>
                                    <?php _e('Only certain permissions can be modified for the Client role. These correspond to the client settings that were previously in the Options page.', 'cftp_admin'); ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">
                                    <?php _e('Select the permissions this role should have. Users with this role will be able to perform the selected actions.', 'cftp_admin'); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php foreach ($permissions_grouped as $category => $permissions): ?>
                        <div class="permission-category mb-4">
                            <div class="row">
                                <div class="col-12">
                                    <h6 class="text-primary border-bottom pb-2">
                                        <i class="fa fa-<?php
                                        switch($category) {
                                            case 'files': echo 'file'; break;
                                            case 'users': echo 'users'; break;
                                            case 'groups': echo 'th-large'; break;
                                            case 'system': echo 'cogs'; break;
                                            case 'dashboard': echo 'dashboard'; break;
                                            case 'categories': echo 'tags'; break;
                                            case 'assets': echo 'code'; break;
                                            default: echo 'circle';
                                        }
                                        ?>"></i>
                                        <?php echo $permission_categories[$category]; ?>
                                        <?php if ($role->permissions_editable): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary ms-2 category-toggle"
                                                data-category="<?php echo $category; ?>">
                                            <?php _e('Toggle All', 'cftp_admin'); ?>
                                        </button>
                                        <?php endif; ?>
                                    </h6>
                                </div>
                            </div>

                            <div class="row">
                                <?php foreach ($permissions as $permission_key => $permission_data): ?>
                                    <?php if ($permission_key === 'edit_self_account') continue; // Skip - always granted ?>
                                    <?php
                                    // Determine if this permission is editable for Client role
                                    $is_client_editable = ($role->name == 'Client' && in_array($permission_key, $client_editable_permissions));
                                    $is_disabled = (!$role->permissions_editable && $role->name !== 'Client') ||
                                                   ($role->name == 'Client' && !$is_client_editable);
                                    ?>
                                    <div class="col-md-6 col-lg-4 mb-2">
                                        <div class="form-check <?php echo $is_client_editable ? 'client-editable' : ''; ?>">
                                            <input type="checkbox"
                                                   name="permissions[]"
                                                   value="<?php echo $permission_key; ?>"
                                                   id="perm_<?php echo $permission_key; ?>"
                                                   class="form-check-input permission-checkbox"
                                                   data-category="<?php echo $category; ?>"
                                                   <?php echo in_array($permission_key, $current_permissions) ? 'checked' : ''; ?>
                                                   <?php echo $is_disabled ? 'disabled' : ''; ?> />
                                            <label for="perm_<?php echo $permission_key; ?>" class="form-check-label <?php echo $is_disabled ? 'text-muted' : ''; ?>">
                                                <strong><?php echo $permission_data['label']; ?></strong>
                                                <?php if ($is_client_editable): ?>
                                                    <span class="badge bg-info ms-1"><?php _e('Configurable', 'cftp_admin'); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($permission_data['description'])): ?>
                                                    <br><small class="text-muted"><?php echo $permission_data['description']; ?></small>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="row mt-4">
                        <div class="col-12">
                            <?php if ($role->permissions_editable || $role->name == 'Client'): ?>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-check"></i> <?php _e('Update Permissions', 'cftp_admin'); ?>
                                </button>
                            <?php endif; ?>
                            <a href="roles.php" class="btn btn-secondary">
                                <?php echo (!$role->permissions_editable && $role->name !== 'Client') ? __('Back to Roles', 'cftp_admin') : __('Cancel', 'cftp_admin'); ?>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>



<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
?>