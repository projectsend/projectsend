<?php
/**
 * Show the list of activities logged.
 */
$allowed_levels = array(9);
require_once 'bootstrap.php';

$active_nav = 'tools';

$page_title = __('Recent activities log', 'cftp_admin');

$current_url = get_form_action_with_existing_parameters(basename(__FILE__));

// Apply the corresponding action to the selected users.
if (isset($_POST['action']) && $_POST['action'] != 'none') {
    switch ($_POST['action']) {
        case 'delete':
            $selected_actions = $_POST['batch'];
            $delete_ids = implode(',', $selected_actions);

            if (!empty($_POST['batch'])) {
                $statement = $dbh->prepare("DELETE FROM " . TABLE_LOG . " WHERE FIND_IN_SET(id, :delete)");
                $params = array(
                    ':delete' => $delete_ids,
                );
                $statement->execute($params);

                $flash->success(__('The selected activities were deleted.', 'cftp_admin'));
            } else {
                $flash->error(__('Please select at least one activity.', 'cftp_admin'));
            }
            break;
        case 'log_clear':
            $keep = '5,6,7,8,37';
            $statement = $dbh->prepare("DELETE FROM " . TABLE_LOG . " WHERE NOT ( FIND_IN_SET(action, :keep) ) ");
            $params = array(
                ':keep' => $keep,
            );
            $statement->execute($params);

            $flash->success(__('The log was cleared. Only data used for statistics remained. You can delete them manually if you want.', 'cftp_admin'));
            break;
    }

    ps_redirect($current_url);
}

$params = [];

// Get the actually requested items
$cq = "SELECT * FROM " . TABLE_LOG;

/** Add the search terms */
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $cq .= " WHERE (owner_user LIKE :owner OR affected_file_name LIKE :file OR affected_account_name LIKE :account)";
    $next_clause = ' AND';
    $no_results_error = 'search';

    $search_terms = '%' . $_GET['search'] . '%';
    $params[':owner'] = $search_terms;
    $params[':file'] = $search_terms;
    $params[':account'] = $search_terms;
} else {
    $next_clause = ' WHERE';
}

// Add the activities filter
if (isset($_GET['activity']) && $_GET['activity'] != 'all') {
    $cq .= $next_clause . " action=:status";

    $status_filter = $_GET['activity'];
    $params[':status'] = $status_filter;

    $no_results_error = 'filter';
}

/**
 * Add the order.
 * Defaults to order by: id, order: DESC
 */
$cq .= sql_add_order(TABLE_LOG, 'id', 'DESC');

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
        $flash->warning(__('There are no activities recorded.', 'cftp_admin'));
    }
}

// Search + filters bar data
$search_form_action = 'actions-log.php';
$logger = new \ProjectSend\Classes\ActionsLog;
$activities = $logger->getActivitiesReferences();
$filters_form = [
    'action' => 'actions-log.php',
    'items' => [
        'activity' => [
            'current' => (isset($_GET['activity'])) ? $_GET['activity'] : null,
            'placeholder' => [
                'value' => 'all',
                'label' => __('All activities', 'cftp_admin')
            ],
            'options' => $activities,
        ]
    ]
];

// Results count and form actions 
$elements_found_count = $count_for_pagination;
$bulk_actions_items = [
    'none' => __('Select action', 'cftp_admin'),
    'log_download' => __('Download as csv', 'cftp_admin'),
    'delete' => __('Delete selected', 'cftp_admin'),
    'log_clear' => __('Clear entire log', 'cftp_admin'),
];

// Include layout files
include_once ADMIN_VIEWS_DIR . DS . 'header.php';

include_once LAYOUT_DIR . DS . 'search-filters-bar.php';
?>

<form action="<?php echo $current_url; ?>" name="actions_list" method="post" class="form-inline batch_actions">
    <?php addCsrf(); ?>
    <?php include_once LAYOUT_DIR . DS . 'form-counts-actions.php'; ?>

    <div class="row">
        <div class="col-12">
            <?php
            if ($count > 0) {
                // Generate the table using the class.
                $table = new \ProjectSend\Classes\Layout\Table([
                    'id' => 'activities_tbl',
                    'class' => 'footable table',
                    'origin' => __FILE__,
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
                        'content' => __('Date', 'cftp_admin'),
                    ),
                    array(
                        'sortable' => true,
                        'sort_url' => 'owner_id',
                        'content' => __('Author', 'cftp_admin'),
                    ),
                    array(
                        'sortable' => true,
                        'sort_url' => 'action',
                        'content' => __('Activity', 'cftp_admin'),
                        'hide' => 'phone',
                    ),
                    array(
                        'content' => '',
                        'hide' => 'phone',
                    ),
                    array(
                        'content' => '',
                        'hide' => 'phone',
                    ),
                    array(
                        'content' => '',
                        'hide' => 'phone',
                    ),
                );
                $table->thead($thead_columns);

                $sql->setFetchMode(PDO::FETCH_ASSOC);
                while ($log = $sql->fetch()) {

                    $this_action = format_action_log_record($log);

                    $date = format_date($log['timestamp']);

                    $table->addRow();

                    $tbody_cells = array(
                        array(
                            'checkbox' => true,
                            'value' => $log["id"],
                        ),
                        array(
                            'content' => $date,
                        ),
                        array(
                            'content' => (!empty($this_action["part1"])) ? html_output($this_action["part1"]) : '',
                        ),
                        array(
                            'content' => html_output($this_action["action"]),
                        ),
                        array(
                            'content' => (!empty($this_action["part2"])) ? html_output($this_action["part2"]) : '',
                        ),
                        array(
                            'content' => (!empty($this_action["part3"])) ? html_output($this_action["part3"]) : '',
                        ),
                        array(
                            'content' => (!empty($this_action["part4"])) ? html_output($this_action["part4"]) : '',
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
            'link' => 'actions-log.php',
            'current' => $pagination_page,
            'item_count' => $count_for_pagination,
        ]);
    }
?>

<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
