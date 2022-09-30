<?php
/**
 * Serves the public downloads.
 */

 use ProjectSend\Classes\Download;

$allowed_levels = array(9, 8, 7, 0);
require_once 'bootstrap.php';

$page_title = __('File information', 'cftp_admin');

$dont_redirect_if_logged = 1;

include_once ADMIN_VIEWS_DIR . DS . 'header-unlogged.php';

if (!empty($_GET['token']) && !empty($_GET['id'])) {
    $token = htmlentities($_GET['token']);
    $file_id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

    if (!is_numeric($_GET['id'])) {
        exit;
    }

    $can_download = false;
    $can_view = false; // Can only view information about the file, not download it

    $file = new \ProjectSend\Classes\Files;
    $file->get($file_id);

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
?>

<div class="row">
    <div class="col-12 col-sm-12 col-lg-8 col-lg-offset-2">

        <div class="white-box">
            <div class="white-box-interior">
                <?php
                if ($can_view) {
                ?>
                    <div class="text-center">
                        <h2 class="file_title">
                            <span class="badge bg-default">
                                <?php echo $file->filename_original; ?>
                            </span>
                        </h2>
                        <h3><?php echo $file->title; ?></h3>

                        <div class="description">
                            <?php echo $file->description; ?>
                        </div>

                        <div class="size">
                            <?php echo $file->size_formatted; ?>
                        </div>

                        <?php if ($can_download == true) { ?>
                            <div class="actions">
                                <a href="<?php echo $file->public_url . '&download'; ?>" class="btn btn-primary">
                                    <?php _e('Download file', 'cftp_admin'); ?>
                                </a>
                            </div>
                        <?php } ?>
                    </div>
                <?php
                }
                ?>
            </div>
        </div>

        <div class="login_form_links">
            <p><a href="<?php echo BASE_URI; ?>" target="_self"><?php _e('Go back to the homepage.', 'cftp_admin'); ?></a></p>
        </div>
    </div>
</div>

<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
