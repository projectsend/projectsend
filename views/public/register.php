<?php
/**
 * Show the form to register a new account for yourself.
 */
$allowed_levels = array(9, 8, 7, 0);

$page_title = __('Register new account', 'cftp_admin');

$page_id = 'client_form';

$new_client = new \ProjectSend\Classes\Users();

include_once VIEWS_PARTS_DIR.DS.'header-unlogged.php';

global $auth;
$flash = get_container_item('flash');

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
        'max_file_size' => 0,
        'notify_upload' => (isset($_POST["notify_upload"])) ? 1 : 0,
        'notify_account' => (isset($_POST["notify_account"])) ? 1 : 0,
        'active' => (get_option('clients_auto_approve') == 0) ? 0 : 1,
        'can_upload_public' => (get_option('clients_new_default_can_set_public') == 1) ? 1 : 0,
        'account_requested'    => (get_option('clients_auto_approve') == 0) ? 1 : 0,
        'type' => 'new_client',
        'recaptcha' => (recaptcha2_is_enabled()) ? recaptcha2_get_request() : null,
    ]);

    $create = $new_client->create();
    if (!empty($create['id'])) {
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
}
?>
<div class="row justify-content-md-center">
    <div class="col-12 col-sm-12 col-lg-4">
        <div class="white-box">
            <div class="white-box-interior">
                <?php
                if (!isset($_GET['success'])) {
                    // If the form was submitted with errors, show them here.
                    echo $new_client->getValidationErrors();

                    $clients_form_type = 'new_client_self';
                    include_once FORMS_DIR . DS . 'clients.php';
                }
                ?>

                <div class="login_form_links">
                    <p><a href="<?php echo BASE_URI; ?>" target="_self"><?php _e('Go back to the homepage.', 'cftp_admin'); ?></a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include_once VIEWS_PARTS_DIR.DS.'footer.php';
