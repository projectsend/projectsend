<?php
/**
 * Show the form to reset the password.
 *
 * @package		ProjectSend
 *
 */
$allowed_levels = array(9, 8, 7, 0);
require_once 'bootstrap.php';

$page_title = __('Lost password', 'cftp_admin');

$page_id = (!empty($_GET['token']) && !empty($_GET['user'])) ? 'reset_password_enter_new' : 'reset_password_enter_email';

include_once ADMIN_VIEWS_DIR . DS . 'header-unlogged.php';

$pass_reset = new \ProjectSend\Classes\PasswordReset();

// Process request
if ($_POST) {
    $form_type = encode_html($_POST['form_type']);
    
    switch ($form_type) {
        case 'new_request':
            recaptcha2_validate_request();
            
            $get_user = get_user_by('user', 'email', $_POST['email']);
            if ($get_user) {
                $request = $pass_reset->requestNew($get_user['id']);
                if ($request['status'] == 'success') {
                    $flash->success($request['message']);
                } else {
                    $flash->error($request['message']);
                }
            } else {
                // Simulate that the request has been set, do not show that email exists or not on the database
                $flash->success($pass_reset->getNewRequestSuccessMessage());
            }

            ps_redirect(BASE_URI . 'reset-password.php');
        break;
        case 'new_password':
            $get_user = get_user_by_username($_POST['user']);
            if (!empty($get_user['id'])) {
                $pass_reset->getByTokenAndUserId($_POST['token'], $get_user['id']);
                $set = $pass_reset->processRequest($_POST['password']);
                if ($set['status'] == 'success') {
                    $flash->success($set['message']);
                    ps_redirect(BASE_URI);
                } else {
                    $flash->error($set['message']);
                    ps_redirect(BASE_URI . 'reset-password.php');
                }
            }

            exit_with_error_code(403);
        break;
    }
} else {
    if (!empty($_GET['token']) && !empty($_GET['user'])) {
        $get_user = get_user_by_username($_GET['user']);
    
        $pass_reset->getByTokenAndUserId($_GET['token'], $get_user['id']);
        $validate = $pass_reset->validate();
        if ($validate['status'] == 'error') {
            $flash->error($validate['message']);
            ps_redirect(BASE_URI . 'reset-password.php');
        }
    }
}
?>

<div class="row">
    <div class="col-xs-12 col-sm-12 col-lg-4 col-lg-offset-4">
        <div class="white-box">
            <div class="white-box-interior">
                <?php
                    switch ($page_id) {
                        case 'reset_password_enter_email':
                        default:
                            include_once FORMS_DIR . DS . 'reset-password' . DS . 'enter-email.php';
                            break;
                        case 'reset_password_enter_new':
                            include_once FORMS_DIR . DS . 'reset-password' . DS . 'enter-password.php';
                            break;
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
    include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
