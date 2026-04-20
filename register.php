<?php
/**
 * Show the form to register a new account for yourself.
 */
require_once 'bootstrap.php';

$page_title = __('Register new account', 'cftp_admin');

$page_id = 'client_form';

$new_client = new \ProjectSend\Classes\Users();

include_once ADMIN_VIEWS_DIR . DS . 'header-unlogged.php';

global $auth;
global $flash;

if (get_option('clients_can_register') != '1') {
    exit_with_error_code(403);
}

/** The form was submitted */
if ($_POST) {
    $new_client->setType('new_client');
    $new_client->set([
        'username' => $_POST['username'],
        'password' => $_POST['password'],
        'name' => $_POST['name'],
        'email' => $_POST['email'],
        'address' => (isset($_POST["address"])) ? $_POST['address'] : null,
        'phone' => (isset($_POST["phone"])) ? $_POST['phone'] : null,
        'contact' => (isset($_POST["contact"])) ? $_POST['contact'] : null,
        'role_id' => \ProjectSend\Classes\Roles::getClientRoleId(), // Set client role for self-registration
        'max_file_size' => 0,
        'notify_upload' => (isset($_POST["notify_upload"])) ? 1 : 0,
        'notify_account' => (isset($_POST["notify_account"])) ? 1 : 0,
        'active' => (get_option('clients_auto_approve') == 0) ? 0 : 1,
        'can_upload_public' => (get_option('clients_new_default_can_set_public') == 1) ? 1 : 0,
        'account_requested'    => (get_option('clients_auto_approve') == 0) ? 1 : 0,
        'type' => 'new_client',
        'captcha' => captcha_maybe_get_request(),
        'recaptcha' => (recaptcha2_is_enabled()) ? recaptcha2_get_request() : null,
    ]);

    // Process custom fields for visible fields only
    $custom_field_data = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'custom_field_') === 0) {
            $field_id = str_replace('custom_field_', '', $key);
            $custom_field_data[$field_id] = $value;
        }
    }
    $new_client->custom_field_data = $custom_field_data;

    $create = $new_client->create();
    if ($create['status'] === 'success') {
        $new_client->triggerAfterSelfRegister([
            'groups' => (isset($_POST["groups_request"])) ? $_POST["groups_request"] : null,
        ]);

        /** Record the action log */
        $logger = new \ProjectSend\Classes\ActionsLog;
        $record = $logger->addEntry([
            'action' => 4,
            'owner_user' => $new_client->username,
            'owner_id' => $new_client->id,
            'affected_account' => $new_client->id,
            'affected_account_name' => $new_client->name
        ]);

        $redirect_to = BASE_URI . 'register.php?success=1';

        if (get_option('clients_auto_approve') != 1) {
            $flash->success(__('Account created successfully', 'cftp_admin'));
            $flash->warning(__('Please remember that an administrator needs to approve your account before you can log in.', 'cftp_admin'));
        } else {
            // Auto approve accounts: redirect to files list
            $auth->authenticate($_POST['username'], $_POST['password']);
            $flash->success(__('Thank you for registering. Your account has been activated.', 'cftp_admin'));
            $redirect_to = 'my_files/index.php';
        }
    } else {
        // Store validation errors in session if they exist
        if (!empty($create['errors'])) {
            $_SESSION['registration_errors'] = $create['errors'];
        } else {
            // Use the generic message if no specific errors provided
            $flash->error($create['message']);
        }
        // Store form data in session to preserve it after redirect (except password)
        $_SESSION['registration_form_data'] = [
            'username' => $_POST['username'],
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'address' => (isset($_POST["address"])) ? $_POST['address'] : '',
            'phone' => (isset($_POST["phone"])) ? $_POST['phone'] : '',
            'contact' => (isset($_POST["contact"])) ? $_POST['contact'] : '',
            'notify_upload' => (isset($_POST["notify_upload"])) ? 1 : 0,
            'notify_account' => (isset($_POST["notify_account"])) ? 1 : 0,
            'groups_request' => (isset($_POST["groups_request"])) ? $_POST["groups_request"] : null,
        ];
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
}
?>
<div class="row justify-content-md-center">
    <div class="col-12 col-sm-12 col-lg-4">
        <div class="white-box">
            <div class="white-box-interior">
                <?php
                if (!isset($_GET['success'])) {
                    // Display validation errors if they exist
                    if (isset($_SESSION['registration_errors'])) {
                        echo $_SESSION['registration_errors'];
                        unset($_SESSION['registration_errors']);
                    } else {
                        // Show any errors from the current object (for backward compatibility)
                        echo $new_client->getValidationErrors();
                    }

                    // Retrieve form data from session if available (after failed submission)
                    if (isset($_SESSION['registration_form_data'])) {
                        $client_arguments = $_SESSION['registration_form_data'];
                        // Set selected groups for the form
                        if (isset($client_arguments['groups_request'])) {
                            $selected_groups = $client_arguments['groups_request'];
                        }
                        // Clear the session data after using it
                        unset($_SESSION['registration_form_data']);
                    } else {
                        $client_arguments = [];
                    }

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
