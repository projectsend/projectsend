<?php
/**
 * Show the form to register a new account for yourself.
 *
 * @package		ProjectSend
 * @subpackage	Clients
 *
 */
$allowed_levels = array(9,8,7,0);
require_once 'bootstrap.php';

$page_title = __('Register new account','cftp_admin');

$page_id = 'client_form';

$new_client = new \ProjectSend\Classes\Users();

include_once ADMIN_VIEWS_DIR . DS . 'header-unlogged.php';

    /** The form was submitted */
    if ($_POST) {
        if ( defined('RECAPTCHA_AVAILABLE') ) {
            $recaptcha_user_ip		= $_SERVER["REMOTE_ADDR"];
            $recaptcha_response		= $_POST['g-recaptcha-response'];
            $recaptcha_secret_key	= get_option('recaptcha_secret_key');
            $recaptcha_request		= file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_secret_key}&response={$recaptcha_response}&remoteip={$recaptcha_user_ip}");
        }

        /** Validate the information from the posted form. */
        /** Create the user if validation is correct. */
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
            'account_requested'	=> (get_option('clients_auto_approve') == 0) ? 1 : 0,
            'type' => 'new_client',
            'recaptcha' => ( defined('RECAPTCHA_AVAILABLE') ) ? $recaptcha_request : null,
        ]);
        if ($new_client->validate()) {
            $new_response = $new_client->create();
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

            if (get_option('clients_auto_approve') == 1) {
                global $auth;
                global $flash;
                $auth->authenticate($_POST['username'], $_POST['password']);
                $flash->success(__('Thank you for registering. Your account has been activated.', 'cftp_admin'));
                $redirect_url = 'my_files/index.php';
            } else {
                $redirect_url = BASE_URI.'register.php?success=1';
            }

            // Redirect
            header("Location:".$redirect_url);
            exit;
        }
    }
?>

<div class="col-xs-12 col-sm-12 col-lg-4 col-lg-offset-4">

    <div class="row">
        <div class="col-xs-12 branding_unlogged">
            <?php echo get_branding_layout(); ?>
        </div>
    </div>

    <div class="white-box">
        <div class="white-box-interior">

            <?php
                $form = true;
                if (isset($_GET['success']) && $_GET['success'] == '1') {
                    $msg = __('Account added correctly.','cftp_admin');
                    echo system_message('success',$msg);

                    if (get_option('clients_auto_approve') == 0) {
                        $msg = __('Please remember that an administrator needs to approve your account before you can log in.','cftp_admin');
                        $type = 'warning';
                    }
                    else {
                        $msg = __('You may now log in with your new credentials.','cftp_admin');
                        $type = 'success';
                    }
                    echo system_message($type,$msg);
                    $form = false;
                }

                if (get_option('clients_can_register') == '0') {
                    $msg = __('Client self registration is not allowed. If you need an account, please contact a system administrator.','cftp_admin');
                    echo system_message('danger',$msg);
                    $form = false;
                }
                else {
                    // If the form was submited with errors, show them here.
                    echo $new_client->getValidationErrors();
        
                    if (isset($new_response)) {
                        /**
                         * Get the process state and show the corresponding ok or error messages.
                         */
    
                        $error_msg = '</p><br /><p>';
                        $error_msg .= __('Please contact a system administrator.','cftp_admin');
    
                        switch ($new_response['query']) {
                            case 0:
                                $msg = __('There was an error. Please try again.','cftp_admin');
                                $msg .= $error_msg;
                                echo system_message('danger',$msg);
                            break;
                            case 2:
                                $msg = __('A folder for this account could not be created. Probably because of a server configuration.','cftp_admin');
                                $msg .= $error_msg;
                                echo system_message('danger',$msg);
                            break;
                            case 3:
                                $msg = __('The account could not be created. A folder with this name already exists.','cftp_admin');
                                $msg .= $error_msg;
                                echo system_message('danger',$msg);
                            break;
                        }
                        /**
                         * Show the ok or error message for the email notification.
                         */
                        switch ($new_response['email']) {
                            case 1:
                                $msg = __('An e-mail notification with login information was sent to the specified address.','cftp_admin');
                                echo system_message('success',$msg);
                            break;
                            case 0:
                                $msg = __("E-mail notification couldn't be sent.",'cftp_admin');
                                echo system_message('danger',$msg);
                            break;
                        }
                    }
                }

                if ($form == true) {
                    /**
                     * If not $new_response is set, it means we are just entering for the first time.
                     * Include the form.
                     */
                    $clients_form_type = 'new_client_self';
                    include_once FORMS_DIR . DS . 'clients.php';
                }
            ?>

            <div class="login_form_links">
                <p><a href="<?php echo BASE_URI; ?>" target="_self"><?php _e('Go back to the homepage.','cftp_admin'); ?></a></p>
            </div>
        </div>
    </div> <!-- main -->

<?php
    include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
