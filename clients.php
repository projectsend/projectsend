<?php
/**
 * Show the list of current clients.
 */
$allowed_levels = array(9, 8);
require_once 'bootstrap.php';

$active_nav = 'clients';

$page_title = __('Clients Administration', 'cftp_admin');

$current_url = get_form_action_with_existing_parameters(basename(__FILE__));

// Apply the corresponding action to the selected clients.
if (isset($_POST['action'])) {
    if (!empty($_POST['batch'])) {
        $selected_clients = $_POST['batch'];

        switch ($_POST['action']) {
            case 'activate':
                foreach ($selected_clients as $work_client) {
                    $this_client = new \ProjectSend\Classes\Users();
                    if ($this_client->get($work_client)) {
                        $hide_user = $this_client->setActiveStatus(1);
                    }
                }

                $flash->success(__('The selected clients were marked as active.', 'cftp_admin'));
                break;
            case 'deactivate':
                foreach ($selected_clients as $work_client) {
                    $this_client = new \ProjectSend\Classes\Users();
                    if ($this_client->get($work_client)) {
                        $hide_user = $this_client->setActiveStatus(0);
                    }
                }

                $flash->success(__('The selected clients were marked as inactive.', 'cftp_admin'));
                break;
            case 'delete':
                foreach ($selected_clients as $work_client) {
                    $this_client = new \ProjectSend\Classes\Users();
                    if ($this_client->get($work_client)) {
                        $delete_user = $this_client->delete();
                    }
                }

                $flash->success(__('The selected clients were deleted.', 'cftp_admin'));
                break;
        }
    } else {
        $flash->error(__('Please select at least one client.', 'cftp_admin'));
    }

    ps_redirect($current_url);
}

// Query the clients
$params = [];

$cq = "SELECT id FROM " . TABLE_USERS . " WHERE level='0' AND account_requested='0'";

// Add the search terms
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $cq .= " AND (name LIKE :name OR user LIKE :user OR address LIKE :address OR phone LIKE :phone OR email LIKE :email OR contact LIKE :contact)";
    $no_results_error = 'search';

    $search_terms = '%' . $_GET['search'] . '%';
    $params[':name'] = $search_terms;
    $params[':user'] = $search_terms;
    $params[':address'] = $search_terms;
    $params[':phone'] = $search_terms;
    $params[':email'] = $search_terms;
    $params[':contact'] = $search_terms;
}

// Add the active filter
if (isset($_GET['active']) && $_GET['active'] != '2') {
    $cq .= " AND active = :active";
    $no_results_error = 'filter';

    $params[':active'] = (int)$_GET['active'];
}

// Add the order
$cq .= sql_add_order(TABLE_USERS, 'id', 'desc');

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

// Flash errors
if (!$count) {
    if (isset($no_results_error)) {
        switch ($no_results_error) {
            case 'search':
                $flash->error(__('Your search keywords returned no results.', 'cftp_admin'));
                break;
            case 'filter':
                $flash->error(__('The filters you selected returned no results.', 'cftp_admin'));
            break;
        }
    } else {
        $flash->error(__('There are no clients yet.', 'cftp_admin'));
    }
}

// Header buttons
$header_action_buttons = [
    [
        'url' => 'clients-add.php',
        'label' => __('Create new', 'cftp_admin'),
    ],
];

// Search + filters bar data
$search_form_action = 'clients.php';
$filters_form = [
    'action' => $current_url,
    'items' => [
        'active' => [
            'current' => (isset($_GET['active'])) ? $_GET['active'] : null,
            'placeholder' => [
                'value' => '2',
                'label' => __('All statuses', 'cftp_admin')
            ],
            'options' => [
                '1' => __('Active', 'cftp_admin'),
                '0' => __('Inactive', 'cftp_admin'),
            ],
        ]
    ]
];

// Results count and form actions 
$elements_found_count = $count_for_pagination;
$bulk_actions_items = [
    'none' => __('Select action', 'cftp_admin'),
    'activate' => __('Activate', 'cftp_admin'),
    'deactivate' => __('Deactivate', 'cftp_admin'),
    'delete' => __('Delete', 'cftp_admin'),
];

// Include layout files
include_once ADMIN_VIEWS_DIR . DS . 'header.php';

include_once LAYOUT_DIR . DS . 'search-filters-bar.php';
?>
<form action="<?php echo $current_url; ?>" name="clients_list" method="post" class="form-inline batch_actions">
    <?php addCsrf(); ?>
    <?php include_once LAYOUT_DIR . DS . 'form-counts-actions.php'; ?>

    <div class="row">
        <div class="col-12">
            <?php
            if ($count > 0) {
                // Generate the table using the class.
                $table_attributes = array(
                    'id' => 'clients_tbl',
                    'class' => 'footable table',
                );
                $table = new \ProjectSend\Classes\TableGenerate($table_attributes);

                $thead_columns = array(
                    array(
                        'select_all' => true,
                        'attributes' => array(
                            'class' => array('td_checkbox'),
                        ),
                    ),
                    array(
                        'sortable' => true,
                        'sort_url' => 'timestamp',
                        'content' => __('Created', 'cftp_admin'),
                        'sort_default' => true,
                        'hide' => 'phone,tablet',
                    ),
                    array(
                        'sortable' => true,
                        'sort_url' => 'name',
                        'content' => __('Full name', 'cftp_admin'),
                    ),
                    array(
                        'sortable' => true,
                        'sort_url' => 'user',
                        'content' => __('Log in username', 'cftp_admin'),
                        'hide' => 'phone,tablet',
                    ),
                    array(
                        'sortable' => true,
                        'sort_url' => 'email',
                        'content' => __('E-mail', 'cftp_admin'),
                        'hide' => 'phone,tablet',
                    ),
                    array(
                        'content' => __('Uploads', 'cftp_admin'),
                        'hide' => 'phone',
                    ),
                    array(
                        'content' => __('Files: Direct', 'cftp_admin'),
                        'hide' => 'phone',
                    ),
                    array(
                        'content' => __('Files: Groups', 'cftp_admin'),
                        'hide' => 'phone',
                    ),
                    array(
                        'sortable' => true,
                        'sort_url' => 'active',
                        'content' => __('Status', 'cftp_admin'),
                    ),
                    array(
                        'content' => __('Groups on', 'cftp_admin'),
                        'hide' => 'phone',
                    ),
                    array(
                        'content' => __('Notify', 'cftp_admin'),
                        'hide' => 'phone,tablet',
                    ),
                    array(
                        'sortable' => true,
                        'sort_url' => 'max_file_size',
                        'content' => __('Max. upload size', 'cftp_admin'),
                        'hide' => 'phone',
                    ),
                    array(
                        'content' => __('View', 'cftp_admin'),
                        'hide' => 'phone',
                    ),
                    array(
                        'content' => __('Actions', 'cftp_admin'),
                        'hide' => 'phone',
                    ),
                );
                $table->thead($thead_columns);

                $sql->setFetchMode(PDO::FETCH_ASSOC);
                while ($row = $sql->fetch()) {
                    $table->addRow();

                    $client = new \ProjectSend\Classes\Users;
                    $client->get($row["id"]);

                    $count_groups = count($client->groups);

                    // Count OWN and GROUP files
                    $own_files = 0;
                    $groups_files = 0;

                    $found_groups = ($count_groups > 0) ? implode(',', $client->groups) : '';
                    $files_query = "SELECT DISTINCT id, file_id, client_id, group_id FROM " . TABLE_FILES_RELATIONS . " WHERE client_id=:id";
                    if (!empty($found_groups)) {
                        $files_query .= " OR FIND_IN_SET(group_id, :group_id)";
                    }
                    $sql_files = $dbh->prepare($files_query);
                    $sql_files->bindParam(':id', $client->id, PDO::PARAM_INT);
                    if (!empty($found_groups)) {
                        $sql_files->bindParam(':group_id', $found_groups);
                    }

                    $sql_files->execute();
                    $sql_files->setFetchMode(PDO::FETCH_ASSOC);
                    while ($row_files = $sql_files->fetch()) {
                        if (!is_null($row_files['client_id'])) {
                            $own_files++;
                        } else {
                            $groups_files++;
                        }
                    }

                    /* Get active status */
                    $badge_label = ($client->active == 0) ? __('Inactive', 'cftp_admin') : __('Active', 'cftp_admin');
                    $badge_class = ($client->active == 0) ? 'bg-danger' : 'bg-success';

                    /* Actions buttons */
                    if ($own_files + $groups_files > 0) {
                        $files_link = 'manage-files.php?client=' . $client->id;
                        $files_button = 'btn-primary';
                    } else {
                        $files_link = 'javascript:void(0);';
                        $files_button = 'btn-pslight disabled';
                    }

                    if ($count_groups > 0) {
                        $groups_link = 'groups.php?member=' . $client->id;
                        $groups_button = 'btn-primary';
                    } else {
                        $groups_link = 'javascript:void(0);';
                        $groups_button = 'btn-pslight disabled';
                    }

                    // Add the cells to the row
                    $tbody_cells = array(
                        array(
                            'checkbox' => true,
                            'value' => $client->id,
                        ),
                        array(
                            'content' => format_date($client->created_date),
                        ),
                        array(
                            'content' => $client->name,
                        ),
                        array(
                            'content' => $client->username,
                        ),
                        array(
                            'content' => $client->email,
                        ),
                        array(
                            'content' => (!empty($client->files)) ? count($client->files) : null,
                        ),
                        array(
                            'content' => $own_files,
                        ),
                        array(
                            'content' => $groups_files,
                        ),
                        array(
                            'content' => '<span class="badge ' . $badge_class . '">' . $badge_label . '</span>',
                        ),
                        array(
                            'content' => $count_groups,
                        ),
                        array(
                            'content' => ($client->notify_upload == '1') ? __('Yes', 'cftp_admin') : __('No', 'cftp_admin'),
                        ),
                        array(
                            'content' => ($client->max_file_size == '0') ? __('Default', 'cftp_admin') : $client->max_file_size . ' ' . 'MB',
                        ),
                        array(
                            'actions' => true,
                            'content' =>  '<a href="' . $files_link . '" class="btn btn-sm ' . $files_button . '">' . __("Files", "cftp_admin") . '</a>' . "\n" .
                                '<a href="' . $groups_link . '" class="btn btn-sm ' . $groups_button . '">' . __("Groups", "cftp_admin") . '</a>' . "\n" .
                                '<a href="' . CLIENT_VIEW_FILE_LIST_URL . '?client=' . $client->username . '" class="btn btn-primary btn-sm" target="_blank">' . __('As client', 'cftp_admin') . '</a>' . "\n"
                        ),
                        array(
                            'actions' => true,
                            'content' =>  '<a href="clients-edit.php?id=' . $client->id . '" class="btn btn-primary btn-sm"><i class="fa fa-pencil"></i><span class="button_label">' . __('Edit', 'cftp_admin') . '</span></a>' . "\n"
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
   
<div class="row">
    <div class="col-12">
        <?php
            if (!empty($table)) {
                // PAGINATION
                $pagination = new \ProjectSend\Classes\PaginationLayout;
                echo $pagination->make([
                    'link' => 'clients.php',
                    'current' => $pagination_page,
                    'item_count' => $count_for_pagination,
                ]);
            }
        ?>
    </div>
</div>
    
<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
    