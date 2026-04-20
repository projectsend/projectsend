<?php
/**
 * Show the form to edit a system user.
 */


require_once 'bootstrap.php';
redirect_if_not_logged_in();

// Ensure user is properly logged in and constants are defined
if (!defined('CURRENT_USER_ID') || !CURRENT_USER_ID) {
    ps_redirect(BASE_URI . 'index.php');
    exit;
}

// Users can always edit their own account
// Otherwise need edit_users permission
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$can_edit = false;

if ($user_id == CURRENT_USER_ID) {
    // Editing own account
    $can_edit = current_user_can('edit_self_account');
} else {
    // Editing another user's account
    $can_edit = current_user_can('edit_users');
}

if (!$can_edit) {
    exit_with_error_code(403);
}

$active_nav = 'users';

// Check if the id parameter is on the URI.
if (!isset($_GET['id'])) {
    exit_with_error_code(403);
}

$user_id = (int)$_GET['id'];
if (!user_exists_id($user_id)) {
    exit_with_error_code(403);
}

// Create the object
$edit_user = new \ProjectSend\Classes\Users($user_id);
$user_arguments = $edit_user->getProperties();


// Form type
if (current_role_in(['Uploader'])) {
    $user_form_type = 'edit_user_self';
    $ignore_size = true;
} else {
    if (CURRENT_USER_USERNAME == $user_arguments['username']) {
        $user_form_type = 'edit_user_self';
        $ignore_size = true;
    } else {
        $user_form_type = 'edit_user';
        $ignore_size = false;
    }
}

// Additional permission check - using consistent permission system
$can_edit_this_user = false;
if ($user_arguments['id'] == CURRENT_USER_ID) {
    // Editing own account
    $can_edit_this_user = current_user_can('edit_self_account');
} else {
    // Editing another user's account
    $can_edit_this_user = current_user_can('edit_users');
}

if (!$can_edit_this_user) {
    exit_with_error_code(403);
}

if ($_POST) {

    /**
     * Check if user can edit this account using the same logic as the initial permission check
     */
    $can_edit_in_post = false;
    if ($user_id == CURRENT_USER_ID) {
        // Editing own account
        $can_edit_in_post = current_user_can('edit_self_account');
    } else {
        // Editing another user's account
        $can_edit_in_post = current_user_can('edit_users');
    }

    if (!$can_edit_in_post) {
        exit_with_error_code(403);
    }

    /**
     * Clean the posted form values to be used on the user actions,
     * and again on the form if validation failed.
     * Also, overwrites the values gotten from the database so if
     * validation failed, the new unsaved values are shown to avoid
     * having to type them again.
     */
    $user_arguments = array(
        'id' => $user_arguments['id'],
        'username' => $user_arguments['username'],
        'name' => $_POST['name'],
        'email' => $_POST['email'],
        'role_id' => $user_arguments['role_id'],
        'max_file_size' => $user_arguments['max_file_size'],
        'max_disk_quota' => $user_arguments['max_disk_quota'],
        'active' => $user_arguments['active'],
        'type' => 'edit_user',
        'limit_upload_to' => (isset($_POST["limit_upload_to"])) ? $_POST["limit_upload_to"] : null,
    );

    if ($ignore_size == false) {
        $user_arguments['max_file_size'] = (isset($_POST["max_file_size"])) ? $_POST["max_file_size"] : '';
        $user_arguments['max_disk_quota'] = (isset($_POST["max_disk_quota"])) ? $_POST["max_disk_quota"] : '';
    }

    // If the password field send an empty value to prevent notices.
    $user_arguments['password'] = (isset($_POST['password'])) ? $_POST['password'] : '';

    /**
     * Edit level only when user is not Uploader (level 7) or when
     * editing other's account (not own).
     */
    $can_edit_level_and_active = true;
    if (current_role_in(['Uploader'])) {
        $can_edit_level_and_active = false;
    } else {
        if (CURRENT_USER_USERNAME == $user_arguments['username']) {
            $can_edit_level_and_active = false;
        }
    }

    if ($can_edit_level_and_active === true) {
        $user_arguments['role_id'] = (isset($_POST['role_id'])) ? $_POST['role_id'] : $user_arguments['role_id'];
        $user_arguments['active'] = (isset($_POST["active"])) ? 1 : 0;
    }


    // Process custom fields
    $custom_field_data = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'custom_field_') === 0) {
            $field_id = str_replace('custom_field_', '', $key);
            $custom_field_data[$field_id] = $value;
        }
    }

    // Validate the information from the posted form.
    $edit_user->set($user_arguments);
    $edit_user->setType("existing_user");
    $edit_user->custom_field_data = $custom_field_data;
    $edit_response = $edit_user->edit();


    if ($edit_response['status'] === 'success') {
        $flash->success($edit_response['message']);
    } else {
        $flash->error($edit_response['message']);
    }

    // Ensure we're redirecting to the correct user ID from the original request
    $redirect_user_id = isset($_GET['id']) ? (int)$_GET['id'] : $user_id;
    ps_redirect(BASE_URI . 'users-edit.php?id=' . $redirect_user_id);
}

$page_title = __('Edit system user', 'cftp_admin');

$page_id = 'user_form';

if (CURRENT_USER_USERNAME == $user_arguments['username']) {
    $page_title = __('My account', 'cftp_admin');
}

// Preserve the user_id before header inclusion (header might overwrite it)
$edit_user_id = $user_id;

include_once ADMIN_VIEWS_DIR . DS . 'header.php';

// Restore the user_id after header inclusion
$user_id = $edit_user_id;
?>
<div class="row">
    <div class="col-12 col-sm-12 col-lg-6">
        <div class="white-box">
            <div class="white-box-interior">
                <?php
                // If the form was submitted with errors, show them here.
                echo $edit_user->getValidationErrors();

                include_once FORMS_DIR . DS . 'users.php';
                ?>
            </div>
        </div>
    </div>
</div>
<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
