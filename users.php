<?php
/**
 * Show the list of current users.
 *
 * @package		ProjectSend
 * @subpackage	Users
 *
 */
$allowed_levels = array(9);
require_once 'bootstrap.php';

$active_nav = 'users';

$page_title = __('Users administration','cftp_admin');
include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>
<div class="row">
    <div class="col-xs-12">
        <?php
        /**
         * Apply the corresponding action to the selected users.
         */
        if(isset($_GET['action'])) {
            /** Continue only if 1 or more users were selected. */
            if(!empty($_GET['batch'])) {
                $selected_users = $_GET['batch'];

                $affected_users = 0;

                switch($_GET['action']) {
                    case 'activate':
                        /**
                         * Changes the value on the "active" column value on the database.
                         * Inactive users are not allowed to log in.
                         */
                        foreach ($selected_users as $work_user) {
                            $this_user = new \ProjectSend\Classes\Users();
                            if ($this_user->get($work_user)) {
                                $hide_user = $this_user->setActiveStatus(1);
                            }
                        }

                        $msg = __('The selected users were marked as active.','cftp_admin');
                        echo system_message('success',$msg);
                        break;
                    case 'deactivate':
                        /**
                         * Reverse of the previous action. Setting the value to 0 means
                         * that the user is inactive.
                         */
                        foreach ($selected_users as $work_user) {
                            /**
                             * A user should not be able to deactivate himself
                             */
                            if ($work_user != CURRENT_USER_ID) {
                                $this_user = new \ProjectSend\Classes\Users;
                                if ($this_user->get($work_user)) {
                                    $hide_user = $this_user->setActiveStatus(0);
                                }
                                $affected_users++;
                            }
                            else {
                                $msg = __('You cannot deactivate your own account.','cftp_admin');
                                echo system_message('danger',$msg);
                            }
                        }

                        if ($affected_users > 0) {
                            $msg = __('The selected users were marked as inactive.','cftp_admin');
                            echo system_message('success',$msg);
                        }
                        break;
                    case 'delete':		
                        foreach ($selected_users as $work_user) {
                            /**
                             * A user should not be able to delete himself
                             */
                            if ($work_user != CURRENT_USER_ID) {
                                $this_user = new \ProjectSend\Classes\Users();
                                if ($this_user->get($work_user)) {
                                    $delete_user = $this_user->delete();
                                    $affected_users++;
                                }
                            }
                            else {
                                $msg = __('You cannot delete your own account.','cftp_admin');
                                echo system_message('danger',$msg);
                            }
                        }
                        
                        if ($affected_users > 0) {
                            $msg = __('The selected users were deleted.','cftp_admin');
                            echo system_message('success',$msg);
                        }
                    break;
                }
            }
            else {
                $msg = __('Please select at least one user.','cftp_admin');
                echo system_message('danger',$msg);
            }
        }

        $params	= array();

        $cq = "SELECT id FROM " . TABLE_USERS . " WHERE level != '0'";

        /** Add the search terms */	
        if ( isset( $_GET['search'] ) && !empty( $_GET['search'] ) ) {
            $cq .= " AND (name LIKE :name OR user LIKE :user OR email LIKE :email)";
            $no_results_error = 'search';

            $search_terms		= '%'.$_GET['search'].'%';
            $params[':name']	= $search_terms;
            $params[':user']	= $search_terms;
            $params[':email']	= $search_terms;
        }

        /** Add the role filter */	
        if ( isset( $_GET['role'] ) && $_GET['role'] != 'all' ) {
            $cq .= " AND level=:level";
            $no_results_error = 'filter';

            $params[':level']	= $_GET['role'];
        }
        
        /** Add the active filter */	
        if ( isset( $_GET['active'] ) && $_GET['active'] != '2' ) {
            $cq .= " AND active = :active";
            $no_results_error = 'filter';

            $params[':active']	= (int)$_GET['active'];
        }

        /**
         * Add the order.
         */
        $cq .= sql_add_order( TABLE_USERS, 'id', 'desc' );

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
                <?php show_search_form('users.php'); ?>

                <form action="users.php" name="users_filters" method="get" class="form-inline">
                    <?php form_add_existing_parameters( array('active', 'role', 'action') ); ?>
                    <div class="form-group group_float">
                        <select name="role" id="role" class="txtfield form-control">
                            <?php
                                $roles_options = array(
                                                        'all'	=> __('All roles','cftp_admin'),
                                                        '9'		=> USER_ROLE_LVL_9,
                                                        '8'		=> USER_ROLE_LVL_8,
                                                        '7'		=> USER_ROLE_LVL_7,
                                                    );
                                foreach ( $roles_options as $val => $text ) {
                            ?>
                                    <option value="<?php echo $val; ?>" <?php if ( isset( $_GET['role'] ) && $_GET['role'] == $val ) { echo 'selected="selected"'; } ?>><?php echo $text; ?></option>
                            <?php
                                }
                            ?>
                        </select>
                    </div>

                    <div class="form-group group_float">
                        <select name="active" id="active" class="txtfield form-control">
                            <?php
                                $status_options = array(
                                                        '2'		=> __('All statuses','cftp_admin'),
                                                        '1'		=> __('Active','cftp_admin'),
                                                        '0'		=> __('Inactive','cftp_admin'),
                                                    );
                                foreach ( $status_options as $val => $text ) {
                            ?>
                                    <option value="<?php echo $val; ?>" <?php if ( isset( $_GET['active'] ) && $_GET['active'] == $val ) { echo 'selected="selected"'; } ?>><?php echo $text; ?></option>
                            <?php
                                }
                            ?>
                        </select>
                    </div>
                    <button type="submit" id="btn_proceed_filter_clients" class="btn btn-sm btn-default"><?php _e('Filter','cftp_admin'); ?></button>
                </form>
            </div>
        </div>

        <form action="users.php" name="users_list" method="get" class="form-inline batch_actions">
            <?php form_add_existing_parameters(); ?>
            <div class="form_actions_right">
                <div class="form_actions">
                    <div class="form_actions_submit">
                        <div class="form-group group_float">
                            <label class="control-label hidden-xs hidden-sm"><i class="glyphicon glyphicon-check"></i> <?php _e('Selected users actions','cftp_admin'); ?>:</label>
                            <select name="action" id="action" class="txtfield form-control">
                                <?php
                                    $actions_options = array(
                                                            'none'			=> __('Select action','cftp_admin'),
                                                            'activate'		=> __('Activate','cftp_admin'),
                                                            'deactivate'	=> __('Deactivate','cftp_admin'),
                                                            'delete'		=> __('Delete','cftp_admin'),
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
                <p><?php _e('Found','cftp_admin'); ?>: <span><?php echo $count_for_pagination; ?> <?php _e('users','cftp_admin'); ?></span></p>
            </div>

            <div class="clear"></div>

            <?php
                if (!$count) {
                    switch ($no_results_error) {
                        case 'search':
                            $no_results_message = __('Your search keywords returned no results.','cftp_admin');
                            break;
                        case 'filter':
                            $no_results_message = __('The filters you selected returned no results.','cftp_admin');
                            break;
                    }
                    echo system_message('danger',$no_results_message);
                }
                
                if ($count > 0) {
                    /**
                     * Generate the table using the class.
                     */
                    $table_attributes	= array(
                                                'id'		=> 'users_tbl',
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
                                                    'sort_url'		=> 'timestamp',
                                                    'sort_default'	=> true,
                                                    'content'		=> __('Created','cftp_admin'),
                                                    'hide'			=> 'phone,tablet',
                                                ),
                                                array(
                                                    'sortable'		=> true,
                                                    'sort_url'		=> 'name',
                                                    'content'		=> __('Full name','cftp_admin'),
                                                ),
                                                array(
                                                    'sortable'		=> true,
                                                    'sort_url'		=> 'user',
                                                    'content'		=> __('Log in username','cftp_admin'),
                                                    'hide'			=> 'phone',
                                                ),
                                                array(
                                                    'sortable'		=> true,
                                                    'sort_url'		=> 'email',
                                                    'content'		=> __('E-mail','cftp_admin'),
                                                    'hide'			=> 'phone',
                                                ),
                                                array(
                                                    'sortable'		=> true,
                                                    'sort_url'		=> 'level',
                                                    'content'		=> __('Role','cftp_admin'),
                                                    'hide'			=> 'phone',
                                                ),
                                                array(
                                                    'sortable'		=> true,
                                                    'sort_url'		=> 'active',
                                                    'content'		=> __('Status','cftp_admin'),
                                                ),
                                                array(
                                                    'sortable'		=> true,
                                                    'sort_url'		=> 'max_file_size',
                                                    'content'		=> __('Max. upload size','cftp_admin'),
                                                    'hide'			=> 'phone',
                                                ),
                                                array(
                                                    'content'		=> __('Actions','cftp_admin'),
                                                    'hide'			=> 'phone',
                                                ),
                                            );
                    $table->thead( $thead_columns );

                    $sql->setFetchMode(PDO::FETCH_ASSOC);
                    while ( $row = $sql->fetch() ) {
                        $table->addRow();

                        $user = new \ProjectSend\Classes\Users();
                        $user->get($row["id"]);

                        /* Role name */
                        switch( $user->role ) {
                            case '9': $role_name = USER_ROLE_LVL_9; break;
                            case '8': $role_name = USER_ROLE_LVL_8; break;
                            case '7': $role_name = USER_ROLE_LVL_7; break;
                        }

                        /* Get active status */
                        $label = ($user->active == 0) ? __('Inactive','cftp_admin') : __('Active','cftp_admin');
                        $class = ($user->active == 0) ? 'danger' : 'success';
                        
                        /**
                         * Add the cells to the row
                         * @todo allow deleting first user
                         */
                        if ( $user->id == 1 ) {
                            $cell = array( 'content' => '' );
                        }
                        else {
                            $cell = array(
                                        'checkbox'		=> true,
                                        'value'			=> $user->id,
                                        );
                        }
                        $tbody_cells = array(
                                                $cell,
                                                array(
                                                        'content'		=> format_date($user->created_date),
                                                ),
                                                array(
                                                        'content'		=> $user->name,
                                                    ),
                                                array(
                                                        'content'		=> $user->username,
                                                    ),
                                                array(
                                                        'content'		=> $user->email,
                                                    ),
                                                array(
                                                        'content'		=> $role_name,
                                                    ),
                                                array(
                                                        'content'		=> '<span class="label label-' . $class . '">' . $label . '</span>',
                                                    ),
                                                array(
                                                        'content'		=> ( $user->max_file_size == '0' ) ? __('Default','cftp_admin') : $user->max_file_size . ' ' . 'MB',
                                                    ),
                                                array(
                                                        'actions'		=> true,
                                                        'content'		=>  '<a href="users-edit.php?id=' . $user->id . '" class="btn btn-primary btn-sm"><i class="fa fa-pencil"></i><span class="button_label">' . __('Edit','cftp_admin') . '</span></a>' . "\n"
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
                        'link' => 'users.php',
                        'current' => $pagination_page,
                        'item_count' => $count_for_pagination,
                    ]);
                }
            ?>
        </form>
    </div>
</div>
<?php
    include_once ADMIN_VIEWS_DIR . DS . 'footer.php';