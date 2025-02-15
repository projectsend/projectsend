<?php

use ProjectSend\Classes\ActionsLog;
use ProjectSend\Classes\Users;

$allowed_levels = [9];

require_once 'bootstrap.php';

log_in_required($allowed_levels);

$active_nav = 'users';
$page_title = __('Add system user', 'cftp_admin');
$page_id = 'user_form';

$newUser = new Users();

include_once ADMIN_VIEWS_DIR . DS . 'header.php';

$user_arguments = [
    'active' => 1,
    'notify_account' => 1,
    'require_password_change' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_arguments = [
        'username' => filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING),
        'password' => $_POST['password'], // Handle securely later
        'name' => filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING),
        'email' => filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL),
        'role' => filter_input(INPUT_POST, 'level', FILTER_VALIDATE_INT),
        'max_file_size' => filter_input(INPUT_POST, 'max_file_size', FILTER_VALIDATE_INT),
        'notify_account' => isset($_POST["notify_account"]) ? 1 : 0,
        'active' => isset($_POST["active"]) ? 1 : 0,
        'require_password_change' => isset($_POST["require_password_change"]) ? true : false,
        'limit_upload_to' => filter_input(INPUT_POST, "limit_upload_to", FILTER_SANITIZE_STRING),
        'type' => 'new_user',
    ];

    $newUser->setType('new_user');
    $newUser->set($user_arguments);
    $create = $newUser->create();

    if (!empty($create['id'])) {
        $logger = new ActionsLog;
        $record = $logger->addEntry([
            'action' => 2,
            'owner_user' => CURRENT_USER_USERNAME,
            'owner_id' => CURRENT_USER_ID,
            'affected_account' => $newUser->id,
            'affected_account_name' => $newUser->name
        ]);

        $flash->success(__('User created successfully'));
        $redirectTo = BASE_URI . 'users-edit.php?id=' . $create['id'];
    } else {
        $flash->error($newUser->getValidationErrors());
        $redirectTo = BASE_URI . 'users-add.php';
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

    ps_redirect($redirectTo);
    exit; // Always exit after a redirect
}
?>

<div class="row">
    <div class="col-12 col-sm-12 col-lg-6">
        <div class="white-box">
            <div class="white-box-interior">
                <?php
                $user_form_type = 'new_user';
                include_once FORMS_DIR . DS . 'users.php';
                ?>
            </div>
        </div>
    </div>
</div>

<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
