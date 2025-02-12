<?php

use ProjectSend\Classes\Users;

/**
 * Show the form to edit a system user.
 */

// Configuration: Allowed user levels.
$allowed_levels = [9, 8, 7];

require_once 'bootstrap.php';

// Security: Verify user authentication.
log_in_required($allowed_levels);

// Template: Active navigation item.
$active_nav = 'users';

// Security: Validate GET parameter and user existence.
$user_id = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : null;

if (!$user_id || !user_exists_id($user_id)) {
    exit_with_error_code(404);
}

// Create the user object.
$edit_user = new Users($user_id);
$user_arguments = $edit_user->getProperties();

// Determine form type based on user level and identity.
if (CURRENT_USER_LEVEL == 7 || CURRENT_USER_USERNAME == $user_arguments['username']) {
    $user_form_type = 'edit_user_self';
    $ignore_size = true;
} else {
    $user_form_type = 'edit_user';
    $ignore_size = false;
}

// Security: Prevent unauthorized access.
if (CURRENT_USER_LEVEL != 9 && CURRENT_USER_USERNAME != $user_arguments['username']) {
    exit_with_error_code(403);
}

// Handle form submission.
if ($_POST) {
    // Security: Validate CSRF token.
    if (!validateCsrfToken()) {
        $flash->error(__('Invalid CSRF token.', 'cftp_admin'));
        ps_redirect(BASE_URI . 'users-edit.php?id=' . $user_id);
        exit;
    }

    // Security: Prevent unauthorized edits via POST.
    if (CURRENT_USER_LEVEL != 9 && $user_id != CURRENT_USER_ID) {
        exit_with_error_code(403);
    }

    // Sanitize and retrieve POST data.
    $name = isset($_POST['name']) ? sanitizeString($_POST['name']) : '';
    $email = isset($_POST['email']) ? sanitizeEmail($_POST['email']) : '';
    $password = $_POST['password'] ?? ''; // Allow empty password

    if ($ignore_size == false) {
        $max_file_size = isset($_POST["max_file_size"]) ? sanitizeString($_POST["max_file_size"]) : '';
    } else {
        $max_file_size = $user_arguments['max_file_size'];
    }

    $limit_upload_to = $_POST["limit_upload_to"] ?? null;

    // Determine role and active status based on user level.
    $role = $user_arguments['role'];
    $active = $user_arguments['active'];

    $can_edit_level_and_active = !(CURRENT_USER_LEVEL == 7 || CURRENT_USER_USERNAME == $user_arguments['username']);

    if ($can_edit_level_and_active === true) {
        $role = isset($_POST['level']) ? sanitizeString($_POST['level']) : $user_arguments['role'];
        $active = isset($_POST["active"]) ? 1 : 0;
    }

    // Prepare user arguments for update.
    $user_arguments = [
        'id' => $user_arguments['id'],
        'username' => $user_arguments['username'],
        'name' => $name,
        'email' => $email,
        'role' => $role,
        'max_file_size' => $max_file_size,
        'active' => $active,
        'type' => 'edit_user',
        'password' => $password,
        'limit_upload_to' => $limit_upload_to,
    ];

    try {
        $edit_user->set($user_arguments);
        $edit_user->setType("existing_user");
        $edit_response = $edit_user->edit();

        if ($edit_response['query'] == 1) {
            $flash->success(__('User saved successfully', 'cftp_admin'));
        } else {
            $flash->error(__('There was an error saving to the database', 'cftp_admin'));
        }

    } catch (Exception $e) {
        $flash->error(__('An error occurred: ', 'cftp_admin') . htmlspecialchars($e->getMessage()));
    }

    ps_redirect(BASE_URI . 'users-edit.php?id=' . $user_id);
    exit;
}

$page_title = __('Edit system user', 'cftp_admin');
if (CURRENT_USER_USERNAME == $user_arguments['username']) {
    $page_title = __('My account', 'cftp_admin');
}

$page_id = 'user_form';

// Include layout files
include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>
<div class="row">
    <div class="col-12 col-sm-12 col-lg-6">
        <div class="white-box">
            <div class="white-box-interior">
                <?php
                // Display validation errors, if any.
                echo $edit_user->getValidationErrors();

                // Include the user form.
                include_once FORMS_DIR . DS . 'users.php';
                ?>
            </div>
        </div>
    </div>
</div>
<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';

/**
 * Sanitizes a string input.
 **/
function sanitizeString(string $string): string
{
    $string = trim($string);
    $string = stripslashes($string);
    $string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');

    return $string;
}

/**
 * Sanitizes an email input.
 * */
function sanitizeEmail(string $email): string
{
    $email = trim($email);
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);

    return $email;
}
