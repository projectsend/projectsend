<?php

use ProjectSend\Classes\ActionsLog;
use ProjectSend\Classes\Users;

$allowed_levels = [9, 8, 7, 0];

require_once 'bootstrap.php';

$page_title = __('Register new account', 'cftp_admin');
$page_id = 'client_form';

include_once ADMIN_VIEWS_DIR . DS . 'header-unlogged.php';

global $auth;
global $flash;

if (get_option('clients_can_register') != '1') {
    exit_with_error_code(403);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING) ?: null;
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING) ?: null;
    $contact = filter_input(INPUT_POST, 'contact', FILTER_SANITIZE_STRING) ?: null;
    $notify_upload = isset($_POST['notify_upload']) ? 1 : 0;
    $notify_account = isset($_POST['notify_account']) ? 1 : 0;
    $groups_request = isset($_POST['groups_request']) && is_array($_POST['groups_request']) ? array_map('intval', $_POST['groups_request']) : null;

    $new_client = new Users();
    $new_client->setType('new_client');

    $data = [
        'username' => $username,
        'password' => $password,
        'name' => $name,
        'email' => $email,
        'address' => $address,
        'phone' => $phone,
        'contact' => $contact,
        'max_file_size' => 0,
        'notify_upload' => $notify_upload,
        'notify_account' => $notify_account,
        'active' => (get_option('clients_auto_approve') == 0) ? 0 : 1,
        'can_upload_public' => (get_option('clients_new_default_can_set_public') == 1) ? 1 : 0,
        'account_requested' => (get_option('clients_auto_approve') == 0) ? 1 : 0,
        'type' => 'new_client',
        'recaptcha' => (recaptcha2_is_enabled()) ? recaptcha2_get_request() : null,
    ];

    $new_client->set($data);

    $create = $new_client->create();

    if (!empty($create['id'])) {
        $new_client->triggerAfterSelfRegister([
            'groups' => $groups_request,
        ]);

        $logger = new ActionsLog();
        $record = $logger->addEntry([
            'action' => 4,
            'owner_user' => $new_client->username,
            'owner_id' => $new_client->id,
            'affected_account' => $new_client->id,
            'affected_account_name' => $new_client->name,
        ]);

        $redirect_to = BASE_URI . 'register.php?success=1';

        if (get_option('clients_auto_approve') != 1) {
            $flash->success(__('Account created successfully', 'cftp_admin'));
            $flash->warning(__('Please remember that an administrator needs to approve your account before you can log in.', 'cftp_admin'));
        } else {
            $auth->authenticate($username, $password);
            $flash->success(__('Thank you for registering. Your account has been activated.', 'cftp_admin'));
            $redirect_to = 'my_files/index.php';
        }
    } else {
        $flash->error(__('There was an error saving to the database'));
        $redirect_to = BASE_URI . 'register.php';
    }

    if (isset($create['email'])) {
        switch ($create['email']) {
            case 1:
                $flash->success(__('An e-mail notification with login information was sent to the specified address.', 'cftp_admin'));
                break;
            case 0:
                $flash->error(__("E-mail notification couldn't be sent.", 'cftp_admin'));
                break;
        }
    }

    ps_redirect($redirect_to);
    exit;
}
?>

<div class="row justify-content-md-center">
    <div class="col-12 col-sm-12 col-lg-4">
        <div class="white-box">
            <div class="white-box-interior">
                <?php
                if (!isset($_GET['success'])) {
                    echo $new_client->getValidationErrors();

                    $clients_form_type = 'new_client_self';
                    include_once FORMS_DIR . DS . 'clients.php';
                }
                ?>

                <?php login_form_links(['homepage']); ?>
            </div>
        </div>
    </div>
</div>

<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
?>
