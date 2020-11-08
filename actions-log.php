<?php
/**
 * Show the list of activities logged.
 *
 * @package		ProjectSend
 * @subpackage	Log
 *
 */
$allowed_levels = array(9);
require_once 'bootstrap.php';

$active_nav = 'tools';

$page_title = __('Recent activities log','cftp_admin');

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>
<div class="row">
    <div class="col-xs-12">
    <?php
        /**
         * Apply the corresponding action to the selected users.
         */
        if (isset($_GET['action']) && $_GET['action'] != 'none') {
            /** Continue only if 1 or more users were selected. */
                switch($_GET['action']) {
                    case 'delete':

                        $selected_actions = $_GET['batch'];
                        $delete_ids = implode( ',', $selected_actions );

                        if ( !empty( $_GET['batch'] ) ) {
                                $statement = $dbh->prepare("DELETE FROM " . TABLE_LOG . " WHERE FIND_IN_SET(id, :delete)");
                                $params = array(
                                                ':delete'	=> $delete_ids,
                                            );
                                $statement->execute( $params );
                            
                                $msg = __('The selected activities were deleted.','cftp_admin');
                                echo system_message('success',$msg);
                        }
                        else {
                            $msg = __('Please select at least one activity.','cftp_admin');
                            echo system_message('danger',$msg);
                        }
                    break;
                    case 'log_clear':
                        $keep = '5,6,7,8,37';
                        $statement = $dbh->prepare("DELETE FROM " . TABLE_LOG . " WHERE NOT ( FIND_IN_SET(action, :keep) ) ");
                        $params = array(
                                        ':keep'	=> $keep,
                                    );
                        $statement->execute( $params );

                        $msg = __('The log was cleared. Only data used for statistics remained. You can delete them manually if you want.','cftp_admin');
                        echo system_message('success',$msg);
                    break;
                }
        }

        $params	= array();

        /**
         * Get the actually requested items
         */
        $cq = "SELECT * FROM " . TABLE_LOG;

        /** Add the search terms */	
        if ( isset($_GET['search']) && !empty($_GET['search'] ) ) {
            $cq .= " WHERE (owner_user LIKE :owner OR affected_file_name LIKE :file OR affected_account_name LIKE :account)";
            $next_clause = ' AND';
            $no_results_error = 'search';
            
            $search_terms		= '%'.$_GET['search'].'%';
            $params[':owner']	= $search_terms;
            $params[':file']	= $search_terms;
            $params[':account']	= $search_terms;
        }
        else {
            $next_clause = ' WHERE';
        }

        /** Add the activities filter */	
        if (isset($_GET['activity']) && $_GET['activity'] != 'all') {
            $cq .= $next_clause. " action=:status";

            $status_filter		= $_GET['activity'];
            $params[':status']	= $status_filter;

            $no_results_error = 'filter';
        }
        
        /**
         * Add the order.
         * Defaults to order by: id, order: DESC
         */
        $cq .= sql_add_order( TABLE_LOG, 'id', 'DESC' );

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
        $pagination_start			= ( $pagination_page - 1 ) * RESULTS_PER_PAGE_LOG;
        $params[':limit_start']		= $pagination_start;
        $params[':limit_number']	= RESULTS_PER_PAGE_LOG;

        $sql->execute( $params );
        $count = $sql->rowCount();
    ?>

        <div class="form_actions_left">
            <div class="form_actions_limit_results">
                <?php show_search_form('actions-log.php'); ?>

                <form action="actions-log.php" name="actions_filters" method="get" class="form-inline form_filters">
                    <?php form_add_existing_parameters( array('activity') ); ?>
                    <div class="form-group group_float">
                        <label for="activity" class="sr-only"><?php _e('Filter activities','cftp_admin'); ?></label>
                        <select name="activity" id="activity" class="form-control">
                            <option value="all"><?php _e('All activities','cftp_admin'); ?></option>
                                <?php
                                    $logger = new \ProjectSend\Classes\ActionsLog;
                                    $activities_references = $logger->getActivitiesReferences();
                                    foreach ($activities_references as $action_number => $name) {
                                ?>
                                        <option value="<?php echo $action_number; ?>" <?php if ( isset( $_GET['activity'] ) && $_GET['activity'] == $action_number ) { echo 'selected="selected"'; } ?>><?php echo $name; ?></option>
                                <?php
                                    }
                                ?>
                        </select>
                    </div>
                    <button type="submit" id="btn_proceed_filter_clients" class="btn btn-sm btn-default"><?php _e('Filter','cftp_admin'); ?></button>
                </form>
            </div>
        </div>

        <form action="actions-log.php" name="actions_list" method="get" class="form-inline batch_actions">
            <?php form_add_existing_parameters(); ?>
            <div class="form_actions_right">
                <div class="form_actions">
                    <div class="form_actions_submit">
                        <div class="form-group group_float">
                            <label class="control-label hidden-xs hidden-sm"><i class="glyphicon glyphicon-check"></i> <?php _e('Activities actions','cftp_admin'); ?>:</label>
                            <select name="action" id="action" class="form-control">
                                    <?php
                                        $actions_options = array(
                                            'none'				=> __('Select action','cftp_admin'),
                                            'log_download'		=> __('Download as csv','cftp_admin'),
                                            'delete'			=> __('Delete selected','cftp_admin'),
                                            'log_clear'			=> __('Clear entire log','cftp_admin'),
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
                <p><?php _e('Found','cftp_admin'); ?>: <span><?php echo $count_for_pagination; ?> <?php _e('activities','cftp_admin'); ?></span></p>
            </div>

            <div class="clear"></div>

            <?php
                if (!$count) {
                    if (isset($no_results_error)) {
                        switch ($no_results_error) {
                            case 'filter':
                                $no_results_message = __('The filters you selected returned no results.','cftp_admin');
                                break;
                        }
                    }
                    else {
                        $no_results_message = __('There are no activities recorded.','cftp_admin');
                    }
                    echo system_message('danger',$no_results_message);
                }
            ?>

            <?php
                /**
                 * Generate the table using the class.
                 */
                $table_attributes	= array(
                                            'id'		=> 'activities_tbl',
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
                                                'content'		=> __('Date','cftp_admin'),
                                            ),
                                            array(
                                                'sortable'		=> true,
                                                'sort_url'		=> 'owner_id',
                                                'content'		=> __('Author','cftp_admin'),
                                            ),
                                            array(
                                                'sortable'		=> true,
                                                'sort_url'		=> 'action',
                                                'content'		=> __('Activity','cftp_admin'),
                                                'hide'			=> 'phone',
                                            ),
                                            array(
                                                'content'		=> '',
                                                'hide'			=> 'phone',
                                            ),
                                            array(
                                                'content'		=> '',
                                                'hide'			=> 'phone',
                                            ),
                                            array(
                                                'content'		=> '',
                                                'hide'			=> 'phone',
                                            ),
                                        );
                $table->thead( $thead_columns );
                
                $sql->setFetchMode(PDO::FETCH_ASSOC);
                while ( $log = $sql->fetch() ) {

                    $this_action = format_action_log_record($log);

                    $date = format_date($log['timestamp']);

                    $table->addRow();
                    
                    $tbody_cells = array(
                                            array(
                                                    'checkbox'		=> true,
                                                    'value'			=> $log["id"],
                                                ),
                                            array(
                                                    'content'		=> $date,
                                                ),
                                            array(
                                                    'content'		=> ( !empty( $this_action["part1"] ) ) ? html_output( $this_action["part1"] ) : '',
                                                ),
                                            array(
                                                    'content'		=> html_output( $this_action["action"] ),
                                                ),
                                            array(
                                                    'content'		=> ( !empty( $this_action["part2"] ) ) ? html_output( $this_action["part2"] ) : '',
                                                ),
                                            array(
                                                    'content'		=> ( !empty( $this_action["part3"] ) ) ? html_output( $this_action["part3"] ) : '',
                                                ),
                                            array(
                                                    'content'		=> ( !empty( $this_action["part4"] ) ) ? html_output( $this_action["part4"] ) : '',
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
                    'link' => 'actions-log.php',
                    'current' => $pagination_page,
                    'pages' => ceil( $count_for_pagination / RESULTS_PER_PAGE_LOG ),
                ]);
            ?>
        </form>
        
    </div>
</div>
<?php
    include_once ADMIN_VIEWS_DIR . DS . 'footer.php';