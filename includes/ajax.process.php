<?php
// Process ajax calls
require_once '../bootstrap.php';

global $auth;
global $logger;

extend_session();

if (!user_is_logged_in()) {
    die_with_error_code(403);
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
        $folder = new \ProjectSend\Classes\Folder($_POST['folder_id']);
        $delete = $folder->delete(); 

        if (!$delete) {
            echo json_encode([
                'status' => 'error',
            ]);
            die_with_error_code(500);
        }

        if (in_array($_POST['folder_id'], $delete['folders'])) {
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

    case 'thumbnails_regenerate_get_files':
        if (!current_user_can('edit_settings')) {
            echo json_encode(['status' => 'error', 'message' => 'Permission denied']);
            exit;
        }

        // Validate required parameters
        if (!isset($_POST['formats']) || !is_array($_POST['formats'])) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid formats parameter'
            ]);
            exit;
        }

        // Get parameters with defaults
        $formats = $_POST['formats'];
        $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : null;
        $batch_size = isset($_POST['batch_size']) ? (int)$_POST['batch_size'] : 10;
        $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;

        // Get files for processing
        $files_data = get_files_for_thumbnail_regeneration($formats, $start_date, $end_date, $batch_size, $offset);

        echo json_encode([
            'status' => 'success',
            'data' => $files_data
        ]);

        exit;
    break;

    case 'thumbnails_regenerate_process':
        if (!current_user_can('edit_settings')) {
            echo json_encode(['status' => 'error', 'message' => 'Permission denied']);
            exit;
        }

        // Validate required parameters
        if (!isset($_POST['file_id']) || !isset($_POST['width']) || !isset($_POST['height'])) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Missing required parameters'
            ]);
            exit;
        }

        $file_id = (int)$_POST['file_id'];
        $width = (int)$_POST['width'];
        $height = (int)$_POST['height'];

        // Validate dimensions
        if ($width < 50 || $width > 1000 || $height < 50 || $height > 1000) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid thumbnail dimensions'
            ]);
            exit;
        }

        // Process thumbnail regeneration with error handling
        try {
            $result = regenerate_single_thumbnail($file_id, $width, $height);

            if ($result['success']) {
                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'file_id' => $result['file_id'],
                        'filename' => $result['filename']
                    ]
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => $result['error'],
                    'data' => [
                        'file_id' => isset($result['file_id']) ? $result['file_id'] : null,
                        'filename' => isset($result['filename']) ? $result['filename'] : null
                    ]
                ]);
            }
        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Fatal error: ' . $e->getMessage(),
                'data' => [
                    'file_id' => $file_id,
                    'filename' => null
                ]
            ]);
        } catch (Error $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'PHP Error: ' . $e->getMessage(),
                'data' => [
                    'file_id' => $file_id,
                    'filename' => null
                ]
            ]);
        }

        exit;
    break;

    case 'recalculate_storage':
        header('Content-Type: application/json');

        if (!current_user_can('edit_settings')) {
            echo json_encode([
                'status' => 'error',
                'message' => __('You do not have permission to perform this action.', 'cftp_admin')
            ]);
            exit;
        }

        set_time_limit(0);

        // Get ALL files for full recalculation
        $files_sql = "SELECT id, url, disk_folder_year, disk_folder_month
                      FROM " . TABLE_FILES;
        $files_statement = $dbh->prepare($files_sql);
        $files_statement->execute();
        $files = $files_statement->fetchAll(PDO::FETCH_ASSOC);

        $total_files = count($files);
        $updated_count = 0;
        $total_storage = 0;

        $update_sql = "UPDATE " . TABLE_FILES . " SET size = :size WHERE id = :id";
        $update_statement = $dbh->prepare($update_sql);

        foreach ($files as $file) {
            $filename = $file['url'];
            if (empty($filename)) {
                continue;
            }

            // Construct file path directly (same logic as Files::getFilePath())
            $path = UPLOADED_FILES_DIR . DS;
            if (!empty($file['disk_folder_year'])) {
                $path .= $file['disk_folder_year'] . DS;
            }
            if (!empty($file['disk_folder_month'])) {
                $path .= $file['disk_folder_month'] . DS;
            }
            $path .= $filename;

            // Fallback to flat path if date-folder path doesn't exist
            if (!file_exists($path) && (!empty($file['disk_folder_year']) || !empty($file['disk_folder_month']))) {
                $path = UPLOADED_FILES_DIR . DS . $filename;
            }

            if (file_exists($path)) {
                try {
                    $file_size = get_real_size($path);
                    if (is_numeric($file_size) && $file_size > 0) {
                        $update_statement->execute([
                            'size' => $file_size,
                            'id' => $file['id']
                        ]);
                        $total_storage += $file_size;
                        $updated_count++;
                    }
                } catch (Exception $e) {
                    error_log("ProjectSend: Recalculate storage - error for file ID {$file['id']}: " . $e->getMessage());
                }
            }
        }

        echo json_encode([
            'status' => 'success',
            'message' => sprintf(
                __('Storage recalculated. Updated %d of %d files.', 'cftp_admin'),
                $updated_count,
                $total_files
            ),
            'updated_count' => $updated_count,
            'total_files' => $total_files,
            'total_storage_formatted' => format_file_size($total_storage)
        ]);
        exit;
    break;

    default:
        die_with_error_code(500);
    break;
}
