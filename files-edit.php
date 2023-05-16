<?php
/**
 * File information editor
 */
define('IS_FILE_EDITOR', true);

$allowed_levels = array(9, 8, 7, 0);
require_once 'bootstrap.php';
log_in_required($allowed_levels);

$active_nav = 'files';

$page_title = __('Edit files', 'cftp_admin');

$page_id = 'file_editor';

define('CAN_INCLUDE_FILES', true);

// Editable
$editable = [];
$files = explode(',', $_GET['ids']);
foreach ($files as $file_id) {
    if (is_numeric($file_id)) {
        if (user_can_edit_file(CURRENT_USER_ID, $file_id)) {
            $editable[] = (int)$file_id;
        }
    }
}

$saved_files = [];

// Fill the categories array that will be used on the form
$categories = [];
$get_categories = get_categories();

function custom_download_exists($link)
{
    global $dbh;
    $statement = $dbh->prepare('SELECT link, file_id FROM ' . TABLE_CUSTOM_DOWNLOADS . ' WHERE link=:link');
    $statement->bindParam(':link', $link);
    $statement->execute();
    return $statement->fetchColumn();
}

function create_custom_download($link, $file_id, $client_id)
{
    global $dbh;
    if (custom_download_exists($link)) {
        $statement = $dbh->prepare('UPDATE ' . TABLE_CUSTOM_DOWNLOADS . ' SET file_id=:file_id, client_id=:client_id WHERE link=:link');
        $statement->bindParam(':link', $link);
        $statement->bindParam(':file_id', $file_id, PDO::PARAM_INT);
        $statement->bindParam(':client_id', $client_id, PDO::PARAM_INT);
        $statement->execute();
        return true;
    }
    else {
        $statement = $dbh->prepare('INSERT INTO ' . TABLE_CUSTOM_DOWNLOADS . ' (link, file_id, client_id) VALUES (:link, :file_id, :client_id)');
        $statement->bindParam(':link', $link);
        $statement->bindParam(':file_id', $file_id);
        $statement->bindParam(':client_id', $client_id, PDO::PARAM_INT);
        $statement->execute();
    }
    return false;
}

if (isset($_POST['save'])) {
    // Edit each file and its assignations
    $confirm = false;
    foreach ($_POST['file'] as $file) {
        $object = new \ProjectSend\Classes\Files($file['id']);
        if ($object->recordExists()) {
            if ($object->save($file) != false) {
                $saved_files[] = $file['id'];
            }
        }

        foreach ($file['custom_downloads'] as $custom_download) {
            global $dbh;

            if (custom_download_exists($custom_download["link"]) && (!isset($_GET['confirmed']) || !$_GET['confirmed'])) {
                $confirm = true;
                continue;
            }

            if ($custom_download['id']) {
                if ($custom_download['link']) {
                    if ($custom_download['link'] != $custom_download['id']) {
                        $statement = $dbh->prepare('UPDATE ' . TABLE_CUSTOM_DOWNLOADS . ' SET file_id=NULL WHERE link=:link');
                        $statement->bindParam(':link', $custom_download['id']);
                        $statement->execute();
                        if (create_custom_download($custom_download['link'], $file['id'], CURRENT_USER_ID)) {
                            global $flash;
                            $flash->warning(__('Updated existing custom link to point to this file.', 'cftp_admin'));
                        }
                    }
                }
                else { // remove file_id from custom download
                    $statement = $dbh->prepare('UPDATE ' . TABLE_CUSTOM_DOWNLOADS . ' SET file_id=NULL WHERE link=:link');
                    $statement->bindParam(':link', $custom_download['id']);
                    $statement->execute();
                }
            }
            else {
                if ($custom_download['link']) {
                    if (create_custom_download($custom_download['link'], $file['id'], CURRENT_USER_ID)) {
                        $flash->warning(__('Updated existing custom link to point to this file.', 'cftp_admin'));
                    }
                }
            }
        }
    }

    // Send the notifications
    if (get_option('notifications_send_when_saving_files') == '1') {
        $notifications = new \ProjectSend\Classes\EmailNotifications();
        $notifications->sendNotifications();
        if (!empty($notifications->getNotificationsSent())) {
            $flash->success(__('E-mail notifications have been sent.', 'cftp_admin'));
        }
        if (!empty($notifications->getNotificationsFailed())) {
            $flash->error(__("One or more notifications couldn't be sent.", 'cftp_admin'));
        }
        if (!empty($notifications->getNotificationsInactiveAccounts())) {
            if (CURRENT_USER_LEVEL == 0) {
                /**
                 * Clients do not need to know about the status of the
                 * creator's account. Show the ok message instead.
                 */
                $flash->success(__('E-mail notifications have been sent.', 'cftp_admin'));
            } else {
                $flash->warning(__('E-mail notifications for inactive clients were not sent.', 'cftp_admin'));
            }
        }
    } else {
        $flash->warning(__('E-mail notifications were not sent according to your settings. Make sure you have a cron job enabled if you need to send them.', 'cftp_admin'));
    }

    // Redirect
    $saved = implode(',', $saved_files);


    if ($confirm) {
        global $flash;
        $flash->success(__('Files saved successfully', 'cftp_admin'));
        $flash->warning(__('A custom link like this already exists, enter it again to override.', 'cftp_admin'));
        ps_redirect('files-edit.php?&ids=' . $saved . '&confirm=true');
    }
    else {
        $flash->success(__('Files saved successfully', 'cftp_admin'));
        ps_redirect('files-edit.php?&ids=' . $saved . '&saved=true');
    }
}

// Message
if (!empty($editable) && !isset($_GET['saved'])) {
    if (CURRENT_USER_LEVEL != 0) {
        $flash->info(__('You can skip assigning if you want. The files are retained and you may add them to clients or groups later.', 'cftp_admin'));
    }
}

if (count($editable) > 1) {
    // Header buttons
    $header_action_buttons = [
        [
            'url' => '#',
            'id' => 'files_collapse_all',
            'icon' => 'fa fa-chevron-right',
            'label' => __('Collapse all', 'cftp_admin'),
        ],
        [
            'url' => '#',
            'id' => 'files_expand_all',
            'icon' => 'fa fa-chevron-down',
            'label' => __('Expand all', 'cftp_admin'),
        ],
    ];
}

// Include layout files
include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>
<div class="row">
    <div class="col-12">
        <?php
        // Saved files
        $saved_files = [];
        if (!empty($_GET['saved'])) {
            foreach ($editable as $file_id) {
                if (is_numeric($file_id)) {
                    $saved_files[] = $file_id;
                }
            }

            // Generate the table using the class.
            $table = new \ProjectSend\Classes\Layout\Table([
                'id' => 'uploaded_files_tbl',
                'class' => 'footable table',
                'origin' => basename(__FILE__),
            ]);

            $thead_columns = array(
                array(
                    'content' => __('Title', 'cftp_admin'),
                ),
                array(
                    'content' => __('Description', 'cftp_admin'),
                ),
                array(
                    'content' => __('File Name', 'cftp_admin'),
                ),
                array(
                    'content' => __('Public', 'cftp_admin'),
                    'condition' => (CURRENT_USER_LEVEL != 0 || current_user_can_upload_public()),
                    'hide' => 'phone',
                ),
                array(
                    'content' => __("Actions", 'cftp_admin'),
                    'hide' => 'phone',
                ),
            );
            $table->thead($thead_columns);

            foreach ($saved_files as $file_id) {
                $file = new \ProjectSend\Classes\Files($file_id);
                if ($file->recordExists()) {
                    $table->addRow();

                    if ($file->public == '1') {
                        $col_public = '<a href="javascript:void(0);" class="btn btn-primary btn-sm public_link" data-type="file" data-public-url="'.$file->public_url.'" data-title="'.$file->title.'">'.__('Public', 'cftp_admin').'</a>';
                    } else {
                        $col_public = '<a href="javascript:void(0);" class="btn btn-pslight btn-sm disabled" rel="" title="">'.__('Private', 'cftp_admin').'</a>';
                    }

                    $col_actions = '<a href="files-edit.php?ids='.$file->id.'" class="btn-primary btn btn-sm">
                        <i class="fa fa-pencil"></i><span class="button_label">'.__('Edit file', 'cftp_admin').'</span>
                    </a>';

                    // Show the "My files" button only to clients
                    if (CURRENT_USER_LEVEL == 0) {
                        $col_actions .= ' <a href="'. CLIENT_VIEW_FILE_LIST_URL .'" class="btn-primary btn btn-sm">'.__('View my files', 'cftp_admin').'</a>';
                    }

                    // Add the cells to the row
                    $tbody_cells = array(
                        array(
                            'content' => $file->title,
                        ),
                        array(
                            'content' => htmlentities_allowed($file->description),
                        ),
                        array(
                            'content' => $file->filename_original,
                        ),
                        array(
                            'content' => $col_public,
                            'condition' => (CURRENT_USER_LEVEL != 0 || current_user_can_upload_public()),
                            'attributes' => array(
                                'class' => array('col_visibility'),
                            ),
                        ),
                        array(
                            'content' => $col_actions,
                        ),
                    );

                    foreach ($tbody_cells as $cell) {
                        $table->addCell($cell);
                    }

                    $table->end_row();
                }
            }

            echo $table->render();
        } else {
            // Generate the table of files ready to be edited
            if (!empty($editable)) {
                include_once FORMS_DIR . DS . 'file_editor.php';
            }
        }
        ?>
    </div>
</div>
<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
