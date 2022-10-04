<?php
/**
 * Show the list of current groups.
 */
$allowed_levels = array(9, 8);
require_once 'bootstrap.php';

$active_nav = 'groups';

$page_title = __('Groups administration', 'cftp_admin');

$current_url = get_form_action_with_existing_parameters(basename(__FILE__));

// Used when viewing groups a certain client belongs to
if (!empty($_GET['member'])) {
    $member = get_client_by_id($_GET['member']);
    // Add the name of the client to the page's title
    if ($member != false) {
        $page_title = __('Groups where', 'cftp_admin') . ' ' . html_entity_decode($member['name']) . ' ' . __('is member', 'cftp_admin');
        $member_exists = 1;

        // Get groups where this client is member
        $get_groups = new \ProjectSend\Classes\GroupsMemberships();
        $get_arguments = array(
            'client_id' => $member['id'],
            'return' => 'list',
        );
        $found_groups = $get_groups->getGroupsByClient($get_arguments);
        if (empty($found_groups)) {
            $found_groups = '';
        }
    } else {
        exit_with_error_code(403);
    }
}

// Apply the corresponding action to the selected groups.
if (isset($_POST['action']) && $_POST['action'] != 'none') {
    if (!empty($_POST['batch'])) {
        $selected_groups = $_POST['batch'];

        switch ($_POST['action']) {
            case 'delete':
                $deleted_groups = 0;

                foreach ($selected_groups as $group) {
                    $this_group = new \ProjectSend\Classes\Groups;
                    if ($this_group->get($group)) {
                        $delete_user = $this_group->delete();
                        $deleted_groups++;
                    }
                }

                if ($deleted_groups > 0) {
                    $flash->success(__('The selected groups were deleted.', 'cftp_admin'));
                }
                break;
        }
    } else {
        $flash->error(__('Please select at least one group.', 'cftp_admin'));
    }

    ps_redirect($current_url);
}

// Check if there are public groups but page is disabled
$public_groups = get_groups([
    'public' => true,
]);
if (count($public_groups) > 0) {
    if (get_option('public_listing_page_enable') != 1) {
        $msg = '<i class="fa fa-exclamation-triangle" aria-hidden="true"></i> ' . __('There are public groups, but the public page is disabled.');
        $msg .= ' <a href="' . BASE_URI . 'options.php?section=privacy" class="underline">' . __('Go to privacy options', 'cftp_admin') . '</a>';
        $flash->info($msg);
    }
}

// Query groups
$params = [];
$cq = "SELECT id FROM " . TABLE_GROUPS;

// Add the search terms
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $cq .= " WHERE (name LIKE :name OR description LIKE :description)";
    $next_clause = ' AND';
    $no_results_error = 'search';

    $search_terms = '%' . $_GET['search'] . '%';
    $params[':name'] = $search_terms;
    $params[':description'] = $search_terms;
} else {
    $next_clause = ' WHERE';
}

// Add the member
if (isset($found_groups)) {
    if ($found_groups != '') {
        $cq .= $next_clause . " FIND_IN_SET(id, :groups)";
        $params[':groups'] = $found_groups;
    } else {
        $cq .= $next_clause . " id = NULL";
    }
    $no_results_error = 'is_not_member';
}

// Add the active filter
if (isset($_GET['public']) && $_GET['public'] != '2') {
    $cq .= $next_clause . " public = :public";
    $no_results_error = 'filter';

    $params[':public'] = (int)$_GET['public'];
}

// Add the order
$cq .= sql_add_order(TABLE_GROUPS, 'id', 'desc');

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
            case 'is_not_member':
                $flash->error(__('There are no groups where this client is member.', 'cftp_admin'));
                break;
        }
    } else {
        $flash->error(__('There are no groups created yet.', 'cftp_admin'));
    }
}

// Header buttons
$header_action_buttons = [
    [
        'url' => 'groups-add.php',
        'label' => __('Create new', 'cftp_admin'),
    ],
];

// Search + filters bar data
$search_form_action = 'groups.php';
$filters_form = [
    'action' => $current_url,
    'items' => [
        'public' => [
            'current' => (isset($_GET['public'])) ? $_GET['public'] : null,
            'placeholder' => [
                'value' => '2',
                'label' => __('Visibility', 'cftp_admin')
            ],
            'options' => [
                '1' => __('Public', 'cftp_admin'),
                '0' => __('Private', 'cftp_admin'),
            ],
        ]
    ]
];

// Results count and form actions 
$elements_found_count = $count_for_pagination;
$bulk_actions_items = [
    'none' => __('Select action', 'cftp_admin'),
    'delete' => __('Delete', 'cftp_admin'),
];

// Include layout files
include_once ADMIN_VIEWS_DIR . DS . 'header.php';

include_once LAYOUT_DIR . DS . 'search-filters-bar.php';
?>

<form action="<?php echo $current_url; ?>" name="groups_list" method="post" class="form-inline batch_actions">
    <div class="row">
        <div class="col-12">
            <?php addCsrf(); ?>
            <?php
            if ($count > 0) {
                // Generate the table using the class.
                $table_attributes = array(
                    'id' => 'groups_tbl',
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
                        'hide' => 'phone',
                    ),
                    array(
                        'sortable' => true,
                        'sort_url' => 'name',
                        'content' => __('Group name', 'cftp_admin'),
                    ),
                    array(
                        'sortable' => true,
                        'sort_url' => 'description',
                        'content' => __('Description', 'cftp_admin'),
                        'hide' => 'phone',
                    ),
                    array(
                        'content' => __('Members', 'cftp_admin'),
                    ),
                    array(
                        'content' => __('Files', 'cftp_admin'),
                        'hide' => 'phone',
                    ),
                    array(
                        'sortable' => true,
                        'sort_url' => 'active',
                        'content' => __('Public', 'cftp_admin'),
                    ),
                    array(
                        'sortable' => true,
                        'sort_url' => 'created_by',
                        'content' => __('Created by', 'cftp_admin'),
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

                    $group = new \ProjectSend\Classes\Groups();
                    $group->get($row["id"]);

                    // Button class for the manage files link
                    if (!empty($group->files)) {
                        $files_link = 'manage-files.php?group=' . $group->id;
                        $files_btn = 'btn-primary';
                    } else {
                        $files_link = '#';
                        $files_btn = 'btn-pslight disabled';
                    }

                    // Visibility
                    if ($group->public == '1') {
                        $pre = (get_option('public_listing_page_enable') != 1) ? '<i class="fa fa-exclamation-triangle" aria-hidden="true"></i>' : null;
                        $visibility_link = '<a href="javascript:void(0);" class="btn btn-primary btn-sm public_link" data-title="' . $group->name . '" data-type="group" data-public-url="' . $group->public_url . '">'
                            . $pre . ' ' . __('Public', 'cftp_admin') . '
                                </a>';
                    } else {
                        $visibility_link = '<a href="javascript:void(0);" class="btn btn-pslight btn-sm disabled" title="">'
                            . __('Private', 'cftp_admin') . '
                                </a>';
                    }

                    // Add the cells to the row
                    $tbody_cells = array(
                        array(
                            'checkbox' => true,
                            'value' => $group->id,
                        ),
                        array(
                            'content' => format_date($group->created_date),
                        ),
                        array(
                            'content' => $group->name,
                        ),
                        array(
                            'content' => $group->description,
                        ),
                        array(
                            'content' => (!empty($group->members)) ? count($group->members) : '0',
                        ),
                        array(
                            'content' => (!empty($group->files)) ? count($group->files) : '0',
                        ),
                        array(
                            'content' => $visibility_link,
                        ),
                        array(
                            'content' => $group->created_by,
                        ),
                        array(
                            'actions' => true,
                            'content' => '<a href="' . $files_link . '" class="btn ' . $files_btn . ' btn-sm">' . __('Files', 'cftp_admin') . '</a>',
                        ),
                        array(
                            'actions' => true,
                            'content' => '<a href="groups-edit.php?id=' . $group->id . '" class="btn btn-primary btn-sm"><i class="fa fa-pencil"></i><span class="button_label">' . __('Edit', 'cftp_admin') . '</span></a>' . "\n"
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
                    'link' => 'groups.php',
                    'current' => $pagination_page,
                    'item_count' => $count_for_pagination,
                ]);
            }
        ?>
    </div>
</div>

<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
