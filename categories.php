<?php
/**
 * Allows to hide, show or delete the files assigned to the
 * selected client.
 *
 * @package		ProjectSend
 * @subpackage	Files
 */
$allowed_levels = array(9,8,7);
require_once 'bootstrap.php';

$active_nav = 'files';

$page_title = __('Categories administration','cftp_admin');

$page_id = 'categories_list';

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
$current_url = get_form_action_with_existing_parameters(basename(__FILE__));

/**
 * Messages set when adding or editing a category
 */
if ( !empty( $_GET['status'] ) ) {
    $result_status = $_GET['status'];
    switch ( $result_status ) {
        case 'added':
                $msg_text	= __('The category was successfully created.','cftp_admin');
                $msg_type	= 'success';
            break;
        case 'edited':
                $msg_text	= __('The category was successfully edited.','cftp_admin');
                $msg_type	= 'success';
            break;
    }

    echo system_message( $msg_type, $msg_text );
}


/**
 * Apply the corresponding action to the selected categories.
 */
if ( isset( $_POST['action'] ) ) {
    if ( $_POST['action'] != 'none' ) {
        /** Continue only if 1 or more categories were selected. */
        if ( !empty($_POST['batch'] ) ) {
            /**
             * Make a list of categories to avoid individual queries.
             */
            $selected_categories = $_POST['batch'];

            if (count($selected_categories) < 1 ) {
                $flash->error(__('Please select at least one category.', 'cftp_admin'));
            } else {
                switch ($_POST['action']) {
                    case 'delete':
                        foreach ($selected_categories as $category_id) {
                            $category = new \ProjectSend\Classes\Categories();
                            $category->get($category_id);
                            $delete_category = $category->delete();
                        }
                        
                        $flash->success(__('The selected categories were deleted.', 'cftp_admin'));
                    break;
                }
            }

            ps_redirect($current_url);
        }
    }
}

/** Get all the existing categories */
$params = array();

$results_show = 'arranged';
/**
 * Add the search terms
 */
if ( isset( $_GET['search'] ) && !empty( $_GET['search'] ) ) {
    $params['search'] = $_GET['search'];
    $results_show = 'categories'; // show from all categories, not the arranged array
}

$params['page']	= ( isset( $_GET["page"] ) ) ? $_GET["page"] : 1;
$page = $params['page'];

$get_categories = get_categories( $params );
if ( !empty( $get_categories['categories'] ) ) {
    $categories	= $get_categories['categories'];
    $arranged	= $get_categories['arranged'];
}
else {
    $categories	= null;
    $arranged	= null;
}

/**
 * Adding or editing a category
 *
 * By default, the action is ADD category
 */
$form_information = array(
                            'type' => 'create',
                            'title' => __('Create new category','cftp_admin'),
                            'redirect_status' => 'added',
                        );

/** Loading the form in EDIT mode */
if ( (!empty( $_GET['action'] ) && $_GET['action'] == 'edit' ) or !empty( $_POST['editing_id'] )) {
    $action				= 'edit';
    $editing			= !empty( $_POST['editing_id'] ) ? $_POST['editing_id'] : $_GET['id'];
    $form_information	= array(
                                'type'	=> 'edit',
                                'title'	=> __('Edit category','cftp_admin'),
                                'redirect_status' => 'edited',
                            );

    /**
     * Get the current information if just entering edit mode
     */
    $category_name			= $categories[$editing]['name'];
    $category_parent		= $categories[$editing]['parent'];
    $category_description	= $categories[$editing]['description'];
}


/**
 * Process the action
 */
if ( isset( $_POST['btn_process'] ) ) {
    /**
     * Applies for both ADDING a new category as well
     * as editing one but with the form already sent.
     */
    $category_name			= $_POST['category_name'];
    $category_parent		= $_POST['category_parent'];
    $category_description	= $_POST['category_description'];
    
    $category_object = new \ProjectSend\Classes\Categories();

    $arguments = array(
                        'name'			=> $category_name,
                        'parent'		=> $category_parent,
                        'description'	=> $category_description,
                    );
    if ($form_information['type'] == 'edit') {
        $arguments['id'] = ( $_POST ) ? $_POST['editing_id'] : $_GET['id'];
    }
    
    $category_object->set($arguments);
    if ($category_object->validate() ) {
        $method = $form_information['type'];

        $process = $category_object->{$method}( $arguments );
        if ( $process['query'] === 1 ) {
            $redirect = true;
        }
        else {
            $msg = __('There was a problem saving to the database.','cftp_admin');
            echo system_message('danger', $msg);
        }
    }
    else {
        $msg = __('Please complete all the required fields.','cftp_admin');
        echo system_message('danger', $msg);
    }

    /** Redirect so the actions are reflected immediately */
    if ( isset( $redirect ) && $redirect === true ) {
        while (ob_get_level()) ob_end_clean();
        $location = BASE_URI . 'categories.php?status=' . $form_information['redirect_status'];
        header("Location: $location");
        exit;
    }
}
?>

<div class="row">
    <div class="col-xs-12 col-sm-12 col-md-8">
        <div class="form_actions_left">
            <div class="form_actions_limit_results">
                <?php show_search_form('categories.php'); ?>
            </div>
        </div>

        <form action="<?php echo $current_url; ?>" class="form-inline batch_actions" name="selected_categories" id="selected_categories" method="post">
            <?php addCsrf(); ?>
            <div class="form_actions_right form-inline">
                <div class="form_actions">
                    <div class="form_actions_submit">
                        <div class="form-group group_float">
                            <label class="control-label hidden-xs hidden-sm"><i class="glyphicon glyphicon-check"></i> <?php _e('Selected categories actions','cftp_admin'); ?>:</label>
                            <select name="action" id="action" class="txtfield form-control">
                                <?php
                                    $actions_options = array(
                                        'none' => __('Select action','cftp_admin'),
                                        'delete' => __('Delete','cftp_admin'),
                                    );
                                    foreach ( $actions_options as $val => $text ) {
                                ?>
                                        <option value="<?php echo $val; ?>"><?php echo $text; ?></option>
                                <?php
                                    }
                                ?>
                            </select>
                        </div>
                        <button type="submit" name="do_action" id="do_action" class="btn btn-sm btn-default"><?php _e('Proceed','cftp_admin'); ?></button>
                    </div>
                </div>
            </div>

            <div class="clear"></div>

            <div class="form_actions_count">
                <p class="form_count_total"><?php _e('Found','cftp_admin'); ?>: <span><?php echo $get_categories['count']; ?> <?php _e('categories','cftp_admin'); ?></span></p>
            </div>

            <div class="clear"></div>

            <?php
                if ( $get_categories['count'] == 0 ) {
                    if ( !empty( $get_categories['no_results_type'] ) ) {
                        switch ( $get_categories['no_results_type'] ) {
                            case 'search':
                                $no_results_message = __('Your search keywords returned no results.','cftp_admin');
                                break;
                        }
                    }
                    else {
                        $no_results_message = __('There are no categories yet.','cftp_admin');
                    }
                    echo system_message('danger', $no_results_message);
                }

                /**
                 * Generate the table using the class.
                 */
                $table_attributes	= array(
                                            'id'		=> 'categories_tbl',
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
                                                'sort_url'		=> 'name',
                                                'sort_default'	=> true,
                                                'content'		=> __('Name','cftp_admin'),
                                            ),
                                            array(
                                                'content'		=> __('Files','cftp_admin'),
                                            ),
                                            array(
                                                'content'		=> __('Description','cftp_admin'),
                                                'hide'			=> 'phone',
                                            ),
                                            array(
                                                'content'		=> __('View','cftp_admin'),
                                                'hide'			=> 'phone',
                                            ),
                                            array(
                                                'content'		=> __('Actions','cftp_admin'),
                                                'hide'			=> 'phone',
                                            ),
                                        );
                $table->thead( $thead_columns );

                /**
                 * Having the formatting function here seems more convenient
                 * as the HTML layout is easier to edit on it's real context.
                 */
                $c = 0;

                $pagination_page	= $page;
                $pagination_start	= ( $pagination_page - 1 ) * get_option('pagination_results_per_page');
                $limit_start		= $pagination_start;
                $limit_number		= get_option('pagination_results_per_page');
                $limit_amount		= $limit_start + get_option('pagination_results_per_page');

                $i = 0;
                
                function format_category_row( $arranged ) {
                    global $table, $c, $i, $page, $pagination_page, $pagination_start, $limit_start, $limit_number, $limit_amount;

                    $c++;
                    if ( !empty( $arranged ) ) {
                        foreach ( $arranged as $category ) {

                            /**
                             * Horrible hacky way to limit results on the table.
                             * The real filtered results should come from the
                             * 'arranged' array of the get_categories results.
                             */
                            $i++;
                            if  ( $i > $limit_start && $i <= $limit_amount ) {

                                $table->addRow();

                                $depth = ( $category['depth'] > 0 ) ? str_repeat( '&mdash;', $category['depth'] ) . ' ' : false;

                                $total = $category['file_count'];
                                if ( $total > 0 ) {
                                    $class			= 'success';
                                    $files_link 	= 'manage-files.php?category=' . $category['id'];
                                    $files_button	= 'btn-primary';
                                }
                                else {
                                    $class			= 'danger';
                                    $files_link		= 'javascript:void(0);';
                                    $files_button	= 'btn-default disabled';
                                }
                                
                                $count_format = '<span class="label label-' . $class . '">' . $total . '</span>';
                                
                                $tbody_cells = array(
                                                        array(
                                                                'checkbox'		=> true,
                                                                'value'			=> $category["id"],
                                                            ),
                                                        array(
                                                                'content'		=> $depth . html_output($category["name"]),
                                                                'attributes'	=> array(
                                                                                        'data-value'	=> $c,
                                                                                    ),
                                                            ),
                                                        array(
                                                                'content'		=> $count_format,
                                                            ),
                                                        array(
                                                                'content'		=> html_output( $category["description"] ),
                                                            ),
                                                        array(
                                                                'content'		=> '<a href="'. $files_link .'" class="btn btn-sm ' . $files_button . '">' . __('Manage files','cftp_admin') . '</a>',
                                                            ),
                                                        array(
                                                                'content'		=> '<a href="categories.php?action=edit&id=' . $category["id"] .'" class="btn btn-primary btn-sm"><i class="fa fa-pencil"></i><span class="button_label">' . __('Edit','cftp_admin') . '</span></a>'
                                                            ),
                                                    );
                                foreach ( $tbody_cells as $cell ) {
                                    $table->addCell( $cell );
                                }
                                
                                $table->end_row();
                            }

                            $children = $category['children'];
                            if ( !empty( $children ) ) {
                                format_category_row( $children );
                            }
                        }
                    }
                }

                if ( $get_categories['count'] > 0 ) {
                    format_category_row( $get_categories[$results_show] );
                }

                echo $table->render();

                /**
                 * PAGINATION
                 */
                echo $table->pagination([
                    'link' => basename($_SERVER['SCRIPT_FILENAME']),
                    'current' => $params['page'],
                    'item_count' => $get_categories['count'],
                ]);
            ?>
        </form>
    </div>
    <div class="col-xs-12 col-sm-12 col-md-4">
        <?php include_once FORMS_DIR . DS . 'categories.php'; ?>
    </div>
</div>
<?php
    include_once ADMIN_VIEWS_DIR . DS . 'footer.php';