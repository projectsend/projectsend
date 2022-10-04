<?php
/**
 * Show the list of current users.
 */
$allowed_levels = array(9);
require_once 'bootstrap.php';

$active_nav = 'users';

$page_title = __('Users administration', 'cftp_admin');

$current_url = get_form_action_with_existing_parameters(basename(__FILE__));

// Apply the corresponding action to the selected users.
if (isset($_POST['action'])) {
    if (!empty($_POST['batch'])) {
        $selected_users = $_POST['batch'];

        $affected_users = 0;

        switch ($_POST['action']) {
            case 'activate':
                foreach ($selected_users as $work_user) {
                    $this_user = new \ProjectSend\Classes\Users();
                    if ($this_user->get($work_user)) {
                        $hide_user = $this_user->setActiveStatus(1);
                    }
                }

                $flash->success(__('The selected users were marked as active.', 'cftp_admin'));
            break;
            case 'deactivate':
                foreach ($selected_users as $work_user) {
                    // A user should not be able to deactivate himself
                    if ($work_user != CURRENT_USER_ID) {
                        $this_user = new \ProjectSend\Classes\Users;
                        if ($this_user->get($work_user)) {
                            $hide_user = $this_user->setActiveStatus(0);
                        }
                        $affected_users++;
                    } else {
                        $flash->error(__('You cannot deactivate your own account.', 'cftp_admin'));
                    }
                }

                if ($affected_users > 0) {
                    $flash->success(__('The selected users were marked as inactive.', 'cftp_admin'));
                }
            break;
            case 'delete':
                foreach ($selected_users as $work_user) {
                    // A user should not be able to delete himself
                    if ($work_user != CURRENT_USER_ID) {
                        $this_user = new \ProjectSend\Classes\Users();
                        if ($this_user->get($work_user)) {
                            $delete_user = $this_user->delete();
                            $affected_users++;
                        }
                    } else {
                        $flash->error(__('You cannot delete your own account.', 'cftp_admin'));
                    }
                }

                if ($affected_users > 0) {
                    $flash->success(__('The selected users were deleted.', 'cftp_admin'));
                }
            break;
        }
    } else {
        $flash->error(__('Please select at least one user.', 'cftp_admin'));
    }

    ps_redirect($current_url);
}

// Query the users
$params = [];

$cq = "SELECT id FROM " . TABLE_USERS . " WHERE level != '0'";

// Add the search terms
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $cq .= " AND (name LIKE :name OR user LIKE :user OR email LIKE :email)";
    $no_results_error = 'search';

    $search_terms = '%' . $_GET['search'] . '%';
    $params[':name'] = $search_terms;
    $params[':user'] = $search_terms;
    $params[':email'] = $search_terms;
}

// Add the role filter
if (isset($_GET['role']) && $_GET['role'] != 'all') {
    $cq .= " AND level=:level";
    $no_results_error = 'filter';

    $params[':level'] = $_GET['role'];
}

// Add the active filter
if (isset($_GET['active']) && $_GET['active'] != '2') {
    $cq .= " AND active = :active";
    $no_results_error = 'filter';

    $params[':active'] = (int)$_GET['active'];
}

// Add the order.
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
        $flash->error(__('There are no users yet.', 'cftp_admin'));
    }
}

// Header buttons
$header_action_buttons = [
    [
        'url' => 'users-add.php',
        'label' => __('Create new', 'cftp_admin'),
    ],
];

// Search + filters bar data
$search_form_action = 'users.php';
$filters_form = [
    'action' => $current_url,
    'items' => [
        'role' => [
            'current' => (isset($_GET['role'])) ? $_GET['role'] : null,
            'placeholder' => [
                'value' => 'all',
                'label' => __('All roles', 'cftp_admin')
            ],
            'options' => [
                '9' => USER_ROLE_LVL_9,
                '8' => USER_ROLE_LVL_8,
                '7' => USER_ROLE_LVL_7,
            ],
        ],
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
<form action="<?php echo $current_url; ?>" name="users_list" method="post" class="form-inline batch_actions">
    <?php addCsrf(); ?>
    <?php include_once LAYOUT_DIR . DS . 'form-counts-actions.php'; ?>

    <?php
        if ($count > 0) {
            // Generate the table using the class.
            $table_attributes = array(
                'id' => 'users_tbl',
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
                    'sort_default' => true,
                    'content' => __('Created', 'cftp_admin'),
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
                    'hide' => 'phone',
                ),
                array(
                    'sortable' => true,
                    'sort_url' => 'email',
                    'content' => __('E-mail', 'cftp_admin'),
                    'hide' => 'phone',
                ),
                array(
                    'sortable' => true,
                    'sort_url' => 'level',
                    'content' => __('Role', 'cftp_admin'),
                    'hide' => 'phone',
                ),
                array(
                    'sortable' => true,
                    'sort_url' => 'active',
                    'content' => __('Status', 'cftp_admin'),
                ),
                array(
                    'sortable' => true,
                    'sort_url' => 'max_file_size',
                    'content' => __('Max. upload size', 'cftp_admin'),
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

                $user = new \ProjectSend\Classes\Users();
                $user->get($row["id"]);

                // Role name
                switch ($user->role) {
                    case '9':
                        $role_name = USER_ROLE_LVL_9;
                        break;
                    case '8':
                        $role_name = USER_ROLE_LVL_8;
                        break;
                    case '7':
                        $role_name = USER_ROLE_LVL_7;
                        break;
                }

                // Get active status
                $badge_label = ($user->active == 0) ? __('Inactive', 'cftp_admin') : __('Active', 'cftp_admin');
                $badge_class = ($user->active == 0) ? 'danger' : 'success';

                // Add the cells to the row
                // @todo allow deleting first user
                if ($user->id == 1) {
                    $cell = array('content' => '');
                } else {
                    $cell = array(
                        'checkbox' => true,
                        'value' => $user->id,
                    );
                }
                $tbody_cells = array(
                    $cell,
                    array(
                        'content' => format_date($user->created_date),
                    ),
                    array(
                        'content' => $user->name,
                    ),
                    array(
                        'content' => $user->username,
                    ),
                    array(
                        'content' => $user->email,
                    ),
                    array(
                        'content' => $role_name,
                    ),
                    array(
                        'content' => '<span class="badge bg-' . $badge_class . '">' . $badge_label . '</span>',
                    ),
                    array(
                        'content' => ($user->max_file_size == '0') ? __('Default', 'cftp_admin') : $user->max_file_size . ' ' . 'MB',
                    ),
                    array(
                        'actions' => true,
                        'content' =>  '<a href="users-edit.php?id=' . $user->id . '" class="btn btn-primary btn-sm"><i class="fa fa-pencil"></i><span class="button_label">' . __('Edit', 'cftp_admin') . '</span></a>' . "\n"
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
                    'link' => 'users.php',
                    'current' => $pagination_page,
                    'item_count' => $count_for_pagination,
                ]);
            }
        ?>
    </div>
</div>
    
<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
