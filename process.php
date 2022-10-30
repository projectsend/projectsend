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

if (!isset($_GET['do'])) {
    exit_with_error_code(403);
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
    case 'dismiss_upgraded_notice':
        redirect_if_not_logged_in();
        redirect_if_role_not_allowed([9,8,7]);
        save_option('show_upgrade_success_message', 'false');
        ps_redirect(BASE_URI.'dashboard.php');
    case 'return_files_ids':
        redirect_if_not_logged_in();
        redirect_if_role_not_allowed($allowed_levels);
        $download = new Download;
        $download->returnFilesIds($_GET['files']);
        break;
    case 'download_zip':
        redirect_if_not_logged_in();
        redirect_if_role_not_allowed($allowed_levels);
        $download = new Download;
        $download->downloadZip($_GET['files']);
        break;
        case 'folder_create':
            redirect_if_not_logged_in();
            redirect_if_role_not_allowed([9,8,7,0]);
            header('Content-Type: application/json');
            $folder = new \ProjectSend\Classes\Folder();
            $folder->set([
                'name' => $_POST['folder_name'],
                'parent' => (!empty($_POST['folder_parent'])) ? (int)$_POST['folder_parent'] : null,
            ]);
            if ($folder->create()) {
                echo json_encode([
                    'status' => 'success',
                    'data' => $folder->getData(),
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                ]);
            }
            exit;
            break;
        case 'folder_move':
            if (!user_is_logged_in()) {
                die_with_error_code(500);
            }

            header('Content-Type: application/json');
            $folder = new \ProjectSend\Classes\Folder($_POST['folder_id']);
            $move = $folder->setNewParent(CURRENT_USER_ID, $_POST['new_parent_id']); 
            if ($move) {
                echo json_encode([
                    'status' => 'success',
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                ]);
                die_with_error_code(500);
            }
            exit;
            break;
        default:
        ps_redirect(BASE_URI);
        break;
}

exit;
