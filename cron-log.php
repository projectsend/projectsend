<?php
use \Tamtamchik\SimpleFlash\Flash;

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

$page_title = __('Cron execution log','cftp_admin');

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
$current_url = get_form_action_with_existing_parameters(basename(__FILE__));

/**
 * Apply the corresponding action to the selected items.
 */
if (isset($_POST['action']) && $_POST['action'] != 'none') {
    /** Continue only if 1 or more users were selected. */
    switch ($_POST['action']) {
        case 'delete':
            $selected_actions = $_POST['batch'];
            $delete_ids = implode( ',', $selected_actions );

            if ( !empty( $_POST['batch'] ) ) {
                $statement = $dbh->prepare("DELETE FROM " . TABLE_CRON_LOG . " WHERE FIND_IN_SET(id, :delete)");
                $params = array(
                    ':delete' => $delete_ids,
                );
                $statement->execute( $params );

                Flash::success(__('The selected entries were deleted.', 'cftp_admin'));
            }
            else {
                Flash::error(__('Please select at least one entries.', 'cftp_admin'));
            }
        break;
        case 'log_clear':
            $keep = '5,6,7,8,37';
            $statement = $dbh->prepare("DELETE FROM " . TABLE_CRON_LOG);
            $statement->execute( $params );

            Flash::success(__('The log was cleared.', 'cftp_admin'));
        break;
    }

    ps_redirect($current_url);
}
?>
<div class="row">
    <div class="col-xs-12">
    <?php
        $params	= array();

        /**
         * Get the actually requested items
         */
        $cq = "SELECT * FROM " . TABLE_CRON_LOG;

        /** Add the search terms */	
        if ( isset($_GET['search']) && !empty($_GET['search'] ) ) {
            $cq .= " WHERE (sapi LIKE :sapi OR results LIKE :results)";
            $next_clause = ' AND';
            $no_results_error = 'search';
            
            $search_terms		= '%'.$_GET['search'].'%';
            $params[':sapi']	= $search_terms;
            $params[':results']	= $search_terms;
        }
        else {
            $next_clause = ' WHERE';
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
        $pagination_start			= ( $pagination_page - 1 ) * get_option('pagination_results_per_page');
        $params[':limit_start']		= $pagination_start;
        $params[':limit_number']	= get_option('pagination_results_per_page');

        $sql->execute( $params );
        $count = $sql->rowCount();
    ?>

        <div class="form_actions_left">
            <div class="form_actions_limit_results">
                <?php show_search_form('cron-log.php'); ?>
            </div>
        </div>

        <form action="<?php echo $current_url; ?>" name="actions_list" method="post" class="form-inline batch_actions">
            <?php addCsrf(); ?>
            <div class="form_actions_right">
                <div class="form_actions">
                    <div class="form_actions_submit">
                        <div class="form-group group_float">
                            <label class="control-label hidden-xs hidden-sm"><i class="glyphicon glyphicon-check"></i> <?php _e('Actions','cftp_admin'); ?>:</label>
                            <select name="action" id="action" class="form-control">
                                    <?php
                                        $actions_options = array(
                                            'none'				=> __('Select action','cftp_admin'),
                                            'cron_log_download'	=> __('Download as csv','cftp_admin'),
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
                <p><?php echo sprintf(__('Found %d elements','cftp_admin'), (int)$count_for_pagination); ?>
            </div>

            <div class="clear"></div>

            <?php
                if (!$count) {
                    if (isset($no_results_error)) {
                    }
                    else {
                        $no_results_message = __('There are no executions recorded.','cftp_admin');
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

                $thead_columns = array(
                    array(
                        'select_all'	=> true,
                        'attributes'	=> array(
                                                'class' => array( 'td_checkbox' ),
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
                        'sort_url'		=> 'sapi',
                        'content'		=> __('SAPI','cftp_admin'),
                    ),
                    array(
                        'sortable'		=> true,
                        'sort_url'		=> 'results',
                        'content'		=> __('Results','cftp_admin'),
                        'hide'			=> 'phone',
                    ),
                );
                $table->thead( $thead_columns );
                
                $sql->setFetchMode(PDO::FETCH_ASSOC);
                while ( $log = $sql->fetch() ) {
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
                                'content'		=> html_output($log["sapi"]),
                            ),
                        array(
                                'content'		=> html_output( $log["results"] ),
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
                    'link' => 'cron-log.php',
                    'current' => $pagination_page,
                    'item_count' => $count_for_pagination,
                ]);
            ?>
        </form>
        
    </div>
</div>
<?php
    include_once ADMIN_VIEWS_DIR . DS . 'footer.php';