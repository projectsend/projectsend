<?php
/**
 * Show the form to reset the password.
 *
 * @package		ProjectSend
 *
 */
$allowed_levels = array(9,8,7,0);
require_once 'bootstrap.php';

$page_title = __('Lost password','cftp_admin');

$page_id = 'reset_password_enter_email';
if (!empty($_GET['token']) && !empty($_GET['user'])) {
    $page_id = 'reset_password_enter_new';
}

include_once ADMIN_VIEWS_DIR . DS . 'header-unlogged.php';
    $show_form = 'enter_email';

    if (!empty($_GET['token']) && !empty($_GET['user'])) {
        $got_token = $_GET['token'];
        $got_user = $_GET['user'];

        /**
         * Get the user's id
         */
        $user_data = get_user_by_username($got_user);
        $sql_request = $dbh->prepare("SELECT * FROM " . TABLE_PASSWORD_RESET . " WHERE BINARY token = :token AND user_id = :id");
        $sql_request->bindParam(':token', $got_token);
        $sql_request->bindParam(':id', $user_data['id'], PDO::PARAM_INT);
        $sql_request->execute();
        $count_request = $sql_request->rowCount();

        if ($count_request > 0) {
            $sql_request->setFetchMode(PDO::FETCH_ASSOC);
            $token_info = $sql_request->fetch();

            /** Check if the token has been used already */
            if ($token_info['used'] == '1') {
                $errorstate = 'token_used';
            }
            /** Check if the token has expired. */
            elseif (time() - strtotime($token_info['timestamp']) > PASSWORD_RECOVERY_TOKEN_EXPIRATION_TIME) {
                $errorstate = 'token_expired';
            }

            else {
                $show_form = 'enter_new_password';
            }
        }
        else {
            $errorstate = 'token_invalid';
            $show_form = 'none';
        }
    }

    /** Fix CVE-2020-28874 */
    if (!empty($errorstate)) {
        unset($user_data);
    }

    /** The form was submitted */
    if ($_POST) {
        /**
         * Clean the posted form values.
         */
        $form_type = encode_html($_POST['form_type']);
        
        switch ($form_type) {
            /**
             * The form submitted contains a new token request
             */
            case 'new_request':
                recaptcha2ValidateRequest();

                $get_user = get_user_by('user', 'email', $_POST['email']);
        
                if ( $get_user ) {
                    /** Email exists on the database */
                    $token = generateRandomString(32);
                    
                    /**
                     * Count how many request were made by this user today.
                     * No more than 3 unused should exist at a time.
                     */
                    $sql_amount = $dbh->prepare("SELECT * FROM " . TABLE_PASSWORD_RESET . " WHERE user_id = :id AND used = '0' AND timestamp > NOW() - INTERVAL 1 DAY");
                    $sql_amount->bindParam(':id', $get_user['id'], PDO::PARAM_INT);
                    $sql_amount->execute();
                    $count_requests = $sql_amount->rowCount();
                    if ($count_requests >= 3){
                        $errorstate = 'too_many_today';
                    }
                    else {
                        $sql_pass = $dbh->prepare("INSERT INTO " . TABLE_PASSWORD_RESET . " (user_id, token)"
                                                        ."VALUES (:id, :token)");
                        $sql_pass->bindParam(':token', $token);
                        $sql_pass->bindParam(':id', $get_user['id'], PDO::PARAM_INT);
                        $sql_pass->execute();
            
                        /** Send email */
                        $notify_user = new \ProjectSend\Classes\Emails;
                        if ($notify_user->send([
                            'type' => 'password_reset',
                            'address' => $get_user['email'],
                            'username' => $get_user['username'],
                            'token' => $token
                        ])) {
                            $state['email'] = 1;
                        }
                        else {
                            $state['email'] = 0;
                        }
                    }
                    
                    $show_form = 'none';
                }
                else {
                    //$errorstate = 'email_not_found';
                    // Simulate that the request has been set, do not show that email exists or not on the database
                    $state['email'] = 1;
                    $show_form = 'none';
                }
            break;

            /**
             * The form submitted contains the new password
             */
            case 'new_password':
                if (!empty($user_data['id'])) {
                    $reset_password_new = $_POST['password'];
    
                    /** Password checks */
                    $validation = new \ProjectSend\Classes\Validation;
                    $validation->validate_items([
                        $_POST['password'] => [
                            'required' => ['error' => $json_strings['validation']['no_pass']],
                            'password' => ['error' => $json_strings['validation']['valid_pass'].' '.$json_strings['validation']['valid_chars']],
                            'password_rules' => ['error' => $json_strings['validation']['rules_pass']],
                            'length' => ['error' => $json_strings['validation']['length_pass'], 'min' => MIN_PASS_CHARS, 'max' => MAX_PASS_CHARS],
                        ],
                    ]);

                    if ($validation->passed()) {	
                        $enc_password = password_hash($_POST['password'], PASSWORD_DEFAULT, [ 'cost' => HASH_COST_LOG2 ]);
                
                        if (strlen($enc_password) >= 20) {
                
                            $state['hash'] = 1;
                
                            /** SQL queries */

                            $sql_query = $dbh->prepare("UPDATE " . TABLE_USERS . " SET 
                                                        password = :password
                                                        WHERE id = :id"
                                                );
                            $sql_query->bindParam(':password', $enc_password);
                            $sql_query->bindParam(':id', $user_data['id'], PDO::PARAM_INT);
                            $sql_query->execute();							
                    
                            if ($sql_query) {
                                $state['reset'] = 1;

                                $sql_query = $dbh->prepare("UPDATE " . TABLE_PASSWORD_RESET . " SET 
                                                            used = '1' 
                                                            WHERE id = :id"
                                                    );
                                $sql_query->bindParam(':id', $token_info['id'], PDO::PARAM_INT);
                                $sql_query->execute();							

                                $show_form = 'none';
                            }
                            else {
                                $state['reset'] = 0;
                            }
                        }
                        else {
                            $state['hash'] = 0;
                        }
                    }
                }
                
            break;
        }
    }
?>

<div class="row">
    <div class="col-xs-12 col-sm-12 col-lg-4 col-lg-offset-4">
        <div class="white-box">
            <div class="white-box-interior">
                <?php
                    /**
                     * If the form was submitted with errors, show them here.
                     */
                    if (!empty($validation)) {
                        echo $validation->list_errors();
                    }

                    /**
                     * Show status message
                     */
                    if (isset($errorstate)) {
                        switch ($errorstate) {
                            case 'email_not_found':
                                $login_err_message = __("The supplied email address does not correspond to any user.",'cftp_admin');
                                break;
                            case 'token_invalid':
                                $login_err_message = __("The request is not valid.",'cftp_admin');
                                break;
                            case 'token_expired':
                                $login_err_message = __("This request has expired. Please make a new one.",'cftp_admin');
                                break;
                            case 'token_used':
                                $login_err_message = __("This request has already been completed. Please make a new one.",'cftp_admin');
                                break;
                            case 'too_many_today':
                                $login_err_message = __("There are 3 unused requests done in less than 24 hs. Please wait until one expires (1 day since made) to make a new one.",'cftp_admin');
                                break;
                        }
        
                        echo system_message('danger',$login_err_message,'login_error');
                    }

                    /**
                     * Show the ok or error message for the email.
                     */
                    if (isset($state['email'])) {
                        switch ($state['email']) {
                            case 1:
                                $msg = __('An e-mail with further instructions has been sent. Please check your inbox to proceed.','cftp_admin');
                                echo system_message('success',$msg);
                            break;
                            case 0:
                                $msg = __("E-mail couldn't be sent.",'cftp_admin');
                                $msg .= ' ' . __("If the problem persists, please contact an administrator.",'cftp_admin');
                                echo system_message('danger',$msg);
                            break;
                        }
                    }

                    /**
                     * Show the ok or error message for the password reset.
                     */
                    if (isset($state['reset'])) {
                        switch ($state['reset']) {
                            case 1:
                                $msg = __('Your new password has been set. You can now log in using it.','cftp_admin');
                                echo system_message('success',$msg);
                            break;
                            case 0:
                                $msg = __("Your new password couldn't be set.",'cftp_admin');
                                $msg .= ' ' . __("If the problem persists, please contact an administrator.",'cftp_admin');
                                echo system_message('danger',$msg);
                            break;
                        }
                    }

                    switch ($show_form) {
                        case 'enter_email':
                        default:
                            include_once FORMS_DIR . DS . 'reset-password' . DS . 'enter-email.php';
                        break;
                        case 'enter_new_password':
                            include_once FORMS_DIR . DS . 'reset-password' . DS . 'enter-password.php';
                        break;
                        case 'none':
                        break;
                    }
                ?>

                <div class="login_form_links">
                    <p><a href="<?php echo BASE_URI; ?>" target="_self"><?php _e('Go back to the homepage.','cftp_admin'); ?></a></p>
                </div>
            </div>
        </div> <!-- container-custom -->
    </div>
</div>

<?php
    include_once ADMIN_VIEWS_DIR . DS . 'footer.php';