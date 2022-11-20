<?php
/**
 * Shows a list of files found on the upload/ folder that
 * are not yet on the database, meaning they were uploaded
 * via FTP.
 * Only shows files that are allowed according to the system
 * settings.
 * Submits an array of file names.
 */
$allowed_levels = array(9, 8, 7);
require_once 'bootstrap.php';

$active_nav = 'files';
$this_page = 'import-orphans.php';

$page_title = __('Find orphan files', 'cftp_admin');

$page_id = 'import_orphans';

/**
 * Apply the corresponding action to the selected clients.
 */
if (isset($_POST['action'])) {
    /** Continue only if 1 or more clients were selected. */
    if (!empty($_POST['files'])) {
        $custom_assets = $_POST['files'];

        switch ($_POST['action']) {
            case 'import':
                $added = [];

                if (!empty($_POST['files'])) {
                    foreach ($_POST['files'] as $filename) {
                        $filename_path = UPLOADED_FILES_DIR . DS . $filename;
                        if (!file_exists($filename_path) || !file_is_allowed($filename)) {
                            continue;
                        }
            
                        $file = new \ProjectSend\Classes\Files;
                        $file->moveToUploadDirectory($filename_path);
                        $file->setDefaults();
                        $file->addToDatabase();
            
                        // Add it to the array of editable files
                        $added[] = $file->getId();
                    }
                }
            
                if (!empty($added)) {
                    $flash->success(__('The selected files were imported successfully.', 'cftp_admin'));
                    ps_redirect(BASE_URI . 'files-edit.php?ids=' . implode(',', $added));
                }
            
                break;
            case 'delete':
                $selected = count($_POST['files']);
                $deleted = 0;
                foreach ($_POST['files'] as $filename) {
                    $filename_path = UPLOADED_FILES_DIR . DS . $filename;
                    $delete = delete_file_from_disk($filename_path);
                    if ($delete) { $deleted++; }
                }

                if ($deleted > 0) {
                    if ($deleted == $selected) {
                        $flash->success(__('The selected files were deleted.', 'cftp_admin'));
                    } else {
                        $flash->warning(__('Not all of the selected files where were deleted.', 'cftp_admin'));
                    }
                }

                break;
        }
    } else {
        $flash->error(__('Please select at least one file.', 'cftp_admin'));
    }

    ps_redirect($current_url);
}

$orphan_files = new \ProjectSend\Classes\OrphanFiles;
$settings = [];
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $no_results_error = 'search';
    $settings['search'] = $_GET['search'];
}

$search_type = (isset($_GET['allowed_type'])) ? $_GET['allowed_type'] : 'allowed';

$settings['allowed_type'] = $search_type;
$orphans = $orphan_files->getFiles($settings);

// Table options
$can_import = false;
$files = $orphans['allowed'];

switch ($search_type) {
    case 'allowed':
    default:
        $files = $orphans['allowed'];
        $can_import = true;
        break;
    case 'not_allowed':
        $files = $orphans['not_allowed'];
        break;
}

$count = count($files);

// Flash errors
if (!$count) {
    if (isset($no_results_error)) {
        switch ($no_results_error) {
            case 'search':
                $flash->error(__('Your search keywords returned no results.', 'cftp_admin'));
                break;
        }
    } else {
        $flash->error(sprintf(__('There are no files available to add right now. To use this feature you need to upload your files via FTP to the folder %s.', 'cftp_admin'), '<span class="format_url"><strong>' . html_output(UPLOADED_FILES_DIR) . '</strong></span>'));
    }
}

// Header buttons
if (current_user_can_upload()) {
    $header_action_buttons = [
        [
            'url' => 'upload.php',
            'label' => __('Upload files', 'cftp_admin'),
        ],
    ];
}

// Search + filters bar data
$search_form_action = 'import-orphans.php';
$filters_form = [];

// Results count and form actions 
$elements_found_count = $count;
$bulk_actions_items = [
    'none' => __('Select action', 'cftp_admin'),
    'delete' => __('Delete', 'cftp_admin'),
];
if ($can_import) {
    $bulk_actions_items['import'] = __('Import', 'cftp_admin');
}

// Filters as links
$filters_links = [
    'allowed' => [
        'title' => __('Allowed', 'cftp_admin'),
        'link' => $this_page . '?allowed_type=allowed',
        'count' => count($orphans['allowed']),
    ],
    'not_allowed' => [
        'title' => __('Not allowed', 'cftp_admin'),
        'link' => $this_page . '?allowed_type=not_allowed',
        'count' => count($orphans['not_allowed']),
    ],
];

message_no_clients();

if (isset($files) && count($files) > 0) {
    if (!user_can_upload_any_file_type(CURRENT_USER_ID) && $settings['allowed_type'] != 'not_allowed') {
        $settings['only_allowed'] = true;
        $flash->warning(__('This list only shows the files that are allowed according to your security settings. If the file type you need to add is not listed here, add the extension to the "Allowed file extensions" box on the options page.', 'cftp_admin'));
        $flash->success(__('The following files can be imported', 'cftp_admin'));
    }
}

include_once ADMIN_VIEWS_DIR . DS . 'header.php';

include_once LAYOUT_DIR . DS . 'search-filters-bar.php';
?>

<form action="import-orphans.php" name="import_orphans" id="import_orphans" method="post" enctype="multipart/form-data">
    <div class="row">
        <div class="col-12">
            <?php addCsrf(); ?>
            <?php include_once LAYOUT_DIR . DS . 'form-counts-actions.php'; ?>

            <?php
            // Generate the list of files if there is at least 1 available and allowed.
            if (isset($files) && count($files) > 0) {
                $table = new \ProjectSend\Classes\Layout\Table([
                    'id' => 'import_orphans_table',
                    'class' => 'footable table',
                    'data-page-size' => FOOTABLE_PAGING_NUMBER,
                    'origin' => basename(__FILE__),
                ]);

                $thead_columns = array(
                    array(
                        'select_all' => true,
                        'attributes' => array(
                            'class' => array('td_checkbox'),
                            'data-sortable' => 'false',
                        ),
                    ),
                    array(
                        'content' => __('File name', 'cftp_admin'),
                        'attributes' => array(
                            'data-sort-initial' => 'true',
                        ),
                    ),
                    array(
                        'content' => __('File size', 'cftp_admin'),
                        'hide' => 'phone',
                        'attributes' => array(
                            'data-type' => 'numeric',
                        ),
                    ),
                    array(
                        'content' => __('Last modified', 'cftp_admin'),
                        'hide' => 'phone',
                        'attributes' => array(
                            'data-type' => 'numeric',
                        ),
                    ),
                    array(
                        'content' => __('Actions', 'cftp_admin'),
                    ),
                );
                $table->thead($thead_columns);

                foreach ($files as $file) {
                    $table->addRow();
                    // Add the cells to the row
                     
                    $action_buttons = '';
                    if ($can_import) {
                        $action_buttons .= '<button type="button" name="file_edit" data-name="' . html_output($file['name']) . '" class="btn btn-primary btn-sm btn-edit-file"><i class="fa fa-pencil"></i><span class="button_label">' . __('Import', 'cftp_admin') . '</span></button>' . "\n";
                    }

                    $tbody_cells = array(
                        array(
                            'content' => '<input type="checkbox" name="files[]" class="batch_checkbox select_file_checkbox" value="' . html_output($file['name']) . '" />',
                        ),
                        array(
                            'content' => html_output($file['name']),
                        ),
                        array(
                            'content' => html_output(format_file_size(get_real_size($file['path']))),
                            'attributes' => array(
                                'data-value' => filesize($file['path']),
                            ),
                        ),
                        array(
                            'content' => date(get_option('timeformat'), filemtime($file['path'])),
                            'attributes' => array(
                                'data-value' => filemtime($file['path']),
                            ),
                        ),
                        array(
                            'actions' => true,
                            'content' => $action_buttons,
                        ),
                    );

                    foreach ($tbody_cells as $cell) {
                        $table->addCell($cell);
                    }

                    $table->end_row();
                }

                echo $table->render();

                if ($can_import) {
            ?>
                    <nav aria-label="<?php _e('Results navigation', 'cftp_admin'); ?>">
                        <div class="pagination_wrapper text-center">
                            <ul class="pagination hide-if-no-paging"></ul>
                        </div>
                    </nav>
            <?php
                }
            }
            ?>
        </form>
    </div>
</div>
<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
