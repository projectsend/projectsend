<?php
// Process ajax calls
$allowed_levels = [9, 8, 7, 0];
require_once '../bootstrap.php';

global $auth;
global $logger;

extend_session();

if (!user_is_logged_in()) {
    die_with_error_code(403);
}

if (!in_array(CURRENT_USER_LEVEL, $allowed_levels)) {
    exit_with_error_code(403);
}

if (!isset($_GET['do'])) {
    exit_with_error_code(403);
}

header('Content-Type: application/json');

switch ($_GET['do']) {
    case 'folder_create':
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

    case 'file_move':
        $file = new \ProjectSend\Classes\Files($_POST['file_id']);
        $move = $file->moveToFolder($_POST['new_parent_id']); 

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

    case 'folder_rename':
        $folder = new \ProjectSend\Classes\Folder($_POST['folder_id']);
        $rename = $folder->rename($_POST['name']); 

        if ($rename) {
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

    case 'folder_delete':
    break;

    default:
        die_with_error_code(500);
    break;
}
