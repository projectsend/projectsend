<?php

use ProjectSend\Classes\Layout\Pagination;
use ProjectSend\Classes\Layout\Table;
use ProjectSend\Classes\Users;

/**
 * Show the list of current clients.
 */

// Configuration: Allowed user levels.
$allowed_levels = [9, 8];

// Bootstrap the application.
require_once 'bootstrap.php';

// Security: Verify user authentication.
log_in_required($allowed_levels);

// Template: Active navigation item.
$active_nav = 'clients';

// Template: Page title.
$page_title = __('Clients Administration', 'cftp_admin');

// Construct the current URL.
$current_url = get_form_action_with_existing_parameters(basename(__FILE__));

// Handle bulk actions.
if (isset($_POST['action'])) {
    // Security: Validate CSRF token.
    if (!validateCsrfToken()) {
        $flash->error(__('Invalid CSRF token.', 'cftp_admin'));
        ps_redirect($current_url);
        exit;
    }

    if (!empty($_POST['batch'])) {
        $selected_clients = $_POST['batch'];

        try {
            switch ($_POST['action']) {
                case 'activate':
                    foreach ($selected_clients as $client_id) {
                        $client = new Users();
                        if ($client->get($client_id)) {
                            $client->setActiveStatus(1);
                        }
                    }
                    $flash->success(__('The selected clients were marked as active.', 'cftp_admin'));
                    break;

                case 'deactivate':
                    foreach ($selected_clients as $client_id) {
                        $client = new Users();
                        if ($client->get($client_id)) {
                            $client->setActiveStatus(0);
                        }
                    }
                    $flash->success(__('The selected clients were marked as inactive.', 'cftp_admin'));
                    break;

                case 'delete':
                    foreach ($selected_clients as $client_id) {
                        $client = new Users();
                        if ($client->get($client_id)) {
                            $client->delete();
                        }
                    }
                    $flash->success(__('The selected clients were deleted.', 'cftp_admin'));
                    break;

                default:
                    $flash->error(__('Invalid action.', 'cftp_admin'));
                    break;
            }
        } catch (Exception $e) {
            $flash->error(__('An error occurred: ', 'cftp_admin') . $e->getMessage());
        }
    } else {
        $flash->error(__('Please select at least one client.', 'cftp_admin'));
    }

    ps_redirect($current_url);
    exit;
}

$params = [];

$cq = "SELECT id FROM " . TABLE_USERS . " WHERE level='0' AND account_requested='0'";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_terms = '%' . $_GET['search'] . '%';
    $cq .= " AND (name LIKE :name OR user LIKE :user OR address LIKE :address OR phone LIKE :phone OR email LIKE :email OR contact LIKE :contact)";
    $params[':name'] = $search_terms;
    $params[':user'] = $search_terms;
    $params[':address'] = $search_terms;
    $params[':phone'] = $search_terms;
    $params[':email'] = $search_terms;
    $params[':contact'] = $search_terms;
    $no_results_error = 'search';
}

if (isset($_GET['active']) && $_GET['active'] != '2') {
    $cq .= " AND active = :active";
    $params[':active'] = (int)$_GET['active'];
    $no_results_error = 'filter';
}

$cq .= sql_add_order(TABLE_USERS, 'id', 'desc');

$count_sql = $dbh->prepare($cq);
$count_sql->execute($params);
$count_for_pagination = $count_sql->rowCount();

$cq .= " LIMIT :limit_start, :limit_number";
$sql = $dbh->prepare($cq);

$pagination_page = (isset($_GET["page"])) ? (int)$_GET["page"] : 1;
$pagination_start = ($pagination_page - 1) * get_option('pagination_results_per_page');
$params[':limit_start'] = (int)$pagination_start;
$params[':limit_number'] = (int)get_option('pagination_results_per_page');

$sql->execute($params);
$count = $sql->rowCount();

if (!$count) {
    $error_message = match ($no_results_error ?? null) {
        'search' => __('Your search keywords returned no results.', 'cftp_admin'),
        'filter' => __('The filters you selected returned no results.', 'cftp_admin'),
        default => __('There are no clients yet.', 'cftp_admin'),
    };

    if (isset($no_results_error)) {
        $flash->error($error_message);
    } else {
        $flash->warning($error_message);
    }
}

$header_action_buttons = [
    [
        'url' => 'clients-add.php',
        'label' => __('Create new', 'cftp_admin'),
    ],
];

// Configure the search form.
$search_form_action = 'clients.php';

// Configure the filters form.
$filters_form = [
    'action' => $current_url,
    'items' => [
        'active' => [
            'current' => $_GET['active'] ?? null,
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

$elements_found_count = $count_for_pagination;

// Configure bulk actions.
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

<form action="<?php echo esc_url($current_url); ?>" name="clients_list" method="post" class="form-inline batch_actions">
    <?php echo addCsrf(); ?>
    <?php include_once LAYOUT_DIR . DS . 'form-counts-actions.php'; ?>

    <div class="row">
        <div class="col-12">
            <?php
            if ($count > 0) {
                // Generate the table using the class.
                $table = new Table([
                    'id' => 'clients_tbl',
                    'class' => 'footable table',
                    'origin' => basename(__FILE__),
                ]);

                $thead_columns = [
                    [
                        'select_all' => true,
                        'attributes' => [
                            'class' => ['td_checkbox'],
                        ],
                    ],
                    [
                        'sortable' => true,
                        'sort_url' => 'timestamp',
                        'content' => __('Created', 'cftp_admin'),
                        'sort_default' => true,
                        'hide' => 'phone,tablet',
                    ],
                    [
                        'sortable' => true,
                        'sort_url' => 'name',
                        'content' => __('Full name', 'cftp_admin'),
                    ],
                    [
                        'sortable' => true,
                        'sort_url' => 'user',
                        'content' => __('Log in username', 'cftp_admin'),
                        'hide' => 'phone,tablet',
                    ],
                    [
                        'sortable' => true,
                        'sort_url' => 'email',
                        'content' => __('E-mail', 'cftp_admin'),
                        'hide' => 'phone,tablet',
                    ],
                    [
                        'content' => __('Uploads', 'cftp_admin'),
                        'hide' => 'phone',
                    ],
                    [
                        'content' => __('Files: Direct', 'cftp_admin'),
                        'hide' => 'phone',
                    ],
                    [
                        'content' => __('Files: Groups', 'cftp_admin'),
                        'hide' => 'phone',
                    ],
                    [
                        'sortable' => true,
                        'sort_url' => 'active',
                        'content' => __('Status', 'cftp_admin'),
                    ],
                    [
                        'content' => __('Groups on', 'cftp_admin'),
                        'hide' => 'phone',
                    ],
                    [
                        'content' => __('Notify', 'cftp_admin'),
                        'hide' => 'phone,tablet',
                    ],
                    [
                        'sortable' => true,
                        'sort_url' => 'max_file_size',
                        'content' => __('Max. upload size', 'cftp_admin'),
                        'hide' => 'phone',
                    ],
                    [
                        'content' => __('View', 'cftp_admin'),
                        'hide' => 'phone',
                    ],
                    [
                        'content' => __('Actions', 'cftp_admin'),
                        'hide' => 'phone',
                    ],
                ];
                $table->thead($thead_columns);

                while ($row = $sql->fetch(PDO::FETCH_ASSOC)) {
                    $table->addRow();

                    $client = new Users($row["id"]);

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
                    while ($row_files = $sql_files->fetch(PDO::FETCH_ASSOC)) {
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
                    $files_link = ($own_files + $groups_files > 0) ? 'manage-files.php?client=' . $client->id : 'javascript:void(0);';
                    $files_button = ($own_files + $groups_files > 0) ? 'btn-primary' : 'btn-pslight disabled';

                    $groups_link = ($count_groups > 0) ? 'groups.php?member=' . $client->id : 'javascript:void(0);';
                    $groups_button = ($count_groups > 0) ? 'btn-primary' : 'btn-pslight disabled';

                    // Add the cells to the row
                    $tbody_cells = [
                        [
                            'checkbox' => true,
                            'value' => $client->id,
                        ],
                        [
                            'content' => format_date($client->created_date),
                        ],
                        [
                            'content' => htmlspecialchars($client->name),
                        ],
                        [
                            'content' => htmlspecialchars($client->username),
                        ],
                        [
                            'content' => htmlspecialchars($client->email),
                        ],
                        [
                            'content' => (!empty($client->files)) ? count($client->files) : null,
                        ],
                        [
                            'content' => $own_files,
                        ],
                        [
                            'content' => $groups_files,
                        ],
                        [
                            'content' => '<span class="badge ' . $badge_class . '">' . $badge_label . '</span>',
                        ],
                        [
                            'content' => $count_groups,
                        ],
                        [
                            'content' => ($client->notify_upload == '1') ? __('Yes', 'cftp_admin') : __('No', 'cftp_admin'),
                        ],
                        [
                            'content' => ($client->max_file_size == '0') ? __('Default', 'cftp_admin') : $client->max_file_size . ' ' . 'MB',
                        ],
                        [
                            'actions' => true,
                            'content' =>  '<a href="' . esc_url($files_link) . '" class="btn btn-sm ' . $files_button . '">' . __("Files", "cftp_admin") . '</a>' . "\n" .
                                '<a href="' . esc_url($groups_link) . '" class="btn btn-sm ' . $groups_button . '">' . __("Groups", "cftp_admin") . '</a>' . "\n" .
                                '<a href="' . CLIENT_VIEW_FILE_LIST_URL . '?client=' . htmlspecialchars($client->username) . '" class="btn btn-primary btn-sm" target="_blank">' . __('As client', 'cftp_admin') . '</a>' . "\n"
                        ],
                        [
                            'actions' => true,
                            'content' =>  '<a href="clients-edit.php?id=' . $client->id . '" class="btn btn-primary btn-sm"><i class="fa fa-pencil"></i><span class="button_label">' . __('Edit', 'cftp_admin') . '</span></a>' . "\n"
                        ],
                    ];

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
    $pagination = new Pagination;
    echo $pagination->make([
        'link' => 'clients.php',
        'current' => $pagination_page,
        'item_count' => $count_for_pagination,
    ]);
}
?>

<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
