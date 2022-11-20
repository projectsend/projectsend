<?php
/**
 * Show the list of current clients.
 */
$allowed_levels = array(9, 8);
require_once 'bootstrap.php';

$active_nav = 'clients';
$this_page = 'clients-requests.php';

$page_id = 'clients_accounts_requests';

$page_title = __('Account requests', 'cftp_admin');

// Apply the corresponding action to the selected accounts.
if (!empty($_POST)) {
    // Continue only if 1 or more clients were selected.
    $location = BASE_URI . 'clients-requests.php';

    if (!empty($_POST['accounts'])) {
        $selected_clients = $_POST['accounts'];

        $selected_clients_ids = [];
        foreach ($selected_clients as $id => $data) {
            $selected_clients_ids[] = $id;
        }
        $clients_to_get = implode(',', array_map('intval', array_unique($selected_clients_ids)));

        // Make a list of users to avoid individual queries.
        $sql_user = $dbh->prepare("SELECT id, name FROM " . TABLE_USERS . " WHERE FIND_IN_SET(id, :clients)");
        $sql_user->bindParam(':clients', $clients_to_get);
        $sql_user->execute();
        $sql_user->setFetchMode(PDO::FETCH_ASSOC);
        while ($data_user = $sql_user->fetch()) {
            $all_users[$data_user['id']] = $data_user['name'];
        }

        switch ($_POST['action']) {
            case 'apply':
                $selected_clients = $_POST['accounts'];
                foreach ($selected_clients as $client) {
                    $process_memberships = new \ProjectSend\Classes\GroupsMemberships;

                    // 1- Approve or deny account
                    $process_account = new \ProjectSend\Classes\Users($client['id']);

                    // $client['account'] == 1 means approve that account
                    if (!empty($client['account']) and $client['account'] == '1') {
                        $email_type = 'account_approve';
                        // 1 - Approve account
                        $approve = $process_account->accountApprove();
                        // 2 - Prepare memberships information
                        if (empty($client['groups'])) {
                            $client['groups'] = [];
                        }

                        $memberships_arguments = array(
                            'client_id' => $client['id'],
                            'approve' => $client['groups'],
                        );
                    } else {
                        $email_type = 'account_deny';

                        // 1 - Deny account
                        $deny = $process_account->accountDeny();
                        // 2 - Deny all memberships
                        $memberships_arguments = array(
                            'client_id' => $client['id'],
                            'deny_all' => true,
                        );
                    }

                    // 2 - Process memberships requests
                    $process_requests = $process_memberships->clientProcessMemberships($memberships_arguments);

                    // 3- Send email to the client
                    $processed_requests = $process_requests['memberships'];
                    $client_information = get_client_by_id($client['id']);

                    $notify_client = new \ProjectSend\Classes\Emails;
                    $notify_send = $notify_client->send([
                        'type' => $email_type,
                        'username' => $client_information['username'],
                        'name' => $client_information['name'],
                        'address' => $client_information['email'],
                        'memberships' => $processed_requests,
                    ]);
                }
                $flash->success(__('The selected actions were applied.', 'cftp_admin'));
                break;
            case 'delete':
                foreach ($selected_clients as $client) {
                    $this_client = new \ProjectSend\Classes\Users();
                    $this_client->setId($client['id']);
                    $delete_client = $this_client->delete();
                }
                $flash->success(__('The selected clients were deleted.', 'cftp_admin'));
                break;
            default:
                break;
        }

        // Redirect after processing
        if (!empty($_POST['denied']) && $_POST['denied'] == 1) {
            $location .= '?denied=1';
        }
    } else {
        $flash->error(__('Please select at least one client.', 'cftp_admin'));
    }

    ps_redirect($location);
}

// Query the clients
$params = [];

$cq = "SELECT * FROM " . TABLE_USERS . " WHERE level='0' AND account_requested='1'";

if (isset($_GET['denied']) && !empty($_GET['denied'])) {
    $cq .= " AND account_denied='1'";
    $current_filter = 'denied';  // Which link to highlight
} else {
    $cq .= " AND account_denied='0'";
    $current_filter = 'new';
}

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

/**
 * Add the order.
 * Defaults to order by: name, order: ASC
 */
$cq .= sql_add_order(TABLE_USERS, 'name', 'asc');

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
        $flash->warning(__('There are no requests at the moment.', 'cftp_admin'));
    }
}

// Header buttons
$header_action_buttons = [
    [
        'url' => 'clients-add.php',
        'label' => __('Create client', 'cftp_admin'),
    ],
];

// Search + filters bar data
$search_form_action = $this_page;
$filters_form = [];

// Filters as links
$filters_links = [
    'new' => [
        'title' => __('New account requests', 'cftp_admin'),
        'link' => $this_page,
        'count' => count_account_requests(),
    ],
    'denied' => [
        'title' => __('Denied accounts', 'cftp_admin'),
        'link' => $this_page . '?denied=1',
        'count' => count_account_requests_denied(),
    ],
];

// Results count and form actions 
$elements_found_count = $count_for_pagination;
$bulk_actions_items = [
    'none' => __('Select action', 'cftp_admin'),
    'apply' => __('Apply selection', 'cftp_admin'),
    'delete' => __('Delete requests', 'cftp_admin'),
];

// Include layout files
include_once ADMIN_VIEWS_DIR . DS . 'header.php';

include_once LAYOUT_DIR . DS . 'search-filters-bar.php';
?>
<form action="<?php echo $this_page; ?>" name="clients_list" method="post" class="form-inline batch_actions">
    <?php addCsrf(); ?>
    <?php include_once LAYOUT_DIR . DS . 'form-counts-actions.php'; ?>

    <div class="row">
        <div class="col-12">
            <?php
            if ($count > 0) {
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
                        'content' => __('Account', 'cftp_admin'),
                        'hide' => 'phone',
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
                    array(
                        'sortable' => true,
                        'sort_url' => 'timestamp',
                        'content' => __('Added on', 'cftp_admin'),
                        'hide' => 'phone,tablet',
                    ),
                );
                $table->thead($thead_columns);

                $sql->setFetchMode(PDO::FETCH_ASSOC);

                // Common attributes for the togglers
                $toggle_attr = 'data-toggle="toggle" data-style="membership_toggle" data-on="Accept" data-off="Deny" data-onstyle="success" data-offstyle="danger" data-size="mini"';
                while ($row = $sql->fetch()) {
                    $table->addRow();

                    $client_user = $row["user"];
                    $client_id = $row["id"];

                    // Make an array of group membership requests
                    $membership_requests = '';
                    $membership_select = '';

                    // Checkbox on the first column
                    $selectable = '<input name="accounts[' . $row['id'] . '][id]" value="' . $row['id'] . '" type="checkbox" class="batch_checkbox" data-clientid="' . $client_id . '">';

                    // Checkbox for the account action
                    $action_checkbox = '';
                    $account_request = '<div class="request_checkbox">
                                                        <label for="' . $action_checkbox . '">
                                                            <input ' . $toggle_attr . ' type="checkbox" value="1" name="accounts[' . $row['id'] . '][account]" id="' . $action_checkbox . '" class="checkbox_options account_action checkbox_toggle" data-client="' . $client_id . '" />
                                                        </label>
                                                    </div>';


                    // Checkboxes for every membership request
                    if (!empty($get_requests[$row['id']]['requests'])) {
                        foreach ($get_requests[$row['id']]['requests'] as $request) {
                            $this_checkbox = $client_id . '_' . $request['id'];
                            $membership_requests .= '<div class="request_checkbox">
                                                                <label for="' . $this_checkbox . '">
                                                                    <input ' . $toggle_attr . ' type="checkbox" value="' . $request['id'] . '" name="accounts[' . $row['id'] . '][groups][]' . $request['id'] . '" id="' . $this_checkbox . '" class="checkbox_options membership_action checkbox_toggle" data-client="' . $client_id . '" /> ' . $request['name'] . '
                                                                </label>
                                                            </div>';

                            //echo '<input type="hidden" name="accounts['.$row['id'].'][requests][]" value="' . $request['id'] . '">';
                        }

                        $membership_select = '<a href="#" class="change_all btn btn-pslight btn-sm" data-target="' . $client_id . '" data-check="true">' . __('Accept all', 'cftp_admin') . '</a> 
                                                    <a href="#" class="change_all btn btn-pslight btn-sm" data-target="' . $client_id . '" data-check="false">' . __('Deny all', 'cftp_admin') . '</a>';
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
                            'content' => html_output($row["name"]),
                        ),
                        array(
                            'content' => html_output($row["user"]),
                        ),
                        array(
                            'content' => html_output($row["email"]),
                        ),
                        array(
                            'content' => $account_request,
                        ),
                        array(
                            'content' => $membership_requests,
                        ),
                        array(
                            'content' => $membership_select,
                        ),
                        array(
                            'content' => format_date($row['timestamp']),
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
