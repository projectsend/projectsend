<?php
/**
 * Show the form to edit a system user.
 *
 * @package		ProjectSend
 * @subpackage	Users
 *
 */
$allowed_levels = array(9,8,7);
require_once 'bootstrap.php';

$active_nav = 'users';

/** Create the object */
$edit_user = new \ProjectSend\Classes\Users();

/** Check if the id parameter is on the URI. */
if (!isset($_GET['id'])) {
    exit_with_error_code(403);
}

$user_id = $_GET['id'];
if (!user_exists_id($user_id)) {
    exit_with_error_code(403);
}

/**
 * Get the user information from the database to use on the form.
 */
$edit_user->get($user_id);
$user_arguments = $edit_user->getProperties();

/**
 * Form type
 */
if (CURRENT_USER_LEVEL == 7) {
    $user_form_type = 'edit_user_self';
    $ignore_size = true;
}
else {
    if (CURRENT_USER_USERNAME == $user_arguments['username']) {
        $user_form_type = 'edit_user_self';
        $ignore_size = true;
    }
    else {
        $user_form_type = 'edit_user';
        $ignore_size = false;
    }
}

/**
 * Compare the user editing this account to the on the db.
 */
if (CURRENT_USER_LEVEL != 9) {
    if (CURRENT_USER_USERNAME != $user_arguments['username']) {
        exit_with_error_code(403);
    }
}

if ($_POST) {
    /**
     * If the user is not an admin, check if the id of the user
     * that's being edited is the same as the current logged in one.
     */
    if (CURRENT_USER_LEVEL != 9) {
        if ($user_id != CURRENT_USER_ID) {
            exit_with_error_code(403);
        }
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
        'role' => $user_arguments['role'],
        'max_file_size' => $user_arguments['max_file_size'],
        'active' => $user_arguments['active'],
        'type' => 'edit_user',
    );

    if ( $ignore_size == false ) {
        $user_arguments['max_file_size'] = (isset($_POST["max_file_size"])) ? $_POST["max_file_size"] : '';
    }

    /**
     * If the password field send an empty value to prevent notices.
     */
    $user_arguments['password'] = (isset($_POST['password'])) ? $_POST['password'] : '';

    /**
     * Edit level only when user is not Uploader (level 7) or when
     * editing other's account (not own).
     */	
    $can_edit_level_and_active = true;
    if (CURRENT_USER_LEVEL == 7) {
        $can_edit_level_and_active = false;
    }
    else {
        if (CURRENT_USER_USERNAME == $user_arguments['username']) {
            $can_edit_level_and_active = false;
        }
    }

    if ($can_edit_level_and_active === true) {
        $user_arguments['role'] = (isset($_POST['level'])) ? $_POST['level'] : $user_arguments['role'];
        $user_arguments['active'] = (isset($_POST["active"])) ? 1 : 0;
    }

    /** Validate the information from the posted form. */
    $edit_user->set($user_arguments);
    $edit_user->setType("existing_user");
    $edit_response = $edit_user->edit();

    if ($edit_response['query'] == 1) {
        $flash->success(__('User saved successfully'));
    } else {
        $flash->error(__('There was an error saving to the database'));
    }

    ps_redirect(BASE_URI . 'users-edit.php?id=' . $user_id);
}

$page_title = __('Edit system user','cftp_admin');

$page_id = 'user_form';

if (CURRENT_USER_USERNAME == $user_arguments['username']) {
    $page_title = __('My account','cftp_admin');
}

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>
<div class="row">
    <div class="col-xs-12 col-sm-12 col-lg-6">
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
