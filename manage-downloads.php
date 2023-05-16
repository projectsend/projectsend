<?php

/**
 * Allows to hide, show or delete the files assigned to the
 * selected client.
 */
$allowed_levels = array(9, 8, 7, 0);
require_once 'bootstrap.php';
log_in_required($allowed_levels);

$active_nav = 'files';

$page_title = __('Manage downloads', 'cftp_admin');

$page_id = 'manage_downloads';

$current_url = get_form_action_with_existing_parameters(basename(__FILE__), array('modify_id', 'modify_type'));

/**
 * Used to distinguish the current page results.
 * Global means all files.
 * Client or group is only when looking into files
 * assigned to any of them.
 */
$results_type = 'global';

/**
 * The client's id is passed on the URI.
 * Then get_client_by_id() gets all the other account values.
 */
if (isset($_GET['client'])) {
    if (!is_numeric($_GET['client'])) {
        exit_with_error_code(403);
    }

    $this_id = (int)$_GET['client'];
    $this_client = get_client_by_id($this_id);

    /** Add the name of the client to the page's title. */
    if (!empty($this_client)) {
        $page_title .= ' ' . __('for client', 'cftp_admin') . ' ' . html_entity_decode($this_client['name']);
        $search_on = 'client_id';
        $results_type = 'client';
    }
}

// Setting the filter options to avoid duplicates
$filter_options_client_id = array(
    '0' => __('client_id', 'cftp_admin'),
);
global $dbh;
global $flash;
$sql_client_ids = $dbh->prepare("SELECT client_id FROM " . TABLE_CUSTOM_DOWNLOADS . " GROUP BY client_id");
$sql_client_ids->execute();
$sql_client_ids->setFetchMode(PDO::FETCH_ASSOC);
while ($data_client_ids = $sql_client_ids->fetch()) {
    $filter_options_client_id[$data_client_ids['client_id']] = $data_client_ids['client_id'];
}

// The group's id is passed on the URI also
if (isset($_GET['group'])) {
    $this_id = $_GET['group'];
    $group = get_group_by_id($this_id);

    // Add the name of the client to the page's title.
    if (!empty($group['name'])) {
        $page_title .= ' ' . __('for group', 'cftp_admin') . ' ' . html_entity_decode($group['name']);
        $search_on = 'group_id';
        $results_type = 'group';
    }
}

// Apply the corresponding action to the selected files.
if (isset($_POST['action'])) {
    if (!empty($_POST['batch'])) {
        $selected_files = array_unique($_POST['batch']);

        switch ($_POST['action']) {
            case 'activate':
                /**
                 * Changes the value on the "hidden" column value on the database.
                 * This files are not shown on the client's file list. They are
                 * also not counted on the dashboard.php files count when the logged in
                 * account is the client.
                 */
                foreach ($selected_files as $file_id) {
                    $custom_download = new \ProjectSend\Classes\Files($file_id);
                    $custom_download->hide($results_type, $_POST['modify_id']);
                }

                $flash->success(__('The selected links were disabled.', 'cftp_admin'));
                break;
            case 'deactivate':
                foreach ($selected_files as $file_id) {
                    $custom_download = new \ProjectSend\Classes\Files($file_id);
                    $custom_download->show($results_type, $_POST['modify_id']);
                }

                $flash->success(__('The selected links were enabled.', 'cftp_admin'));
                break;
            case 'delete':
                $delete_results    = array(
                    'success' => 0,
                    'errors' => 0,
                );
                foreach ($selected_files as $index => $file_id) {
                    if (!empty($file_id)) {
                        $deletesql = $dbh->prepare('DELETE FROM ' . TABLE_CUSTOM_DOWNLOADS . ' WHERE link=:link');
                        $deletesql->execute(['link' => $file_id]);
                        $delete_results['success']++;
                    }
                    else {
                        $delete_results['errors']++;
                    }
                }

                if ($delete_results['success'] > 0) {
                    $flash->success(__('The selected files were deleted.', 'cftp_admin'));
                }
                if ($delete_results['errors'] > 0) {
                    $flash->error(__('Some files could not be deleted.', 'cftp_admin'));
                }
                break;
            case 'edit':
                ps_redirect(BASE_URI . 'files-edit.php?ids=' . implode(',', array_unique(array_map(function ($cd) use ($dbh) {
                    $fsql = $dbh->prepare('SELECT file_id FROM ' . TABLE_CUSTOM_DOWNLOADS . ' WHERE link=:link');
                    $fsql->execute(['link' => $cd]);
                    return $fsql->fetchColumn();
                    }, $selected_files))));
                break;
        }
    } else {
        $flash->error(__('Please select at least one file.', 'cftp_admin'));
    }

    ps_redirect($current_url);
}

// Global form action
$query_table_files = true;

if ($query_table_files === true) {
    // Get the files
    $params = [];

    /**
     * Add the download count to the main query.
     * If the page is filtering files by client, then
     * add the client ID to the subquery.
     */
    $add_user_to_query = '';
    if (isset($search_on) && $results_type == 'client') {
        $add_user_to_query = "AND user_id = :user_id";
        $params[':user_id'] = $this_id;
    }
    $cq = "SELECT * FROM " . TABLE_CUSTOM_DOWNLOADS . "";

    // Add the search terms
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $conditions[] = "(link LIKE :search)";
        $no_results_error = 'search';

        $search_terms = '%' . $_GET['search'] . '%';
        $params[':search'] = $search_terms;
    }

    // Filter by client_id
    if (isset($_GET['client_id']) && !empty($_GET['client_id'])) {
        $conditions[] = "client_id = :client_id";
        $no_results_error = 'filter';

        $params[':client_id'] = $_GET['client_id'];
    }

    /**
     * If the user is an client_id, or a client is editing their files
     * only show files uploaded by that account.
     */
    if (CURRENT_USER_LEVEL == '7' || CURRENT_USER_LEVEL == '0') {
        $conditions[] = "client_id = :client_id";
        $no_results_error = 'account_level';

        $params[':client_id'] = CURRENT_USER_USERNAME;
    }

    // Build the final query
    if (!empty($conditions)) {
        foreach ($conditions as $index => $condition) {
            $cq .= ($index == 0) ? ' WHERE ' : ' AND ';
            $cq .= $condition;
        }
    }

    /**
     * Add the order.
     * Defaults to order by: date, order: DESC
     */
    $cq .= sql_add_order(TABLE_CUSTOM_DOWNLOADS, 'timestamp', 'desc');

    // Pre-query to count the total results
    $count_sql = $dbh->prepare($cq);
    $count_sql->execute($params);
    $count_for_pagination = $count_sql->rowCount();

    // Repeat the query but this time, limited by pagination
    $cq .= " LIMIT :limit_start, :limit_number";
    $sql = $dbh->prepare($cq);

    $pagination_page = (isset($_GET["page"])) ? $_GET["page"] : 1;
    $pagination_start = ($pagination_page - 1) * get_option('pagination_results_per_page');
    $params[':limit_start'] = $pagination_start;
    $params[':limit_number'] = get_option('pagination_results_per_page');

    $sql->execute($params);
    $count = $sql->rowCount();
} else {
    $count_for_pagination = 0;
}

if (!$count) {
    if (isset($no_results_error)) {
        switch ($no_results_error) {
            case 'search':
                $flash->error(__('Your search keywords returned no results.', 'cftp_admin'));
                break;
            case 'category':
                $flash->error(__('There are no files assigned to this category.', 'cftp_admin'));
                break;
            case 'filter':
                $flash->error(__('The filters you selected returned no results.', 'cftp_admin'));
                break;
            case 'account_level':
                $flash->error(__('You have not uploaded any files yet.', 'cftp_admin'));
                break;
        }
    } else {
        $flash->warning(__('There are no files.', 'cftp_admin'));
    }
}

// Setting the filter options to avoid duplicates
$filter_options_uploader = array(
    '0' => __('Creator', 'cftp_admin'),
);
$sql_uploaders = $dbh->prepare("SELECT client_id FROM " . TABLE_CUSTOM_DOWNLOADS . " GROUP BY client_id");
$sql_uploaders->execute();
$sql_uploaders->setFetchMode(PDO::FETCH_ASSOC);
while ($data_uploaders = $sql_uploaders->fetch()) {
    if (is_null($data_uploaders['client_id'])) continue;
    $sql_cd = $dbh->prepare('SELECT name FROM ' . TABLE_USERS . ' WHERE id=:client_id');
    $sql_cd->execute(['client_id' => $data_uploaders['client_id']]);
    $cl = $sql_cd->fetchColumn();
    $filter_options_uploader[$data_uploaders['client_id']] = $cl;
}

// Search + filters bar data
$search_form_action = 'manage-downloads.php';
if (CURRENT_USER_LEVEL != '0') {
    $filters_form = [
        'action' => $current_url,
        'ignore_form_parameters' => ['hidden', 'action', 'client_id'],
    ];
    // Filters are not available for clients
    if ($results_type == 'global') {
        $filters_form['items'] = [
            'client_id' => [
                'current' => (isset($_GET['client_id'])) ? $_GET['client_id'] : null,
                'options' => $filter_options_uploader,
            ],
        ];
    } else {
        // Filters available when results are only those of a group or client
        $filters_form['items'] = [
            'hidden' => [
                'current' => (isset($_GET['hidden'])) ? $_GET['hidden'] : null,
                'options' => [
                    '2' => __('All statuses', 'cftp_admin'),
                    '0' => __('Visible', 'cftp_admin'),
                    '1' => __('Hidden', 'cftp_admin'),
                ],
            ],
        ];
    }
}

// Results count and form actions
$elements_found_count = $count_for_pagination;// + count($folders);
$bulk_actions_items = [
    'none' => __('Select action', 'cftp_admin'),
    'edit' => __('Edit', 'cftp_admin'),
];

if (CURRENT_USER_LEVEL != '0' || (CURRENT_USER_LEVEL == '0' && get_option('clients_can_delete_own_files') == '1'))
    $bulk_actions_items['delete'] = __('Delete', 'cftp_admin');

// Include layout files
include_once ADMIN_VIEWS_DIR . DS . 'header.php';

include_once LAYOUT_DIR . DS . 'search-filters-bar.php';

?>

    <form action="<?php echo $current_url; ?>" name="files_list" method="post" class="batch_actions">
        <?php addCsrf(); ?>
        <?php include_once LAYOUT_DIR . DS . 'form-counts-actions.php'; ?>

        <?php if (isset($search_on)) { ?>
            <input type="hidden" name="modify_type" id="modify_type" value="<?php echo $search_on; ?>" />
            <input type="hidden" name="modify_id" id="modify_id" value="<?php echo $this_id; ?>" />
        <?php } ?>

        <div class="row">
            <div class="col-12">
                <?php
                if ($count_for_pagination > 0) {
                    // Generate the table using the class.
                    $table = new \ProjectSend\Classes\Layout\Table([
                        'id' => 'files_tbl',
                        'class' => 'footable table',
                        'origin' => basename(__FILE__),
                    ]);

                    /**
                     * Set the conditions to true or false once here to
                     * avoid repetition
                     * They will be used to generate or no certain columns
                     */
                    $conditions = array(
                        'select_all' => true,
                        'is_not_client' => (CURRENT_USER_LEVEL != '0') ? true : false,
                        'can_set_public' => (CURRENT_USER_LEVEL != '0' || current_user_can_upload_public()) ? true : false,
                        'can_set_expiration' => (CURRENT_USER_LEVEL != '0' || get_option('clients_can_set_expiration_date') == '1') ? true : false,
                        'total_downloads' => (CURRENT_USER_LEVEL != '0' && !isset($search_on)) ? true : false,
                        'is_search_on' => (isset($search_on)) ? true : false,
                    );

                    $thead_columns = array(
                        array(
                            'select_all' => true,
                            'attributes' => array(
                                'class' => array('td_checkbox'),
                            ),
                            'condition' => $conditions['select_all'],
                        ),
                        array(
                            'sortable' => true,
                            'sort_url' => 'link',
                            'content' => __('Title', 'cftp_admin'),
                        ),
                        array(
                            'content' => __('File', 'cftp_admin')
                        ),
                        array(
                            'content' => __('Preview', 'cftp_admin'),
                            'hide' => 'phone,tablet',
                        ),
                        array(
                            'sortable' => true,
                            'sort_url' => 'timestamp',
                            'sort_default' => true,
                            'content' => __('Added on', 'cftp_admin'),
                            'hide' => 'phone',
                        ),
                        array(
                            'sortable' => true,
                            'sort_url' => 'client_id',
                            'content' => __('Creator', 'cftp_admin'),
                            'hide' => 'phone,tablet',
                            'condition' => $conditions['is_not_client'],
                        ),
                        array(
                            'sortable' => true,
                            'sort_url' => 'expires',
                            'content' => __('Expiry', 'cftp_admin'),
                            'hide' => 'phone',
                            'condition' => $conditions['can_set_expiration'],
                        ),
                        array(
                            'sortable' => true,
                            'sort_url' => 'download_count',
                            'content' => __('Download count', 'cftp_admin'),
                            'hide' => 'phone',
                            'condition' => $conditions['is_search_on'],
                        ),
                        array(
                            'sortable' => true,
                            'sort_url' => 'download_count',
                            'content' => __('Total downloads', 'cftp_admin'),
                            'hide' => 'phone',
                            'condition' => $conditions['total_downloads'],
                        ),
                        array(
                            'content' => __('Actions', 'cftp_admin'),
                            'hide' => 'phone',
                        ),
                    );

                    $table->thead($thead_columns);

                    // Files
                    $sql->setFetchMode(PDO::FETCH_ASSOC);
                    while ($row = $sql->fetch()) {
                        $table->addRow([
                            'class' => '',
                            'attributes' => [
                                'draggable' => 'false',
                            ],
                            'data-attributes' => [
                                'draggable-type' => 'file',
                                'file-id' => $row['id'],
                            ],
                        ]);
                        $custom_download = (object) $row;
                        $file = new \ProjectSend\Classes\Files($custom_download->file_id);

                        // Preview
                        $preview_cell = '';
                        if ($file->embeddable) {
                            $preview_cell = '<button class="btn btn-warning btn-sm btn-wide get-preview" data-url="' . BASE_URI . 'process.php?do=get_preview&file_id=' . $file->id . '">' . __('Preview', 'cftp_admin') . '</button>';
                        }
                        if (file_is_image($file->full_path)) {
                            $thumbnail = make_thumbnail($file->full_path, null, 50, 50);
                            if (!empty($thumbnail['thumbnail']['url'])) {
                                $preview_cell = '<a href="#" class="get-preview" data-url="' . BASE_URI . 'process.php?do=get_preview&file_id=' . $file->id . '">
                                            <img src="' . $thumbnail['thumbnail']['url'] . '" class="thumbnail" />
                                        </a>';
                            }
                        }

                        // Expiration
                        $expires_date = $custom_download->expiry_date ?: ($file->expires ? $file->expiry_date : null);
                        if (is_null($expires_date) && $file->isPublic()) {
                            $expires_button = 'success';
                            $expires_label = __('Does not expire', 'cftp_admin');
                        } else {
                            if (!$file->isPublic()) {
                                $expires_button = 'danger';
                                $expires_label = __('File is not public', 'cftp_admin');
                            }
                            else {
                                $expires_date = date(get_option('timeformat'), strtotime($expires_date));

                                if ($expires_date < new \DateTime()) {
                                    $expires_button = 'danger';
                                    $expires_label = __('Expired on', 'cftp_admin') . ' ' . $expires_date;
                                } else {
                                    $expires_button = 'info';
                                    $expires_label = __('Expires on', 'cftp_admin') . ' ' . $expires_date;
                                }
                            }
                        }

                        $custom_download_uri = get_option('custom_download_uri');
                        if (!$custom_download_uri) $custom_download_uri = 'custom_downloads.php?link=';
                        $custom_download_link = $custom_download_uri . $custom_download->link;

                        $title_content = '<a href="' . $custom_download_link . '" target="_blank">' . $custom_download->link . '</a>';
                        if (file_is_image($file->full_path)) {
                            $dimensions = $file->getDimensions();
                            if (!empty($dimensions)) {
                                $title_content .= '<br><div class="file_meta"><small>'.$dimensions['width'].' x '.$dimensions['height'].' px</small></div>';
                            }
                        }

                        $user = '';
                        if ($custom_download->client_id) {
                            $usersql = $dbh->prepare('SELECT name FROM ' . TABLE_USERS . ' WHERE id=:client_id');
                            $usersql->execute(['client_id' => $custom_download->client_id]);
                            $user = $usersql->fetchColumn();
                        }

                        $file_edit_button = '<a href="files-edit.php?ids=' . $file->id . '" class="btn btn-primary btn-sm"><i class="fa fa-pencil"></i><span class="button_label">' . __('Edit', 'cftp_admin') . '</span></a>';
                        $link_copy_button = <<<EOL
                            <a href="#" class="btn btn-pslight btn-sm" onclick="navigator.clipboard.writeText('$custom_download_link');">
                                <i class="fa fa-copy" style="cursor: pointer"></i>
                            </a
EOL;

                        //* Add the cells to the row
                        $tbody_cells = array(
                            array(
                                'checkbox' => true,
                                'value' => $custom_download->link,
                                'condition' => $conditions['select_all'],
                            ),
                            array(
                                'attributes' => array(
                                    'class' => array('file_name'),
                                ),
                                'content' => $title_content,
                            ),
                            array(
                                'attributes' => array(
                                    'class' => array('file_name'),
                                ),
                                'content' => $file->title,
                            ),
                            array(
                                'content' => $preview_cell,
                            ),
                            array(
                                'content' => format_date($custom_download->timestamp),
                            ),
                            array(
                                'content' => $user,
                                'condition' => $conditions['is_not_client'],
                            ),
                            array(
                                'content' => '<a href="javascript:void(0);" class="btn btn-' . $expires_button . ' disabled btn-sm" rel="" title="">' . $expires_label . '</a>',
                                'condition' => $conditions['can_set_expiration'],
                            ),
                            array(
                                'content' => $custom_download->visit_count,
                                'condition' => $conditions['total_downloads'],
                            ),
                            array(
                                'content' => $file_edit_button . $link_copy_button,
                            ),
                        );

                        foreach ($tbody_cells as $cell) {
                            $table->addCell($cell);
                        }

                        $table->end_row();
                    }

                    echo $table->render();
                }
                ?>
            </div>
        </div>
    </form>

<?php
if (!empty($table)) {
    // PAGINATION
    $pagination = new \ProjectSend\Classes\Layout\Pagination;
    echo $pagination->make([
        'link' => 'manage-downloads.php',
        'current' => $pagination_page,
        'item_count' => $count_for_pagination,
    ]);
}
?>

<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
