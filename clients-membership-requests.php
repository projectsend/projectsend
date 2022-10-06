<?php
/**
 * Show the list of current clients.
 */
$allowed_levels = array(9, 8);
require_once 'bootstrap.php';

$active_nav = 'groups';
$this_page = 'clients-membership-requests.php';

$page_title = __('Membership requests', 'cftp_admin');

$page_id = 'clients_memberships_requests';

// Apply the corresponding action to the selected clients.
if (!empty($_POST)) {
    $location = BASE_URI . $this_page;
    // Continue only if 1 or more clients were selected
    if (!empty($_POST['accounts'])) {
        $selected_clients = $_POST['accounts'];

        $selected_clients_ids = [];
        foreach ($selected_clients as $id => $data) {
            $selected_clients_ids[] = $id;
        }
        $clients_to_get = implode(',', array_map('intval', array_unique($selected_clients_ids)));

        switch ($_POST['action']) {
            case 'apply':
                foreach ($selected_clients as $client) {
                    $process_memberships = new \ProjectSend\Classes\GroupsMemberships;

                    // Process memberships requests
                    if (empty($client['groups'])) {
                        $client['groups'] = [];
                    }

                    $memberships_arguments = array(
                        'client_id' => $client['id'],
                        'approve' => $client['groups'],
                    );

                    $process_requests = $process_memberships->clientProcessMemberships($memberships_arguments, true);
                }
                $flash->success(__('The selected actions were applied.', 'cftp_admin'));
                break;
            case 'delete':
                foreach ($selected_clients as $client) {
                    $process_memberships = new \ProjectSend\Classes\GroupsMemberships;

                    $memberships_arguments = array(
                        'client_id' => $client['id'],
                        'type' => (!empty($_POST['denied']) && $_POST['denied'] == 1) ? 'denied' : 'new',
                    );

                    $delete_requests = $process_memberships->clientDeleteRequests($memberships_arguments);
                }
                $flash->success(__('The selected requests were deleted.', 'cftp_admin'));
                break;
            default:
                break;
        }

        // Redirect after processing
        if (!empty($_POST['denied']) && $_POST['denied'] == 1) {
            $location .= '?denied=1';
        }
    } else {
        $flash->danger(__('Please select at least one client.', 'cftp_admin'));
    }
    ps_redirect($location);
}

// Make a list of active client accounts to include those only on from the membership requests query
$include_user_ids = [];
$statement = $dbh->prepare("SELECT id FROM " . TABLE_USERS . " WHERE level='0' AND active='1' AND account_denied='0'");
$statement->execute();
if ($statement->rowCount() > 0) {
    $statement->setFetchMode(PDO::FETCH_ASSOC);
    while ($row = $statement->fetch()) {
        $include_user_ids[] = $row['id'];
    }
}

// Continue
$params = [];

$cq = "SELECT `client_id`, COUNT(`group_id`) as `amount`, GROUP_CONCAT(`group_id` SEPARATOR ',') AS `groups` FROM " . TABLE_MEMBERS_REQUESTS;

if (isset($_GET['denied']) && !empty($_GET['denied'])) {
    $cq .= " WHERE denied='1'";
    $current_filter = 'denied'; // Which link to highlight
    $found_count = count_memberships_requests_denied();
} else {
    $cq .= " WHERE denied='0'";
    $current_filter = 'new';
    $found_count = count_groups_requests_for_existing_clients();
}

if (!empty($include_user_ids)) {
    $cq .= " AND FIND_IN_SET(`client_id`, :include)";
    $params[':include'] = implode(',', $include_user_ids);
}

$cq .= " GROUP BY client_id";
// Pre-query to count the total results
$count_sql = $dbh->prepare($cq);
$count_sql->execute($params);
$count_for_pagination = ($count_sql->rowCount());

/**
 * Add the order.
 * Defaults to order by: name, order: ASC
 */
$cq .= sql_add_order(TABLE_USERS, 'client_id', 'asc');

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
        $flash->warning(__('There are no requests at the moment', 'cftp_admin'));
    }
}

// Search + filters bar data
$search_form_action = $this_page;
$filters_form = [];

// Filters as links
$filters_links = [
    'new' => [
        'title' => __('New requests', 'cftp_admin'),
        'link' => $this_page,
        'count' => count_groups_requests_for_existing_clients(),
    ],
    'denied' => [
        'title' => __('Denied requests', 'cftp_admin'),
        'link' => $this_page . '?denied=1',
        'count' => count_memberships_requests_denied(),
    ],
];

// Results count and form actions 
$elements_found_count = $found_count;
$bulk_actions_items = [
    'none' => __('Select action', 'cftp_admin'),
    'apply' => __('Apply selection', 'cftp_admin'),
    'delete' => __('Delete requests', 'cftp_admin'),
];

// Include layout files
include_once ADMIN_VIEWS_DIR . DS . 'header.php';

include_once LAYOUT_DIR . DS . 'search-filters-bar.php';
?>
<form action="<?php echo $this_page; ?>" name="requests_list" method="post" class="form-inline batch_actions">
    <div class="row">
        <div class="col-12">
            <?php addCsrf(); ?>
            <?php include_once LAYOUT_DIR . DS . 'form-counts-actions.php'; ?>
            <input type="hidden" name="denied" value="<?php echo (isset($_GET['denied']) && is_numeric($_GET['denied'])) ? $_GET['denied'] : 0; ?>" />

            <?php
                form_add_existing_parameters();

                if ($count > 0) {
                    $all_groups = get_groups([]);

                    // Pre-populate a membership requests array
                    $get_requests = new \ProjectSend\Classes\GroupsMemberships;
                    $arguments = [];
                    if ($current_filter == 'denied') {
                        $arguments['denied'] = 1;
                    }
                    $get_requests = $get_requests->getMembershipRequests($arguments);

                    // Generate the table using the class.
                    $table = new \ProjectSend\Classes\Layout\Table([
                        'id' => 'clients_tbl',
                        'class' => 'footable table',
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
                            'sort_url' => 'name',
                            'sort_default' => true,
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
                            'content' => __('Membership requests', 'cftp_admin'),
                            'hide' => 'phone',
                        ),
                        array(
                            'content' => '',
                            'hide' => 'phone',
                            'attributes' => array(
                                'class' => array('select_buttons'),
                            ),
                        ),
                    );
                    $table->thead($thead_columns);

                    $sql->setFetchMode(PDO::FETCH_ASSOC);

                    // Common attributes for the togglers
                    $toggle_attr = 'data-toggle="toggle" data-style="membership_toggle" data-on="Accept" data-off="Deny" data-onstyle="success" data-offstyle="danger" data-size="mini"';
                    while ($row = $sql->fetch()) {
                        $table->addRow();

                        $client_id = $row["client_id"];

                        $query_client = get_client_by_id($client_id);

                        // Make an array of group membership requests
                        $membership_requests = '';
                        $membership_select = '';

                        // Checkbox on the first column
                        $selectable = '<input name="accounts[' . $client_id . '][id]" value="' . $client_id . '" type="checkbox" class="batch_checkbox" data-clientid="' . $client_id . '">';

                        // Checkboxes for every membership request
                        if (!empty($row['groups'])) {
                            $requests = explode(',', $row['groups']);
                            foreach ($requests as $request) {
                                $this_checkbox = $client_id . '_' . $request;
                                $membership_requests .= '
                                    <div class="request_checkbox">
                                        <label for="' . $this_checkbox . '">
                                            <input ' . $toggle_attr . ' type="checkbox" value="' . $request . '" name="accounts[' . $client_id . '][groups][]' . $request . '" id="' . $this_checkbox . '" class="checkbox_options membership_action checkbox_toggle" data-client="' . $client_id . '" /> ' . $all_groups[$request]['name'] . '
                                        </label>
                                    </div>';
                            }

                            $membership_select = '<a href="#" class="change_all btn btn-pslight btn-xs" data-target="' . $client_id . '" data-check="true">' . __('Accept all', 'cftp_admin') . '</a> ';
                            $membership_select .= '<a href="#" class="change_all btn btn-pslight btn-xs" data-target="' . $client_id . '" data-check="false">' . __('Deny all', 'cftp_admin') . '</a>';
                        }

                        // Add the cells to the row
                        $tbody_cells = array(
                            array(
                                'content' => $selectable,
                                'attributes' => array(
                                    'class' => array('footable-visible', 'footable-first-column'),
                                ),
                            ),
                            array(
                                'content' => html_output($query_client["name"]),
                            ),
                            array(
                                'content' => html_output($query_client["username"]),
                            ),
                            array(
                                'content' => html_output($query_client["email"]),
                            ),
                            array(
                                'content' => $membership_requests,
                            ),
                            array(
                                'content' => $membership_select,
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
            'link' => $this_page,
            'current' => $pagination_page,
            'item_count' => $count_for_pagination,
        ]);
    }
?>
<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
