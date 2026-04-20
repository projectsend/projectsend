<?php
/**
 * Show the form to add a new system user.
 */
require_once 'bootstrap.php';
check_access_enhanced(['create_users']);

$active_nav = 'users';

$page_title = __('Add system user', 'cftp_admin');

$page_id = 'user_form';

$new_user = new \ProjectSend\Classes\Users();

include_once ADMIN_VIEWS_DIR . DS . 'header.php';

// Set checkboxes as 1 to default them to checked when first entering the form
$user_arguments = array(
    'active' => 1,
    'notify_account' => 1,
    'require_password_change' => 1,
);

if ($_POST) {
    /**
     * Clean the posted form values to be used on the user actions,
     * and again on the form if validation failed.
     */
    $user_arguments = array(
        'username' => $_POST['username'],
        'password' => $_POST['password'],
        'name' => $_POST['name'],
        'email' => $_POST['email'],
        'role' => $_POST['role_id'],
        'max_file_size' => (isset($_POST["max_file_size"])) ? $_POST['max_file_size'] : '',
        'max_disk_quota' => (isset($_POST["max_disk_quota"])) ? $_POST['max_disk_quota'] : '',
        'notify_account' => (isset($_POST["notify_account"])) ? 1 : 0,
        'active' => (isset($_POST["active"])) ? 1 : 0,
        'require_password_change' => (isset($_POST["require_password_change"])) ? true : false,
        'limit_upload_to' => (isset($_POST["limit_upload_to"])) ? $_POST["limit_upload_to"] : null,
        'type' => 'new_user',
    );

    // Process custom fields
    $custom_field_data = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'custom_field_') === 0) {
            $field_id = str_replace('custom_field_', '', $key);
            $custom_field_data[$field_id] = $value;
        }
    }

    // Validate the information from the posted form
    // Create the user if validation is correct
    $new_user->setType('new_user');
    $new_user->set($user_arguments);
    $new_user->custom_field_data = $custom_field_data;
    $create = $new_user->create();

    if ($create['status'] === 'success') {
        $logger = new \ProjectSend\Classes\ActionsLog;
        $record = $logger->addEntry([
            'action' => 2,
            'owner_user' => CURRENT_USER_USERNAME,
            'owner_id' => CURRENT_USER_ID,
            'affected_account' => $new_user->id,
            'affected_account_name' => $new_user->name
        ]);

        $flash->success($create['message']);
        $redirect_to = BASE_URI . 'users-edit.php?id=' . $create['id'];
    } else {
        $flash->error($create['message']);
        $redirect_to = BASE_URI . 'users-add.php';
    }

    if (isset($create['email'])) {
        switch ($create['email']) {
            case 2:
                $flash->success(__('A welcome message was not sent to the new account owner.', 'cftp_admin'));
                break;
            case 1:
                $flash->success(__('A welcome message with login information was sent to the new account owner.', 'cftp_admin'));
                break;
            case 0:
                $flash->error(__("E-mail notification couldn't be sent.", 'cftp_admin'));
                break;
        }
    }

    ps_redirect($redirect_to);
}
?>
<div class="row">
    <div class="col-12 col-sm-12 col-lg-6">
        <div class="white-box">
            <div class="white-box-interior">
                <?php
                // If the form was submitted with errors, show them here.
                $user_form_type = 'new_user';
                include_once FORMS_DIR . DS . 'users.php';
                ?>
            </div>
        </div>
    </div>
</div>
<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
