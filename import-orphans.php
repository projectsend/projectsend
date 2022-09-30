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

include_once ADMIN_VIEWS_DIR . DS . 'header.php';

// Count clients to show an error message, or the form
$statement        = $dbh->query("SELECT id FROM " . TABLE_USERS . " WHERE level = '0'");
$count_clients    = $statement->rowCount();
$statement        = $dbh->query("SELECT id FROM " . TABLE_GROUPS);
$count_groups    = $statement->rowCount();

if ((!$count_clients or $count_clients < 1) && (!$count_groups or $count_groups < 1)) {
    message_no_clients();
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

// Import selected files and redirect to editor
if ($_POST) {
    $added = [];

    if (!empty($_POST['files'])) {
        foreach ($_POST['files'] as $filename) {
            $filename_path = UPLOADED_FILES_DIR . DS . $filename;
            if (!file_exists($filename_path)) {
                continue;
            }

            if (!file_is_allowed($filename)) {
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
        ps_redirect(BASE_URI . 'files-edit.php?ids=' . implode(',', $added));
    }
}
?>
<div class="row">
    <div class="col-12">
        <div class="form_actions_limit_results">
            <?php show_search_form('import-orphans.php'); ?>
        </div>

        <div class="form_actions_count">
            <p><?php echo sprintf(__('Found %d elements', 'cftp_admin'), (int)count($files)); ?>
        </div>

        <div class="form_results_filter">
            <?php
            $filters = array(
                'allowed' => array(
                    'title' => __('Allowed', 'cftp_admin'),
                    'link' => $this_page . '?allowed_type=allowed',
                    'count' => count($orphans['allowed']),
                ),
                'not_allowed' => array(
                    'title' => __('Not allowed', 'cftp_admin'),
                    'link' => $this_page . '?allowed_type=not_allowed',
                    'count' => count($orphans['not_allowed']),
                ),
            );
            foreach ($filters as $type => $filter) {
            ?>
                <a href="<?php echo $filter['link']; ?>" class="<?php echo $current_filter == $type ? 'filter_current' : 'filter_option' ?>"><?php echo $filter['title']; ?> (<?php echo $filter['count']; ?>)</a>
            <?php
            }
            ?>
        </div>

        <div class="clear"></div>

        <form action="import-orphans.php" name="import_orphans" id="import_orphans" method="post" enctype="multipart/form-data">
            <?php addCsrf(); ?>

            <?php
            // Generate the list of files if there is at least 1 available and allowed.
            if (isset($files) && count($files) > 0) {
                if (!user_can_upload_any_file_type(CURRENT_USER_ID) && $settings['allowed_type'] != 'not_allowed') {
                    $settings['only_allowed'] = true;
                    echo system_message('warning', __('This list only shows the files that are allowed according to your security settings. If the file type you need to add is not listed here, add the extension to the "Allowed file extensions" box on the options page.', 'cftp_admin'));
                    echo system_message('success', __('The following files can be imported', 'cftp_admin'));
                }

                $table_attributes = array(
                    'id' => 'import_orphans_table',
                    'class' => 'footable table',
                    'data-page-size' => FOOTABLE_PAGING_NUMBER,
                );
                $table = new \ProjectSend\Classes\TableGenerate($table_attributes);

                $thead_columns = array(
                    array(
                        'select_all' => true,
                        'condition' => $can_import,
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
                        'condition' => $can_import,
                    ),
                );
                $table->thead($thead_columns);

                foreach ($files as $file) {
                    $table->addRow();
                    /**
                     * Add the cells to the row
                     */
                    $tbody_cells = array(
                        array(
                            'content' => '<input type="checkbox" name="files[]" class="batch_checkbox select_file_checkbox" value="' . html_output($file['name']) . '" />',
                            'condition' => $can_import,
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
                            'condition' => $can_import,
                            'content' => '<button type="button" name="file_edit" data-name="' . html_output($file['name']) . '" class="btn btn-primary btn-sm btn-edit-file"><i class="fa fa-pencil"></i><span class="button_label">' . __('Import', 'cftp_admin') . '</span></button>' . "\n"
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
                    <div class="after_form_buttons">
                        <button type="submit" class="btn btn-wide btn-primary" id="upload-continue"><?php _e('Import selected files', 'cftp_admin'); ?></button>
                    </div>
            <?php
                }
            }

            // No files found
            else {
                if (isset($no_results_error)) {
                    switch ($no_results_error) {
                        case 'search':
                            $no_results_message = __('Your search keywords returned no results.', 'cftp_admin');
                            break;
                    }
                } else {
                    $no_results_message = __('There are no files available to add right now.', 'cftp_admin');
                    $no_results_message .= __('To use this feature you need to upload your files via FTP to the folder', 'cftp_admin');
                    $no_results_message .= ' <span class="format_url"><strong>' . html_output(UPLOADED_FILES_DIR) . '</strong></span>.';
                }

                echo system_message('danger', $no_results_message);
            }
            ?>
        </form>
    </div>
</div>
<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
