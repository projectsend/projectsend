<?php
/**
 * Add a new user role
 * Only accessible to Super Administrators (level 9)
 */
require_once 'bootstrap.php';
check_access_enhanced(['edit_settings']);

// Check if custom roles are enabled
if (!custom_roles_enabled()) {
    $flash->error(__('Custom roles are disabled.', 'cftp_admin'));
    ps_redirect('roles.php');
}

$active_nav = 'roles';
$page_title = __('Add New Role', 'cftp_admin');
$page_id = 'roles_add';

// Process form submission
if ($_POST) {
    $validation_errors = [];

    // Validate required fields
    if (empty($_POST['name'])) {
        $validation_errors[] = __('Role name is required.', 'cftp_admin');
    }

    // Role level validation removed - roles use auto-generated IDs

    if (empty($validation_errors)) {
        $role_data = [
            'name' => $_POST['name'],
            'description' => $_POST['description'] ?? '',
            'is_system_role' => 0,
            'active' => isset($_POST['active']) ? 1 : 0
        ];

        $role = new \ProjectSend\Classes\Roles();
        $result = $role->create($role_data);

        if ($result) {
            $flash->success(__('Role created successfully. You can now configure permissions.', 'cftp_admin'));
            ps_redirect('role-permissions.php?role=' . $result);
        } else {
            $flash->error(__('Could not create role. Please try again.', 'cftp_admin'));
        }
    } else {
        foreach ($validation_errors as $error) {
            $flash->error($error);
        }
    }
}

// Role levels are no longer used - roles are created with auto-generated IDs

// Permissions are configured separately after role creation

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="row">
            <div class="col-xs-12 col-sm-12 col-lg-6">
            </div>
            <div class="col-xs-12 col-sm-12 col-lg-6 text-end">
                <a href="roles.php" class="btn btn-secondary">
                    <i class="fa fa-arrow-left"></i> <?php _e('Back to Roles', 'cftp_admin'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 col-lg-8">
        <div class="ps-card">
            <div class="ps-card-body">
                <h5><?php _e('Role Information', 'cftp_admin'); ?></h5>
                <form action="" method="post" class="form-horizontal" id="role_form">
                    <?php addCsrf(); ?>

                    <div class="form-group row">
                        <label for="name" class="col-sm-3 control-label"><?php _e('Role Name', 'cftp_admin'); ?></label>
                        <div class="col-sm-9">
                            <input type="text" name="name" id="name" class="form-control required"
                                   value="<?php echo isset($_POST['name']) ? html_output($_POST['name']) : ''; ?>"
                                   maxlength="255" required />
                            <small class="form-text text-muted"><?php _e('A descriptive name for this role (e.g., "Project Manager", "Content Editor")', 'cftp_admin'); ?></small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="description" class="col-sm-3 control-label"><?php _e('Description', 'cftp_admin'); ?></label>
                        <div class="col-sm-9">
                            <textarea name="description" id="description" class="form-control" rows="3"><?php echo isset($_POST['description']) ? html_output($_POST['description']) : ''; ?></textarea>
                            <small class="form-text text-muted"><?php _e('Optional description explaining what this role is for', 'cftp_admin'); ?></small>
                        </div>
                    </div>

                    <!-- Role level field removed - roles now use auto-generated IDs -->

                    <div class="form-group row">
                        <div class="col-sm-9 offset-sm-3">
                            <div class="form-check">
                                <input type="checkbox" name="active" id="active" class="form-check-input" value="1"
                                       <?php echo (!isset($_POST['active']) || $_POST['active']) ? 'checked' : ''; ?> />
                                <label for="active" class="form-check-label"><?php _e('Active', 'cftp_admin'); ?></label>
                                <small class="form-text text-muted"><?php _e('Inactive roles cannot be assigned to users', 'cftp_admin'); ?></small>
                            </div>
                        </div>
                    </div>


                    <div class="form-group row">
                        <div class="col-sm-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-check"></i> <?php _e('Create Role', 'cftp_admin'); ?>
                            </button>
                            <a href="roles.php" class="btn btn-secondary">
                                <?php _e('Cancel', 'cftp_admin'); ?>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="ps-card">
            <div class="ps-card-body">
                <h5><?php _e('Next Steps', 'cftp_admin'); ?></h5>
                <p class="text-muted"><?php _e('After creating the role, you will be redirected to configure its permissions.', 'cftp_admin'); ?></p>

                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i>
                    <?php _e('New roles are created without any permissions by default for security reasons.', 'cftp_admin'); ?>
                </div>
            </div>
        </div>

        <div class="ps-card mt-3">
            <div class="ps-card-body">
                <h5><?php _e('Role Guidelines', 'cftp_admin'); ?></h5>
                <ul class="list-unstyled">
                    <li><strong><?php _e('Naming:', 'cftp_admin'); ?></strong> <?php _e('Use descriptive names that indicate the role\'s purpose', 'cftp_admin'); ?></li>
                    <li><strong><?php _e('Description:', 'cftp_admin'); ?></strong> <?php _e('Optional but helpful for understanding the role\'s purpose', 'cftp_admin'); ?></li>
                    <li><strong><?php _e('Permissions:', 'cftp_admin'); ?></strong> <?php _e('Configure after role creation for better security', 'cftp_admin'); ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>


<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
?>