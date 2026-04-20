<?php
/**
 * Show the list of current users.
 */
require_once 'bootstrap.php';
check_access_enhanced(['edit_users']);

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
                    $this_user = new \ProjectSend\Classes\Users($work_user);
                    if ($this_user->userExists()) {
                        $hide_user = $this_user->setActiveStatus(1);
                    }
                }

                $flash->success(__('The selected users were marked as active.', 'cftp_admin'));
            break;
            case 'deactivate':
                foreach ($selected_users as $work_user) {
                    // A user should not be able to deactivate himself
                    if ($work_user != CURRENT_USER_ID) {
                        $this_user = new \ProjectSend\Classes\Users($work_user);
                        if ($this_user->userExists()) {
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
                $deleted_count = 0;
                $no_permission_count = 0;
                $errors = [];

                foreach ($selected_users as $work_user) {
                    // A user should not be able to delete himself
                    if ($work_user != CURRENT_USER_ID) {
                        $this_user = new \ProjectSend\Classes\Users($work_user);
                        if ($this_user->userExists()) {
                            $result = $this_user->delete();

                            if ($result['status'] === 'success') {
                                $deleted_count++;
                            } else {
                                if (strpos($result['message'], 'permission') !== false) {
                                    $no_permission_count++;
                                } else {
                                    $errors[] = $result['message'];
                                }
                            }
                        }
                    } else {
                        $flash->error(__('You cannot delete your own account.', 'cftp_admin'));
                    }
                }

                if ($deleted_count > 0) {
                    $flash->success(sprintf(__('%d users were deleted.', 'cftp_admin'), $deleted_count));
                }
                if ($no_permission_count > 0) {
                    $flash->warning(sprintf(__('You do not have permission to delete %d users.', 'cftp_admin'), $no_permission_count));
                }
                if (!empty($errors)) {
                    foreach ($errors as $error) {
                        $flash->error($error);
                    }
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

$cq = "SELECT id FROM " . TABLE_USERS . " WHERE role_id != (SELECT id FROM " . TABLE_ROLES . " WHERE name = 'Client')";

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
    $cq .= " AND role_id=:role_id";
    $no_results_error = 'filter';

    $params[':role_id'] = $_GET['role'];
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
        $flash->warning(__('There are no users yet.', 'cftp_admin'));
    }
}

// Header buttons
$header_action_buttons = [];
if (current_user_can('create_users')) {
    $header_action_buttons = [
        [
            'url' => 'users-add.php',
            'label' => __('Create new', 'cftp_admin'),
        ],
    ];
}

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
            'options' => array_column(array_map(function($role) {
                return [$role['id'], $role['name']];
            }, get_available_roles_for_assignment(false)), 1, 0), // Get roles from database (exclude clients)
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
];
if (current_user_can('edit_users')) {
    $bulk_actions_items['activate'] = __('Activate', 'cftp_admin');
    $bulk_actions_items['deactivate'] = __('Deactivate', 'cftp_admin');
}
if (current_user_can('delete_users')) {
    $bulk_actions_items['delete'] = __('Delete', 'cftp_admin');
}

// Include layout files
include_once ADMIN_VIEWS_DIR . DS . 'header.php';

include_once LAYOUT_DIR . DS . 'search-filters-bar.php';
?>
<form action="<?php echo $current_url; ?>" name="users_list" method="post" class="form-inline batch_actions">
    <?php addCsrf(); ?>
    <?php include_once LAYOUT_DIR . DS . 'form-counts-actions.php'; ?>

    <div class="row">
    <div class="col-12">
    <?php
        if ($count > 0) {
            // Generate the table using the class.
            $table = new \ProjectSend\Classes\Layout\Table([
                'id' => 'users_tbl',
                'class' => 'footable table',
                'origin' => basename(__FILE__),
            ]);

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
                    'sortable' => true,
                    'sort_url' => 'max_disk_quota',
                    'content' => __('Disk quota', 'cftp_admin'),
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

                $user = new \ProjectSend\Classes\Users($row["id"]);

                // Get role name from database
                $role_name = $user->getRoleName();

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
                        'content' => (empty($user->max_file_size) || $user->max_file_size == 0) ? '<span class="badge bg-success-subtle text-success">' . __('No limit', 'cftp_admin') . '</span>' : '<span class="badge bg-warning-subtle text-warning">' . $user->max_file_size . ' MB</span>',
                    ),
                    array(
                        'content' => (empty($user->max_disk_quota) || $user->max_disk_quota == 0) ? '<span class="badge bg-success-subtle text-success">' . __('No limit', 'cftp_admin') . '</span>' : '<span class="badge bg-warning-subtle text-warning">' . $user->max_disk_quota . ' MB</span>',
                    ),
                    array(
                        'actions' => true,
                        'content' =>  (current_user_can('edit_users') ? '<a href="users-edit.php?id=' . $user->id . '" class="btn btn-primary btn-sm"><i class="fa fa-pencil"></i><span class="button_label">' . __('Edit', 'cftp_admin') . '</span></a>' : '') . "\n"
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
            'link' => 'users.php',
            'current' => $pagination_page,
            'item_count' => $count_for_pagination,
        ]);
    }
?>

<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
