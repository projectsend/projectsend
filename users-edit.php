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
if (isset($_GET['id'])) {
    $user_id = $_GET['id'];
    $page_status = (user_exists_id($user_id)) ? 1 : 2;
}
else {
    /**
     * Return 0 if the id is not set.
     */
    $page_status = 0;
}

/**
 * Get the user information from the database to use on the form.
 */
if ($page_status === 1) {
    $edit_user->get($user_id);
    $user_arguments = $edit_user->getProperties();
}

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
        $page_status = 3;
    }
}

if ($_POST) {
    /**
     * If the user is not an admin, check if the id of the user
     * that's being edited is the same as the current logged in one.
     */
    if (CURRENT_USER_LEVEL != 9) {
        if ($user_id != CURRENT_USER_ID) {
            die();
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
    if ($edit_user->validate()) {
        $edit_response = $edit_user->edit();

        $location = BASE_URI . 'users-edit.php?id=' . $user_id . '&status=' . $edit_response['query'];
        header("Location: $location");
        exit;
    }
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
        <?php
            /**
             * Get the process state and show the corresponding ok or error message.
             */
            if (isset($_GET['status'])) {
                switch ($_GET['status']) {
                    case 1:
                        $msg = __('User edited correctly.','cftp_admin');
                        if (isset($_GET['is_new'])) {
                            $msg = __('User created successfuly.','cftp_admin');
                        }
                        echo system_message('success',$msg);
                    break;
                    case 0:
                        $msg = __('There was an error. Please try again.','cftp_admin');
                        echo system_message('danger',$msg);
                    break;
                }
            }

            /**
             * Email notification with account information after creating it
             */
            if (isset($_GET['notification'])) {
                switch ($_GET['notification']) {
                    case 2:
                        $msg = __('A welcome message was not sent to the new account owner.','cftp_admin');
                        echo system_message('info',$msg);
                    break;
                    case 1:
                        $msg = __('A welcome message with login information was sent to the new account owner.','cftp_admin');
                        echo system_message('success',$msg);
                    break;
                    case 0:
                        $msg = __("E-mail notification couldn't be sent.",'cftp_admin');
                        echo system_message('danger',$msg);
                    break;
                }
            }
    ?>
        
        <div class="white-box">
            <div class="white-box-interior">
                <?php
                    // If the form was submited with errors, show them here.
                    echo $edit_user->getValidationErrors();

                    $direct_access_error = __('This page is not intended to be accessed directly.','cftp_admin');
                    if ($page_status === 0) {
                        $msg = __('No user was selected.','cftp_admin');
                        echo system_message('danger',$msg);
                        echo '<p>'.$direct_access_error.'</p>';
                    }
                    else if ($page_status === 2) {
                        $msg = __('There is no user with that ID number.','cftp_admin');
                        echo system_message('danger',$msg);
                        echo '<p>'.$direct_access_error.'</p>';
                    }
                    else if ($page_status === 3) {
                        $msg = __("Your account type doesn't allow you to access this feature.",'cftp_admin');
                        echo system_message('danger',$msg);
                    }
                    else {
                        /**
                         * Include the form.
                         */
                        include_once FORMS_DIR . DS . 'users.php';
                    }
                ?>

            </div>		
        </div>
    </div>
</div>
<?php
    include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
