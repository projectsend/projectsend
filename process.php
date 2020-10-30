<?php
use ProjectSend\Classes\Session as Session;
/** Process an action */
$allowed_levels = array(9,8,7,0);
require_once 'bootstrap.php';

global $auth;

$_SESSION['last_call'] = time();
$public = ['login', 'social_login', 'login_ldap', 'logout'];
if (!empty($_GET['do']) && !in_array($_GET['do'], $public)) {
    check_for_session();
    can_see_content($allowed_levels);
}
switch ($_GET['do']) {
    case 'login':
        $login = $auth->authenticate($_POST['username'], $_POST['password']);
        $auth->setLanguage($_POST['language']);

        /** Using an external form */
        if ( !empty( $_GET['external'] ) && $_GET['external'] == '1' && empty( $_GET['ajax'] ) ) {
            /** Success */
            if ( $results['status'] == 'success' ) {
                header('Location: ' . $results['location']);
            } else {
                header('Location: ' . BASE_URI . '?error=invalid_credentials');
            }
            exit;
        }

        echo $login;
        break;
    case 'social_login':
        if (Session::has('SOCIAL_LOGIN_NETWORK')) {
            Session::remove('SOCIAL_LOGIN_NETWORK');
        }
        Session::add('SOCIAL_LOGIN_NETWORK', $_GET['provider']);
    
        $login = $auth->socialLogin($_GET['provider']);
        break;
    case 'login_ldap':
        $login = $auth->login_ldap($_POST['ldap_email'], $_POST['ldap_password']);
        $auth->setLanguage($_POST['language']);
        echo $login;
        break;
    case 'logout':
        $error = (!empty($_GET['logout_error_type'])) ? $_GET['logout_error_type'] : null;
        $auth->logout($error);
        break;
    case 'change_language':
        $auth->setLanguage(html_output($_GET['language']));
        header('Location: ' . BASE_URI . 'index.php');
        exit;
        break;
    case 'download':
        $download = new \ProjectSend\Classes\Download;
        $download->download($_GET['id']);
        break;
    case 'return_files_ids':
        $download = new \ProjectSend\Classes\Download;
        $download->returnFilesIds($_GET['files']);
        break;
    case 'download_zip':
        $download = new \ProjectSend\Classes\Download;
        $download->downloadZip($_GET['files']);
        break;
    default:
        header('Location:' . BASE_URI);
        break;
}

exit;