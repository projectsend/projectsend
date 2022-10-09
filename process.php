<?php
use ProjectSend\Classes\Session as Session;
use ProjectSend\Classes\Download;
use ProjectSend\Classes\ActionsLog;

/** Process an action */
$allowed_levels = array(9, 8, 7, 0);
require_once 'bootstrap.php';

global $auth;
global $logger;

extend_session();

$public = ['login', 'social_login', 'login_ldap', 'logout', 'change_language'];
if (!empty($_GET['do']) && !in_array($_GET['do'], $public)) {
    redirect_if_not_logged_in();
    redirect_if_role_not_allowed($allowed_levels);
}
switch ($_GET['do']) {
    case 'social_login':
        if (Session::has('SOCIAL_LOGIN_NETWORK')) {
            Session::remove('SOCIAL_LOGIN_NETWORK');
        }
        Session::add('SOCIAL_LOGIN_NETWORK', $_GET['provider']);

        $login = $auth->socialLogin($_GET['provider']);
        break;
    case 'login_ldap':
        /*
        $login = $auth->loginLdap($_POST['ldap_email'], $_POST['ldap_password']);
        $auth->setLanguage($_POST['language']);
        echo $login;
        break;
        */
        exit;
    case 'logout':
        force_logout();
        break;
    case 'change_language':
        $auth->setLanguage(html_output($_GET['language']));
        $location = 'index.php';
        if (!empty($_GET['return_to']) && strpos($_GET['return_to'], BASE_URI) === 0) {
            $location = str_replace(BASE_URI, '', $_GET['return_to']);
        }
        ps_redirect(BASE_URI . $location);
        break;
    case 'get_preview':
        $return = [];
        if (!empty($_GET['file_id'])) {
            $file = new \ProjectSend\Classes\Files($_GET['file_id']);
            if ($file->existsOnDisk() && $file->embeddable) {
                $return = json_decode($file->getEmbedData());
            }
        }

        echo json_encode($return);
        exit;
        break;
    case 'download':
        $download = new Download;
        $download->download($_GET['id']);
        break;
    case 'return_files_ids':
        $download = new Download;
        $download->returnFilesIds($_GET['files']);
        break;
    case 'download_zip':
        $download = new Download;
        $download->downloadZip($_GET['files']);
        break;
    default:
        ps_redirect(BASE_URI);
        break;
}

exit;
