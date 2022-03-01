<?php
/**
 * Show the list of current clients.
 *
 * @package		ProjectSend
 * @subpackage	Clients
 *
 */
$allowed_levels = array(9,8);
require_once 'bootstrap.php';

$active_nav = 'clients';
$this_page = 'clients-requests.php';

$page_id = 'clients_accounts_requests';

$page_title = __('Account requests','cftp_admin');
include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>
<div class="row">
    <div class="col-xs-12">
    <?php
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'apply':
                    $msg = __('The selected actions were applied.','cftp_admin');
                    echo system_message('success',$msg);
                    break;
                case 'delete':
                    $msg = __('The selected clients were deleted.','cftp_admin');
                    echo system_message('success',$msg);
                    break;
            }
        }

        /**
         * Apply the corresponding action to the selected clients.
         */
        if ( !empty($_POST) ) {
            //print_array($_POST);

            /** Continue only if 1 or more clients were selected. */
            if(!empty($_POST['accounts'])) {
                $selected_clients = $_POST['accounts'];
                
                $selected_clients_ids = array();
                foreach ( $selected_clients as $id => $data ) {
                    $selected_clients_ids[] = $id;
                }
                $clients_to_get = implode( ',', array_map( 'intval', array_unique( $selected_clients_ids ) ) );

                /**
                 * Make a list of users to avoid individual queries.
                 */
                $sql_user = $dbh->prepare( "SELECT id, name FROM " . TABLE_USERS . " WHERE FIND_IN_SET(id, :clients)" );
                $sql_user->bindParam(':clients', $clients_to_get);
                $sql_user->execute();
                $sql_user->setFetchMode(PDO::FETCH_ASSOC);
                while ( $data_user = $sql_user->fetch() ) {
                    $all_users[$data_user['id']] = $data_user['name'];
                }

                switch($_POST['action']) {
                    case 'apply':
                        $selected_clients = $_POST['accounts'];
                        foreach ( $selected_clients as $client ) {
                            $process_memberships	= new \ProjectSend\Classes\MembersActions;

                            /**
                             * 1- Approve or deny account
                             */
                            $process_account = new \ProjectSend\Classes\Users();
                            $process_account->get($client['id']);

                            /** $client['account'] == 1 means approve that account */
                            if ( !empty( $client['account'] ) and $client['account'] == '1' ) {
                                $email_type = 'account_approve';
                                /**
                                 * 1 - Approve account
                                 */
                                $approve = $process_account->accountApprove();
                                /**
                                 * 2 - Prepare memberships information
                                 */
                                if ( empty( $client['groups'] ) ) {
                                    $client['groups'] = array();
                                }

                                $memberships_arguments = array(
                                                                'client_id'	=> $client['id'],
                                                                'approve'	=> $client['groups'],
                                                            );
                            }
                            else {
                                $email_type = 'account_deny';

                                /**
                                 * 1 - Deny account
                                 */
                                $deny = $process_account->accountDeny();
                                /**
                                 * 2 - Deny all memberships
                                 */
                                $memberships_arguments = array(
                                                                'client_id'	=> $client['id'],
                                                                'deny_all'	=> true,
                                                            );
                            }

                            /**
                             * 2 - Process memberships requests
                             */
                            $process_requests	= $process_memberships->group_process_memberships( $memberships_arguments );

                            /**
                             * 3- Send email to the client
                             */
                            /** Send email */
                            $processed_requests = $process_requests['memberships'];
                            $client_information = get_client_by_id( $client['id'] );

                            $notify_client = new \ProjectSend\Classes\Emails;
                            $notify_send = $notify_client->send([
                                'type'			=> $email_type,
                                'username'		=> $client_information['username'],
                                'name'			=> $client_information['name'],
                                'address'		=> $client_information['email'],
                                'memberships'	=> $processed_requests,
                            ]);
                        }
                        break;
                    case 'delete':
                        foreach ($selected_clients as $client) {
                            $this_client = new \ProjectSend\Classes\Users();
                            $this_client->setId($client['id']);
                            $delete_client = $this_client->delete();
                        }
                        break;
                    default:
                        break;
                }

                /** Redirect after processing */
                while (ob_get_level()) ob_end_clean();
                $action_redirect = html_output($_POST['action']);
                $location = BASE_URI . 'clients-requests.php?action=' . $action_redirect;
                if ( !empty( $_POST['denied'] ) && $_POST['denied'] == 1 ) {
                    $location .= '&denied=1';
                }
                header("Location: $location");
                exit;
            }
            else {
                $msg = __('Please select at least one client.','cftp_admin');
                echo system_message('danger',$msg);
            }
        }

        /** Query the clients */
        $params = array();

        $cq = "SELECT * FROM " . TABLE_USERS . " WHERE level='0' AND account_requested='1'";
        
        if ( isset( $_GET['denied'] ) && !empty( $_GET['denied'] ) ) {
            $cq .= " AND account_denied='1'";
            $current_filter = 'denied';  // Which link to highlight
        }
        else {
            $cq .= " AND account_denied='0'";
            $current_filter = 'new';
        }

        /** Add the search terms */	
        if ( isset( $_GET['search'] ) && !empty( $_GET['search'] ) ) {
            $cq .= " AND (name LIKE :name OR user LIKE :user OR address LIKE :address OR phone LIKE :phone OR email LIKE :email OR contact LIKE :contact)";
            $no_results_error = 'search';

            $search_terms		= '%'.$_GET['search'].'%';
            $params[':name']	= $search_terms;
            $params[':user']	= $search_terms;
            $params[':address']	= $search_terms;
            $params[':phone']	= $search_terms;
            $params[':email']	= $search_terms;
            $params[':contact']	= $search_terms;
        }

        /**
         * Add the order.
         * Defaults to order by: name, order: ASC
         */
        $cq .= sql_add_order( TABLE_USERS, 'name', 'asc' );

        /**
         * Pre-query to count the total results
        */
        $count_sql = $dbh->prepare( $cq );
        $count_sql->execute($params);
        $count_for_pagination = $count_sql->rowCount();

        /**
         * Repeat the query but this time, limited by pagination
         */
        $cq .= " LIMIT :limit_start, :limit_number";
        $sql = $dbh->prepare( $cq );

        $pagination_page			= ( isset( $_GET["page"] ) ) ? $_GET["page"] : 1;
        $pagination_start			= ( $pagination_page - 1 ) * get_option('pagination_results_per_page');
        $params[':limit_start']		= $pagination_start;
        $params[':limit_number']	= get_option('pagination_results_per_page');

        $sql->execute( $params );
        $count = $sql->rowCount();
    ?>
            <div class="form_actions_left">
                <div class="form_actions_limit_results">
                    <?php show_search_form($this_page); ?>
                </div>
            </div>

            <form action="<?php echo $this_page; ?>" name="clients_list" method="post" class="form-inline batch_actions">
                <?php addCsrf(); ?>

                <?php form_add_existing_parameters(); ?>
                <div class="form_actions_right">
                    <div class="form_actions">
                        <div class="form_actions_submit">
                            <div class="form-group group_float">
                                <label class="control-label hidden-xs hidden-sm"><i class="glyphicon glyphicon-check"></i> <?php _e('Selected clients actions','cftp_admin'); ?>:</label>
                                <select name="action" id="action" class="txtfield form-control">
                                    <?php
                                        $actions_options = array(
                                            'none' => __('Select action','cftp_admin'),
                                            'apply' => __('Apply selection','cftp_admin'),
                                            'delete' => __('Delete requests','cftp_admin'),
                                        );
                                        foreach ( $actions_options as $val => $text ) {
                                    ?>
                                            <option value="<?php echo $val; ?>"><?php echo $text; ?></option>
                                    <?php
                                        }
                                    ?>
                                </select>
                            </div>
                            <button type="submit" id="do_action" class="btn btn-sm btn-default"><?php _e('Proceed','cftp_admin'); ?></button>
                        </div>
                    </div>
                </div>
                <div class="clear"></div>

                <div class="form_actions_count">
                    <p><?php _e('Found','cftp_admin'); ?>: <span><?php echo $count_for_pagination; ?> <?php _e('requests','cftp_admin'); ?></span></p>
                </div>

                <div class="form_results_filter">
                    <?php
                        $filters = array(
                                        'new' => array(
                                            'title'	=> __('New account requests','cftp_admin'),
                                            'link' => $this_page,
                                            'count'	=> COUNT_CLIENTS_REQUESTS,
                                        ),
                                        'denied' => array(
                                            'title'	=> __('Denied accounts','cftp_admin'),
                                            'link' => $this_page . '?denied=1',
                                            'count'	=> COUNT_CLIENTS_DENIED,
                                        ),
                                    );
                        foreach ( $filters as $type => $filter ) {
                    ?>
                            <a href="<?php echo $filter['link']; ?>" class="<?php echo $current_filter == $type ? 'filter_current' : 'filter_option' ?>"><?php echo $filter['title']; ?> (<?php echo $filter['count']; ?>)</a>
                    <?php
                        }
                    ?>
                </div>

                <div class="clear"></div>

                <?php
                    if (!$count) {
                        if (isset($no_results_error)) {
                            switch ($no_results_error) {
                                case 'search':
                                    $no_results_message = __('Your search keywords returned no results.','cftp_admin');
                                    break;
                                case 'filter':
                                    $no_results_message = __('The filters you selected returned no results.','cftp_admin');
                                    break;
                            }
                        }
                        else {
                            $no_results_message = __('There are no requests at the moment','cftp_admin');
                        }
                        echo system_message('danger',$no_results_message);
                    }

                    if ($count > 0) {
                        /**
                         * Pre-populate a membership requests array
                         */
                        $get_requests	= new \ProjectSend\Classes\MembersActions;
                        $arguments		= array();
                        if ( $current_filter == 'denied' ) {
                            $arguments['denied'] = 1;
                        }
                        $get_requests	= $get_requests->get_membership_requests( $arguments );

                        /**
                         * Generate the table using the class.
                         */
                        $table_attributes	= array(
                                                    'id'		=> 'clients_tbl',
                                                    'class'		=> 'footable table',
                                                );
                        $table = new \ProjectSend\Classes\TableGenerate( $table_attributes );
        
                        $thead_columns		= array(
                                                    array(
                                                        'select_all'	=> true,
                                                        'attributes'	=> array(
                                                                                'class'		=> array( 'td_checkbox' ),
                                                                            ),
                                                    ),
                                                    array(
                                                        'sortable'		=> true,
                                                        'sort_url'		=> 'name',
                                                        'sort_default'	=> true,
                                                        'content'		=> __('Full name','cftp_admin'),
                                                    ),
                                                    array(
                                                        'sortable'		=> true,
                                                        'sort_url'		=> 'user',
                                                        'content'		=> __('Log in username','cftp_admin'),
                                                        'hide'			=> 'phone,tablet',
                                                    ),
                                                    array(
                                                        'sortable'		=> true,
                                                        'sort_url'		=> 'email',
                                                        'content'		=> __('E-mail','cftp_admin'),
                                                        'hide'			=> 'phone,tablet',
                                                    ),
                                                    array(
                                                        'content'		=> __('Account','cftp_admin'),
                                                        'hide'			=> 'phone',
                                                    ),
                                                    array(
                                                        'content'		=> __('Membership requests','cftp_admin'),
                                                        'hide'			=> 'phone',
                                                    ),
                                                    array(
                                                        'content'		=> '',
                                                        'hide'			=> 'phone',
                                                        'attributes'	=> array(
                                                                                'class'		=> array( 'select_buttons' ),
                                                                            ),
                                                    ),
                                                    array(
                                                        'sortable'		=> true,
                                                        'sort_url'		=> 'timestamp',
                                                        'content'		=> __('Added on','cftp_admin'),
                                                        'hide'			=> 'phone,tablet',
                                                    ),
                                                );
                        $table->thead( $thead_columns );
        
                        $sql->setFetchMode(PDO::FETCH_ASSOC);

                        /**
                         * Common attributes for the togglers
                         */
                        $toggle_attr = 'data-toggle="toggle" data-style="membership_toggle" data-on="Accept" data-off="Deny" data-onstyle="success" data-offstyle="danger" data-size="mini"';
                        while ( $row = $sql->fetch() ) {
                            $table->addRow();

                            $client_user	= $row["user"];
                            $client_id		= $row["id"];

                            /**
                             * Get account creation date
                             */
                            $date = format_date($row['timestamp']);
                            
                            /**
                             * Make an array of group membership requests
                             */
                            $membership_requests	= '';
                            $membership_select		= '';

                            /**
                             * Checkbox on the first column
                             */
                            $selectable = '<input name="accounts['.$row['id'].'][id]" value="'.$row['id'].'" type="checkbox" class="batch_checkbox" data-clientid="' . $client_id . '">';

                            /**
                             * Checkbox for the account action
                             */
                            $action_checkbox = '';
                            $account_request = '<div class="request_checkbox">
                                                        <label for="' . $action_checkbox . '">
                                                            <input ' . $toggle_attr . ' type="checkbox" value="1" name="accounts['.$row['id'].'][account]" id="' . $action_checkbox . '" class="checkbox_options account_action checkbox_toggle" data-client="'.$client_id.'" />
                                                        </label>
                                                    </div>';


                            /**
                             * Checkboxes for every membership request
                             */
                            if ( !empty( $get_requests[$row['id']]['requests'] ) ) {
                                foreach ( $get_requests[$row['id']]['requests'] as $request ) {
                                    $this_checkbox = $client_id . '_' . $request['id'];
                                    $membership_requests .= '<div class="request_checkbox">
                                                                <label for="' . $this_checkbox . '">
                                                                    <input ' . $toggle_attr . ' type="checkbox" value="' . $request['id'] . '" name="accounts['.$row['id'].'][groups][]' . $request['id'] . '" id="' . $this_checkbox . '" class="checkbox_options membership_action checkbox_toggle" data-client="'.$client_id.'" /> '. $request['name'] .'
                                                                </label>
                                                            </div>';
                                    
                                    //echo '<input type="hidden" name="accounts['.$row['id'].'][requests][]" value="' . $request['id'] . '">';
                                }
                                
                                $membership_select = '<a href="#" class="change_all btn btn-default btn-xs" data-target="'.$client_id.'" data-check="true">'.__('Accept all','cftp_admin').'</a> 
                                                    <a href="#" class="change_all btn btn-default btn-xs" data-target="'.$client_id.'" data-check="false">'.__('Deny all','cftp_admin').'</a>';
                            }

                            /**
                             * Add the cells to the row
                             */
                            $tbody_cells = array(
                                                    array(
                                                            'content'		=> $selectable,
                                                            'attributes'	=> array(
                                                                                    'class'		=> array( 'footable-visible', 'footable-first-column' ),
                                                                                ),
                                                        ),
                                                    array(
                                                            'content'		=> html_output( $row["name"] ),
                                                        ),
                                                    array(
                                                            'content'		=> html_output( $row["user"] ),
                                                        ),
                                                    array(
                                                            'content'		=> ENCRYPT_PI ? decrypt_output( $row['email'] ) : html_output( llll$row['email'] ),
                                                        ),
                                                    array(
                                                            'content'		=> $account_request,
                                                        ),
                                                    array(
                                                            'content'		=> $membership_requests,
                                                        ),
                                                    array(
                                                            'content'		=> $membership_select,
                                                        ),
                                                    array(
                                                            'content'		=> $date,
                                                        ),
                                                );

                            foreach ( $tbody_cells as $cell ) {
                                $table->addCell( $cell );
                            }
            
                            $table->end_row();
                        }

                        echo $table->render();
        
                        /**
                         * PAGINATION
                         */
                        echo $table->pagination([
                            'link' => $this_page,
                            'current' => $pagination_page,
                            'item_count' => $count_for_pagination,
                        ]);
                    }
                ?>
            </form>
        </div>
    </div>
</div>
<?php
    include_once ADMIN_VIEWS_DIR . DS . 'footer.php‘;
