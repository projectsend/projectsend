<?php
/**
 * Serves the public downloads.
 */

 use ProjectSend\Classes\Download;

$allowed_levels = array(9, 8, 7, 0);

$page_id = 'public_download';

if (!empty($_GET['token']) && !empty($_GET['id'])) {
    $token = htmlentities($_GET['token']);
    $file_id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

    if (!is_numeric($_GET['id'])) {
        exit;
    }

    $can_download = false;
    $can_view = false; // Can only view information about the file, not download it

    $file = new \ProjectSend\Classes\Files($file_id);

    if ($file->public_token != $token || $file->expired == true) {
        exit_with_error_code(403);
    }

    if ($file->public == 1 && $file->public_token == $token) {
        $can_download = true;
        $can_view = true;
    }

    if (get_option('enable_landing_for_all_files') == '1') {
        $can_view = true;
    } else {
        if ($file->public == 0) {
            exit_with_error_code(403);
        }
    }

    if ($can_download == true) {
        if (isset($_GET['download'])) {
            record_new_download(0, $file->id);

            /** Record the action log */
            $logger = new \ProjectSend\Classes\ActionsLog;
            $new_record_action = $logger->addEntry([
                'action' => 37,
                'owner_user' => null,
                'owner_id' => 0,
                'affected_file' => $file->id,
                'affected_file_name' => $file->filename_original,
            ]);

            // DOWNLOAD
            $process = new Download;
            $alias = $process->getAlias($file);
            $process->serveFile($file->full_path, $file->filename_unfiltered, $alias);
            exit;
        }
    }
} else {
    exit_with_error_code(403);
}

$dont_redirect_if_logged = 1;

require get_template_file_location('public-download.php');
