<?php
/**
 * ProjectSend (previously cFTP) is a free, clients-oriented, private file
 * sharing web application.
 * Clients are created and assigned a username and a password. Then you can
 * upload as much files as you want under each account, and optionally add
 * a name and description to them. 
 *
 * ProjectSend is hosted on Google Code.
 * Feel free to participate!
 *
 * @link		https://github.com/projectsend/projectsend
 * @license		http://www.gnu.org/licenses/gpl-2.0.html GNU GPL version 2
 * @package		ProjectSend
 *
 */
$allowed_levels = array(9,8,7,0);
require_once 'bootstrap.php';

global $dbh;
global $auth;
global $flash;
global $bfchecker;

$page_title = __('Log in','cftp_admin');

$body_class = array('login');
$page_id = 'login';

$current_ip = get_client_ip();

$bfstatus = $bfchecker->getLoginStatus(get_client_ip());
switch ($bfstatus['status']) {
    case 'error_403':
        $flash->clear(); // @todo hack, since flash messages after the last error should not be retained
        exit_with_error_code(403);
    break;
}

if ($_POST) {
    switch ($_POST['do']) {
        default:
            exit_with_error_code(403);
        break;
        case 'login':
            recaptcha2_validate_request();

            $login = json_decode($auth->authenticate($_POST['username'], $_POST['password']));
            if ($login->status == 'success') {
                $user = new \ProjectSend\Classes\Users;
                $user->get($login->user_id);

                ps_redirect($login->location);
            } else {
                $flash->error($auth->getError());

                switch ($bfstatus['status']) {
                    case 'delay':
                        if (is_numeric($bfstatus['message'])) {
                            $flash->error('<div id="message_countdown">'.sprintf(__('Please wait %s seconds before attempting to log in again.', 'cftp_admin'), '<span class="seconds_countdown">'.$bfstatus['message'].'</span>').'</div>');
                            if ($bfstatus['message'] > 150) {
                                $flash->error(sprintf(__('Warning: You are about to reach the failed attempts limit, which will completely block your access for a few minutes.', 'cftp_admin'), $bfstatus['message']));
                            }
                        }
                    break;
                }

                ps_redirect(BASE_URI);
            }
            // $auth->setLanguage($_POST['language']);
        break;
        case '2fa_verify':
            recaptcha2_validate_request();
            $code = $_POST['n1'].$_POST['n2'].$_POST['n3'].$_POST['n4'].$_POST['n5'].$_POST['n6'];

            $login = json_decode($auth->validate2faRequest($_POST['token'], (int)$code));
            if ($login->status == 'success') {
                $user = new \ProjectSend\Classes\Users;
                $user->get($login->user_id);
                ps_redirect($login->location);
            } else {
                $flash->error($auth->getError());
                ps_redirect(BASE_URI."index.php?form=2fa_verify&token=".$_POST['token']);
            }
        break;
        case '2fa_request_another':
            recaptcha2_validate_request();

            $auth_code = new \ProjectSend\Classes\AuthenticationCode();
            if (!$auth_code->getByToken($_POST['token'])) {
                exit_with_error_code(403);
            }
            $props = $auth_code->getProperties();

            if ($auth_code->canRequestNewCode($props['user_id'])) {
                $request = json_decode($auth_code->requestNewCode($props['user_id']));
                if ($request->status == 'success') {
                    ps_redirect(BASE_URI."index.php?form=2fa_verify&token=".$request->token);
                }
                ps_redirect(BASE_URI);
            }
        break;
    }
}

$csrf_token = getCsrfToken();

$login_types = array(
    'local' => '1',
    'ldap' => get_option('ldap_signin_enabled'),
);

$valid_forms = ['login', '2fa_verify'];
$form = (isset($_GET['form']) && in_array($_GET['form'], $valid_forms)) ? $_GET['form'] : 'login';

if ($form == '2fa_verify') {
    $request = new \ProjectSend\Classes\AuthenticationCode();
    $get_request = $request->getByToken($_GET['token']);
    if ($get_request == false) {
        exit_with_error_code(403);
    }

    $props = $request->getProperties();
    $user = get_user_by_id($props['user_id']);
    $masked_email = mask_email($user['email']);
}

include_once ADMIN_VIEWS_DIR . DS . 'header-unlogged.php';
?>
<div class="row">
    <div class="col-xs-12 col-sm-12 col-lg-4 col-lg-offset-4">
        <div class="white-box">
            <div class="white-box-interior">
                <div class="ajax_response">
                </div>
            
                <?php /*
                <ul class="nav nav-tabs" role="tablist">
                    <li role="presentation" class="active"><a href="#local" aria-controls="local" role="tab" data-toggle="tab">Local account</a></li>
                    <?php if ($login_types['ldap'] == 'true') { ?>
                        <li role="presentation"><a href="#ldap" aria-controls="ldap" role="tab" data-toggle="tab">LDAP</a></li>
                    <?php } ?>
                </ul> */ ?>
                <div class="tab-content">
                    <div role="tabpanel" class="tab-pane fade in active" id="local">
                        <?php
                            include_once FORMS_DIR . DS . $form . '.php';
                        ?>
                    </div>

                    <?php /* if ($login_types['ldap'] == 'true') { ?>
                        <div role="tabpanel" class="tab-pane fade" id="ldap">
                            <?php include_once FORMS_DIR . DS . 'login-ldap.php'; ?>
                        </div>
                    <?php } */ ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
