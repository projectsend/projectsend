<?php
/**
 * Allows to hide, show or delete the files assigend to the
 * selected client.
 *
 * @package ProjectSend
 */
$allowed_levels = array(9,8,7,0);
require_once 'bootstrap.php';

$active_nav = 'files';

$page_title = __('Manage files','cftp_admin');

$page_id = 'manage_files';

/**
 * Used to distinguish the current page results.
 * Global means all files.
 * Client or group is only when looking into files
 * assigned to any of them.
 */
$results_type = 'global';

/**
 * The client's id is passed on the URI.
 * Then get_client_by_id() gets all the other account values.
 */
if (isset($_GET['client'])) {
    $this_id = $_GET['client'];
    $this_client = get_client_by_id($this_id);
    
    /** Add the name of the client to the page's title. */
    if(!empty($this_client)) {
        $page_title .= ' '.__('for client','cftp_admin').' '.html_entity_decode($this_client['name']);
        $search_on = 'client_id';
        $results_type = 'client';
    }
}

/**
 * The group's id is passed on the URI also.
 */
if (isset($_GET['group'])) {
    $this_id = $_GET['group'];
    $group = get_group_by_id($this_id);

    /** Add the name of the client to the page's title. */
    if(!empty($group['name'])) {
        $page_title .= ' '.__('for group','cftp_admin').' '.html_entity_decode($group['name']);
        $search_on = 'group_id';
        $results_type = 'group';
    }
}

/**
 * Filtering by category
 */
if (isset($_GET['category'])) {
    $this_id = $_GET['category'];
    $this_category = get_category($this_id);
    
    /** Add the name of the client to the page's title. */
    if(!empty($this_category)) {
        $page_title .= ' '.__('on category','cftp_admin').' '.html_entity_decode($this_category['name']);
        $results_type = 'category';
    }
}

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>
<div class="row">
    <div class="col-xs-12">
        <?php
            /**
             * Apply the corresponding action to the selected files.
             */
            if(isset($_GET['action'])) {
                /** Continue only if 1 or more files were selected. */
                if(!empty($_GET['batch'])) {
                    $selected_files = array_map('intval',array_unique($_GET['batch']));
                    switch($_GET['action']) {
                        case 'hide':
                            /**
                             * Changes the value on the "hidden" column value on the database.
                             * This files are not shown on the client's file list. They are
                             * also not counted on the dashboard.php files count when the logged in
                             * account is the client.
                             */
                            foreach ($selected_files as $file_id) {
                                $file = new \ProjectSend\Classes\Files;
                                $file->get($file_id);
                                $file->hide($results_type, $_GET['modify_id']);
                            }
                            $msg = __('The selected files were marked as hidden.','cftp_admin');
                            echo system_message('success',$msg);
                            break;
                        case 'show':
                            foreach ($selected_files as $file_id) {
                                $file = new \ProjectSend\Classes\Files;
                                $file->get($file_id);
                                $file->show($results_type, $_GET['modify_id']);
                            }
                            $msg = __('The selected files were marked as visible.','cftp_admin');
                            echo system_message('success',$msg);
                            break;
                        case 'hide_everyone':
                            foreach ($selected_files as $file_id) {
                                $file = new \ProjectSend\Classes\Files;
                                $file->get($file_id);
                                $file->hideFromEveryone();
                            }
                            $msg = __('The selected files were marked as hidden.','cftp_admin');
                            echo system_message('success',$msg);
                            break;
                        case 'show_everyone':
                            foreach ($selected_files as $file_id) {
                                $file = new \ProjectSend\Classes\Files;
                                $file->get($file_id);
                                $file->showToEveryone();
                            }
                            $msg = __('The selected files were marked as visible.','cftp_admin');
                            echo system_message('success',$msg);
                            break;
                        case 'unassign':
                            /**
                             * Remove the file from this client or group only.
                             */
                            foreach ($selected_files as $file_id) {
                                $file = new \ProjectSend\Classes\Files;
                                $file->get($file_id);
                                $file->removeAssignment($results_type, $_GET['modify_id']);
                            }
                            $msg = __('The selected files were succesfully unassigned.','cftp_admin');
                            echo system_message('success',$msg);
                            break;
                        case 'delete':
                            $delete_results	= array(
                                                    'ok'		=> 0,
                                                    'errors'	=> 0,
                                                );
                            foreach ($selected_files as $index => $file_id) {
                                if (!empty($file_id)) {
                                    $file = new \ProjectSend\Classes\Files;
                                    $file->get($file_id);
                                    if ($file->deleteFiles()) {
                                        $delete_results['ok']++;
                                    }
                                    else {
                                        $delete_results['errors']++;
                                    }
                                }
                            }

                            if ( $delete_results['ok'] > 0 ) {
                                $msg = __('The selected files were deleted.','cftp_admin');
                                echo system_message('success',$msg);
                            }
                            if ( $delete_results['errors'] > 0 ) {
                                $msg = __('Some files could not be deleted.','cftp_admin');
                                echo system_message('danger',$msg);
                            }
                            break;
                        case 'edit':
                            $url = BASE_URI.'files-edit.php?ids='.implode(',', $selected_files);
                            header("Location: ".$url);
                            exit;
                            break;
                    }
                }
                else {
                    $msg = __('Please select at least one file.','cftp_admin');
                    echo system_message('danger',$msg);
                }
            }
            
            /**
             * Global form action
             */
            $query_table_files = true;

            if (isset($search_on)) {
                $params = array();
                $rq = "SELECT * FROM " . TABLE_FILES_RELATIONS . " WHERE $search_on = :id";
                $params[':id'] = $this_id;

                /** Add the status filter */	
                if (isset($_GET['hidden']) && $_GET['hidden'] != 'all') {
                    $set_and = true;
                    $rq .= " AND hidden = :hidden";
                    $no_results_error = 'filter';
                    
                    $params[':hidden'] = $_GET['hidden'];
                }

                /**
                 * Count the files assigned to this client. If there is none, show
                 * an error message.
                 */
                $sql = $dbh->prepare($rq);
                $sql->execute( $params );
                
                if ( $sql->rowCount() > 0) {
                    /**
                     * Get the IDs of files that match the previous query.
                     */
                    $sql->setFetchMode(PDO::FETCH_ASSOC);
                    while( $row_files = $sql->fetch() ) {
                        $files_ids[] = $row_files['file_id'];
                        $gotten_files = implode(',',$files_ids);
                    }
                }
                else {
                    $count = 0;
                    $no_results_error = 'filter';
                    $query_table_files = false;
                }
            }

            if ( $query_table_files === true ) {
                /**
                 * Get the files
                 */
                $params = array();
                
                /**
                 * Add the download count to the main query.
                 * If the page is filtering files by client, then
                 * add the client ID to the subquery.
                 */
                $add_user_to_query = '';
                if ( isset($search_on) && $results_type == 'client' ) {
                    $add_user_to_query = "AND user_id = :user_id";
                    $params[':user_id'] = $this_id;
                }
                $cq = "SELECT files.*, ( SELECT COUNT(file_id) FROM " . TABLE_DOWNLOADS . " WHERE " . TABLE_DOWNLOADS . ".file_id=files.id " . $add_user_to_query . ") as download_count FROM " . TABLE_FILES . " files";
        
                if ( isset($search_on) && !empty($gotten_files) ) {
                    $conditions[] = "FIND_IN_SET(id, :files)";
                    $params[':files'] = $gotten_files;
                }
        
                /** Add the search terms */	
                if(isset($_GET['search']) && !empty($_GET['search'])) {
                    $conditions[] = "(filename LIKE :name OR description LIKE :description)";
                    $no_results_error = 'search';
        
                    $search_terms			= '%'.$_GET['search'].'%';
                    $params[':name']		= $search_terms;
                    $params[':description']	= $search_terms;
                }

                /**
                 * Filter by uploader
                 */	
                if(isset($_GET['uploader']) && !empty($_GET['uploader'])) {
                    $conditions[] = "uploader = :uploader";
                    $no_results_error = 'filter';
        
                    $params[':uploader'] = $_GET['uploader'];
                }


                /**
                 * If the user is an uploader, or a client is editing his files
                 * only show files uploaded by that account.
                */
                if (CURRENT_USER_LEVEL == '7' || CURRENT_USER_LEVEL == '0') {
                    $conditions[] = "uploader = :uploader";
                    $no_results_error = 'account_level';
        
                    $params[':uploader'] = CURRENT_USER_USERNAME;
                }
                
                /**
                 * Add the category filter
                 */
                if ( isset( $results_type ) && $results_type == 'category' ) {
                    $files_id_by_cat = array();
                    $statement = $dbh->prepare("SELECT file_id FROM " . TABLE_CATEGORIES_RELATIONS . " WHERE cat_id = :cat_id");
                    $statement->bindParam(':cat_id', $this_category['id'], PDO::PARAM_INT);
                    $statement->execute();
                    $statement->setFetchMode(PDO::FETCH_ASSOC);
                    while ( $file_data = $statement->fetch() ) {
                        $files_id_by_cat[] = $file_data['file_id'];
                    }
                    $files_id_by_cat = implode(',',$files_id_by_cat);
        
                    /** Overwrite the parameter set previously */
                    $conditions[] = "FIND_IN_SET(id, :files)";
                    $params[':files'] = $files_id_by_cat;
                    
                    $no_results_error = 'category';
                }
        
                /**
                 * Build the final query
                 */
                if ( !empty( $conditions ) ) {
                    foreach ( $conditions as $index => $condition ) {
                        $cq .= ( $index == 0 ) ? ' WHERE ' : ' AND ';
                        $cq .= $condition;
                    }
                }

                /**
                 * Add the order.
                 * Defaults to order by: date, order: ASC
                 */
                $cq .= sql_add_order( TABLE_FILES, 'timestamp', 'desc' );

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
        
                /** Debug query */
                //echo $cq;
                //print_r( $conditions );
            }
            else {
                $count_for_pagination = 0;
            }
        ?>
            <div class="form_actions_left">
                <div class="form_actions_limit_results">
                    <?php show_search_form('manage-files.php'); ?>

                    <?php
                        if( CURRENT_USER_LEVEL != '0' && $results_type == 'global') {
                    ?>
                        <form action="manage-files.php" name="files_filters" method="get" class="form-inline form_filters">
                            <?php form_add_existing_parameters( array('hidden', 'action', 'uploader') ); ?>
                            <div class="form-group group_float">
                                <select name="uploader" id="uploader" class="txtfield form-control">
                                    <?php
                                        $status_options = array(
                                                                '0'		=> __('Uploader','cftp_admin'),
                                                            );
                                        $sql_uploaders = $dbh->prepare("SELECT uploader FROM " . TABLE_FILES . " GROUP BY uploader");
                                        $sql_uploaders->execute();
                                        $sql_uploaders->setFetchMode(PDO::FETCH_ASSOC);

                                        while( $data_uploaders = $sql_uploaders->fetch() ) {
                                            $status_options[$data_uploaders['uploader']] = $data_uploaders['uploader'];
                                        }

                                        foreach ( $status_options as $val => $text ) {
                                    ?>
                                            <option value="<?php echo $val; ?>" <?php if ( isset( $_GET['uploader'] ) && $_GET['uploader'] == $val ) { echo 'selected="selected"'; } ?>><?php echo $text; ?></option>
                                    <?php
                                        }
                                    ?>
                                </select>
                            </div>
                            <button type="submit" id="btn_proceed_filter_clients" class="btn btn-sm btn-default"><?php _e('Filter','cftp_admin'); ?></button>
                        </form>
                    <?php
                        }

                        /** Filters are not available for clients */
                        if (CURRENT_USER_LEVEL != '0' && $results_type != 'global') {
                    ?>
                            <form action="manage-files.php" name="files_filters" method="get" class="form-inline form_filters">
                                <?php form_add_existing_parameters( array('hidden', 'action', 'uploader') ); ?>
                                <div class="form-group group_float">
                                    <select name="hidden" id="hidden" class="txtfield form-control">
                                        <?php
                                            $status_options = array(
                                                '2' => __('All statuses','cftp_admin'),
                                                '0' => __('Visible','cftp_admin'),
                                                '1' => __('Hidden','cftp_admin'),
                                            );

                                            foreach ( $status_options as $val => $text ) {
                                        ?>
                                                <option value="<?php echo $val; ?>" <?php if ( isset( $_GET['hidden'] ) && $_GET['hidden'] == $val ) { echo 'selected="selected"'; } ?>><?php echo $text; ?></option>
                                        <?php
                                            }
                                        ?>
                                    </select>
                                </div>
                                <button type="submit" id="btn_proceed_filter_clients" class="btn btn-sm btn-default"><?php _e('Filter','cftp_admin'); ?></button>
                            </form>
                    <?php
                        }
                    ?>
                </div>
            </div>


            <form action="manage-files.php" name="files_list" method="get" class="form-inline batch_actions">
                <?php form_add_existing_parameters( array( 'modify_id', 'modify_type' ) ); ?>
                <div class="form_actions_right">
                    <div class="form_actions">
                        <div class="form_actions_submit">
                            <div class="form-group group_float">
                                <label class="control-label hidden-xs hidden-sm"><i class="glyphicon glyphicon-check"></i> <?php _e('Selected files actions','cftp_admin'); ?>:</label>
                                <?php
                                    if (isset($search_on)) {
                                ?>
                                    <input type="hidden" name="modify_type" id="modify_type" value="<?php echo $search_on; ?>" />
                                    <input type="hidden" name="modify_id" id="modify_id" value="<?php echo $this_id; ?>" />
                                <?php
                                    }
                                ?>
                                <select name="action" id="action" class="txtfield form-control">
                                    <?php
                                        $actions_options = array(
                                            'none' => __('Select action','cftp_admin'),
                                            'edit' => __('Edit','cftp_admin'),
                                        );

                                        if (CURRENT_USER_LEVEL != '0') {
                                            $actions_options['zip'] = __('Download zipped','cftp_admin');
                                            if (!isset($search_on)) {
                                                $actions_options['hide_everyone'] = __('Set to hidden from everyone already assigned','cftp_admin');
                                                $actions_options['show_everyone'] = __('Set to visible to everyone already assigned','cftp_admin');
                                            }
                                        }

                                        /** Options only available when viewing a client/group files list */
                                        if (CURRENT_USER_LEVEL != '0' && isset($search_on)) {
                                            $actions_options['hide'] = __('Set to hidden','cftp_admin');
                                            $actions_options['show'] = __('Set to visible','cftp_admin');
                                            $actions_options['unassign'] = __('Unassign','cftp_admin');
                                        }
                                        else {
                                            if (CURRENT_USER_LEVEL != '0' || (CURRENT_USER_LEVEL == '0' && get_option('clients_can_delete_own_files') == '1'))
                                            $actions_options['delete'] = __('Delete','cftp_admin');
                                        }

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
                    <p class="form_count_total"><?php _e('Found','cftp_admin'); ?>: <span><?php echo $count_for_pagination; ?> <?php _e('files','cftp_admin'); ?></span></p>
                </div>
        
                <div class="clear"></div>

                <?php
                    if (!$count) {
                        if (isset($no_results_error)) {
                            switch ($no_results_error) {
                                case 'search':
                                    $no_results_message = __('Your search keywords returned no results.','cftp_admin');
                                    break;
                                case 'category':
                                    $no_results_message = __('There are no files assigned to this category.','cftp_admin');
                                    break;
                                case 'filter':
                                    $no_results_message = __('The filters you selected returned no results.','cftp_admin');
                                    break;
                                case 'none_assigned':
                                    $no_results_message = __('There are no files assigned to this client.','cftp_admin');
                                    break;
                                case 'account_level':
                                    $no_results_message = __('You have not uploaded any files for this account.','cftp_admin');
                                    break;
                            }
                        }
                        else {
                            $no_results_message = __('There are no files for this client.','cftp_admin');
                        }
                        echo system_message('danger',$no_results_message);
                    }

                    if ( $count_for_pagination > 0 ) {
                        /**
                         * Generate the table using the class.
                         */
                        $table_attributes	= array(
                                                    'id'		=> 'files_tbl',
                                                    'class'		=> 'footable table',
                                                );
                        $table = new \ProjectSend\Classes\TableGenerate( $table_attributes );
                        
                        /**
                         * Set the conditions to true or false once here to
                         * avoid repetition
                         * They will be used to generate or no certain columns
                         */
                        $conditions = array(
                                            'select_all'		=> true,
                                            'is_not_client'		=> ( CURRENT_USER_LEVEL != '0' ) ? true : false,
                                            'total_downloads'	=> ( CURRENT_USER_LEVEL != '0' && !isset( $search_on ) ) ? true : false,
                                            'is_search_on'		=> ( isset( $search_on ) ) ? true : false,
                                        );
        
                        $thead_columns		= array(
                                                    array(
                                                        'select_all'	=> true,
                                                        'attributes'	=> array(
                                                                                'class'		=> array( 'td_checkbox' ),
                                                                            ),
                                                        'condition'		=> $conditions['select_all'],
                                                    ),
                                                    array(
                                                        'content'		=> __('Preview','cftp_admin'),
                                                        'hide'			=> 'phone,tablet',
                                                    ),
                                                    array(
                                                        'sortable'		=> true,
                                                        'sort_url'		=> 'timestamp',
                                                        'sort_default'	=> true,
                                                        'content'		=> __('Added on','cftp_admin'),
                                                        'hide'			=> 'phone',
                                                    ),
                                                    array(
                                                        'content'		=> __('Type','cftp_admin'),
                                                        'hide'			=> 'phone,tablet',
                                                    ),
                                                    array(
                                                        'sortable'		=> true,
                                                        'sort_url'		=> 'filename',
                                                        'content'		=> __('Title','cftp_admin'),
                                                    ),
                                                    array(
                                                        'sortable'		=> true,
                                                        'sort_url'		=> 'description',
                                                        'content'		=> __('Description','cftp_admin'),
                                                    ),
                                                    array(
                                                        'content'		=> __('Size','cftp_admin'),
                                                    ),
                                                    array(
                                                        'sortable'		=> true,
                                                        'sort_url'		=> 'uploader',
                                                        'content'		=> __('Uploader','cftp_admin'),
                                                        'hide'			=> 'phone,tablet',
                                                        'condition'		=> $conditions['is_not_client'],
                                                    ),
                                                    array(
                                                        'content'		=> __('Assigned','cftp_admin'),
                                                        'hide'			=> 'phone',
                                                        'condition'		=> ( $conditions['is_not_client'] && !$conditions['is_search_on'] ),
                                                    ),
                                                    array(
                                                        'sortable'		=> true,
                                                        'sort_url'		=> 'public_allow',
                                                        'content'		=> __('Public permissions','cftp_admin'),
                                                        'hide'			=> 'phone',
                                                        'condition'		=> $conditions['is_not_client'],
                                                    ),
                                                    array(
                                                        'content'		=> __('Expiry','cftp_admin'),
                                                        'hide'			=> 'phone',
                                                        'condition'		=> $conditions['is_not_client'],
                                                    ),
                                                    array(
                                                        'content'		=> __('Status','cftp_admin'),
                                                        'hide'			=> 'phone',
                                                        'condition'		=> $conditions['is_search_on'],
                                                    ),
                                                    array(
                                                        'sortable'		=> true,
                                                        'sort_url'		=> 'download_count',
                                                        'content'		=> __('Download count','cftp_admin'),
                                                        'hide'			=> 'phone',
                                                        'condition'		=> $conditions['is_search_on'],
                                                    ),
                                                    array(
                                                        'sortable'		=> true,
                                                        'sort_url'		=> 'download_count',
                                                        'content'		=> __('Total downloads','cftp_admin'),
                                                        'hide'			=> 'phone',
                                                        'condition'		=> $conditions['total_downloads'],
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
                            $file = new \ProjectSend\Classes\Files();
                            $file->get($row['id']);
        
                            /**
                             * Visibility is only available when filtering by client or group.
                             */
                            $assignations = get_file_assignations($file->id);

                            $count_assignations = 0;
                            if (!empty($assignations['clients'])) { $count_assignations += count($assignations['clients']); }
                            if (!empty($assignations['groups'])) { $count_assignations += count($assignations['groups']); }

                            switch ($results_type) {
                                case 'client':
                                    $hidden = $assignations['clients'][$this_id]['hidden'];
                                    break;
                                case 'group':
                                    $hidden = $assignations['groups'][$this_id]['hidden'];
                                    break;
                            }

        
                            /** Preview */
                            $preview_cell = '';
                            if ($file->embeddable) {
                                $preview_cell = '<button class="btn btn-warning btn-sm btn-wide get-preview" data-url="'.BASE_URI.'process.php?do=get_preview&file_id='.$file->id.'">'.__('Preview', 'cftp_admin').'</button>';
                            }
                            if ( file_is_image( $file->full_path ) ) {
                                $thumbnail = make_thumbnail( $file->full_path, null, 50, 50 );
                                if ( !empty( $thumbnail['thumbnail']['url'] ) ) {
                                    $preview_cell = '<a href="#" class="get-preview" data-url="'.BASE_URI.'process.php?do=get_preview&file_id='.$file->id.'">
                                        <img src="' . $thumbnail['thumbnail']['url'] . '" class="thumbnail" />
                                    </a>';
                                }
                            }


                            /** Is file assigned? */
                            $assigned_class		= ($count_assignations == 0) ? 'danger' : 'success';
                            $assigned_status	= ($count_assignations == 0) ? __('No','cftp_admin') : __('Yes','cftp_admin');
        
                            /**
                             * Visibility
                             */
                            if ($file->isPublic()) {
                                $visibility_link = '<a href="javascript:void(0);" class="btn btn-primary btn-sm public_link" data-type="file" data-public-url="'.$file->public_url.'">'.__('Download','cftp_admin').'</a>';
                            }
                            else {
                                if ( get_option('enable_landing_for_all_files') == '1' ) {
                                    $visibility_link = '<a href="javascript:void(0);" class="btn btn-default btn-sm public_link" data-type="file" data-public-url="'.$file->public_url.'">'.__('View information','cftp_admin').'</a>';
                                }
                                else {
                                    $visibility_link = '<a href="javascript:void(0);" class="btn btn-default btn-sm disabled" title="">'.__('None','cftp_admin').'</a>';
                                }
                            }
        
                            /**
                             * Expiration
                             */
                            if ($file->expires == '0') {
                                $expires_button	= 'success';
                                $expires_label	= __('Does not expire','cftp_admin');
                            }
                            else {
                                $expires_date = date( get_option('timeformat'), strtotime ($file->expiry_date) );
        
                                if ($file->expired == true) {
                                    $expires_button	= 'danger';
                                    $expires_label	= __('Expired on','cftp_admin') . ' ' . $expires_date;
                                }
                                else {
                                    $expires_button	= 'info';
                                    $expires_label	= __('Expires on','cftp_admin') . ' ' . $expires_date;
                                }
                            }
        
                            /**
                             * Visibility
                             */
                            $status_label = '';
                            $status_class = '';
                            if ( isset( $search_on ) ) {
                                $status_class	= ($hidden == 1) ? 'danger' : 'success';
                                $status_label	= ($hidden == 1) ? __('Hidden','cftp_admin') : __('Visible','cftp_admin');
                            }
        
                            /**
                             * Download count when filtering by group or client
                             */
                            if ( isset( $search_on ) ) {
                                $download_count_content = $row['download_count'] . ' ' . __('times','cftp_admin');
        
                                switch ($results_type) {
                                    case 'client':
                                        break;
                                    case 'group':
                                    case 'category':
                                        $download_count_class	= ( $row['download_count'] > 0 ) ? 'downloaders btn-primary' : 'btn-default disabled';
                                        $download_count_content	= '<a href="' . BASE_URI .'download-information.php?id=' . $file->id .'" class="' . $download_count_class . ' btn btn-sm" title="' . html_output( $row['filename'] ) . '">' . $download_count_content . '</a>';
                                    break;
                                }
                            }
                            
                            /**
                             * Download count and link on the unfiltered files table
                             * (no specific client or group selected)
                             */
                            if ( !isset( $search_on ) ) {
                                if (CURRENT_USER_LEVEL != '0') {
                                    if ( $row["download_count"] > 0 ) {
                                        $btn_class		= 'downloaders btn-primary';
                                    }
                                    else {
                                        $btn_class		= 'btn-default disabled';
                                    }
        
                                    $downloads_table_link = '<a href="' . BASE_URI .'download-information.php?id=' . $file->id .'" class="' . $btn_class .' btn btn-sm" title="' .html_output($row['filename']) .'">' . $row["download_count"] . ' ' . __('downloads','cftp_admin') .'</a>';
                                }
                            }
        
                            /**
                             * Add the cells to the row
                             */
                            $tbody_cells = array(
                                                    array(
                                                        'checkbox'		=> true,
                                                        'value'			=> $file->id,
                                                        'condition'		=> $conditions['select_all'],
                                                    ),
                                                    array(
                                                        'content'		=> $preview_cell,
                                                    ),
                                                    array(
                                                        'content'		=> format_date($file->uploaded_date),
                                                    ),
                                                    array(
                                                        'content'		=> $file->extension,
                                                    ),
                                                    array(
                                                        'attributes'	=> array(
                                                                                'class'		=> array( 'file_name' ),
                                                                            ),
                                                        'content'		=> '<a href="' . $file->download_link . '" target="_blank">' . $file->filename_original . '</a>',
                                                    ),
                                                    array(
                                                        'content'		=> $file->description,
                                                    ),
                                                    array(
                                                        'content'		=> $file->size_formatted,
                                                    ),
                                                    array(
                                                        'content'		=> $file->uploaded_by,
                                                        'condition'		=> $conditions['is_not_client'],
                                                    ),
                                                    array(
                                                        'content'		=> '<span class="label label-' . $assigned_class .'">' . $assigned_status . '</span>',
                                                        'condition'		=> ( $conditions['is_not_client'] && !$conditions['is_search_on'] ),
                                                    ),
                                                    array(
                                                        'attributes'	=> array(
                                                                                'class'		=> array( 'col_visibility' ),
                                                                            ),
                                                        'content'		=> $visibility_link,
                                                        'condition'		=> $conditions['is_not_client'],
                                                    ),
                                                    array(
                                                        'content'		=> '<a href="javascript:void(0);" class="btn btn-' . $expires_button . ' disabled btn-sm" rel="" title="">' . $expires_label . '</a>',
                                                        'condition'		=> $conditions['is_not_client'],
                                                    ),
                                                    array(
                                                        'content'		=> '<span class="label label-' . $status_class .'">' . $status_label . '</span>',
                                                        'condition'		=> $conditions['is_search_on'],
                                                    ),
                                                    array(
                                                        'content'		=> ( !empty( $download_count_content ) ) ? $download_count_content : false,
                                                        'condition'		=> $conditions['is_search_on'],
                                                    ),
                                                    array(
                                                        'content'		=> ( !empty( $downloads_table_link ) ) ? $downloads_table_link : false,
                                                        'condition'		=> $conditions['total_downloads'],
                                                    ),
                                                    array(
                                                        'content'		=> '<a href="files-edit.php?ids=' . $file->id .'" class="btn btn-primary btn-sm"><i class="fa fa-pencil"></i><span class="button_label">' . __('Edit','cftp_admin') . '</span></a>',
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
                            'link' => 'manage-files.php',
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
