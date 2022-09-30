<?php
/*
Template name: Default
URI: http://www.projectsend.org/templates/default
Author: ProjectSend
Author URI: http://www.projectsend.org/
Author e-mail: contact@projectsend.org
Description: The default template uses the same style as the system backend, allowing for a seamless user experience
*/
$ld = 'cftp_template'; // specify the language domain for this template

define('TEMPLATE_RESULTS_PER_PAGE', get_option('pagination_results_per_page'));

if ( !empty( $_GET['category'] ) ) {
    $category_filter = $_GET['category'];
}

include_once ROOT_DIR.'/templates/common.php'; // include the required functions for every template

$window_title = __('File downloads','cftp_template');

$page_id = 'default_template';

$body_class = array('template', 'default-template', 'hide_title');

include_once ADMIN_VIEWS_DIR . DS . 'header.php';

define('TEMPLATE_THUMBNAILS_WIDTH', '50');
define('TEMPLATE_THUMBNAILS_HEIGHT', '50');
?>
<div class="row">
    <div class="col-12">
        <div id="wrapper">
            <div id="right_column">
        
                <div class="form_actions_left">
                    <div class="form_actions_limit_results">
                        <?php show_search_form(); ?>

                        <?php
                            if ( !empty( $cat_ids ) ) {
                        ?>
                                <form action="" name="files_filters" method="get" class="form-inline form_filters">
                                    <?php form_add_existing_parameters( array('category', 'action') ); ?>
                                    <div class="form-group row group_float">
                                        <select class="form-select form-control-short" name="category" id="category">
                                            <option value="0"><?php _e('All categories','cftp_admin'); ?></option>
                                            <?php
                                                $selected_parent = ( isset($category_filter) ) ? array( $category_filter ) : array();
                                                echo generate_categories_options( $get_categories['arranged'], 0, $selected_parent, 'include', $cat_ids );
                                            ?>
                                        </select>
                                    </div>
                                    <button type="submit" id="btn_proceed_filter_files" class="btn btn-sm btn-pslight"><?php _e('Filter','cftp_admin'); ?></button>
                                </form>
                        <?php
                            }
                        ?>
                    </div>
                </div>
            
                <form action="" name="files_list" method="get" class="form-inline batch_actions">
                    <?php form_add_existing_parameters(); ?>
                    <div class="form_actions_right">
                        <div class="form_actions">
                            <div class="form_actions_submit">
                                <div class="form-group row group_float">
                                    <label class="control-label hidden-xs hidden-sm"><i class="glyphicon glyphicon-check"></i> <?php _e('Selected files actions','cftp_admin'); ?>:</label>
                                    <select class="form-select form-control-short" name="action" id="action">
                                        <?php
                                            $actions_options = array(
                                                                    'none'	=> __('Select action','cftp_admin'),
                                                                    'zip'	=> __('Download zipped','cftp_admin'),
                                                                );
                                            foreach ( $actions_options as $val => $text ) {
                                        ?>
                                                <option value="<?php echo $val; ?>"><?php echo $text; ?></option>
                                        <?php
                                            }
                                        ?>
                                    </select>
                                </div>
                                <button type="submit" id="do_action" class="btn btn-sm btn-pslight"><?php _e('Proceed','cftp_admin'); ?></button>
                            </div>
                        </div>
                    </div>
            
                    <div class="right_clear"></div><br />

                    <div class="form_actions_count">
                        <?php $count = (isset($count_for_pagination)) ? $count_for_pagination : 0; ?>
                        <p><?php echo sprintf(__('Found %d elements','cftp_admin'), (int)$count); ?></p>
                    </div>
        
                    <div class="right_clear"></div>
        
                    <?php
                        if (!isset($count_for_pagination)) {
                            if (isset($no_results_error)) {
                                switch ($no_results_error) {
                                    case 'search':
                                        $no_results_message = __('Your search keywords returned no results.','cftp_admin');
                                        break;
                                }
                            }
                            else {
                                $no_results_message = __('There are no files available.','cftp_template');
                            }
                            echo system_message('danger',$no_results_message);
                        }


                        if (isset($count) && $count > 0) {
                            /**
                             * Generate the table using the class.
                             */
                            $table_attributes	= array(
                                                        'id'		=> 'files_list',
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
                                                            'sort_url'		=> 'filename',
                                                            'content'		=> __('Title','cftp_admin'),
                                                        ),
                                                        array(
                                                            'content'		=> __('Type','cftp_admin'),
                                                            'hide'			=> 'phone',
                                                        ),
                                                        array(
                                                            'sortable'		=> true,
                                                            'sort_url'		=> 'description',
                                                            'content'		=> __('Description','cftp_admin'),
                                                            'hide'			=> 'phone',
                                                            'attributes'	=> array(
                                                                                    'class'		=> array( 'description' ),
                                                                                ),
                                                        ),
                                                        array(
                                                            'content'		=> __('Size','cftp_admin'),
                                                            'hide'			=> 'phone',
                                                        ),
                                                        array(
                                                            'sortable'		=> true,
                                                            'sort_url'		=> 'timestamp',
                                                            'sort_default'	=> true,
                                                            'content'		=> __('Date','cftp_admin'),
                                                        ),
                                                        array(
                                                            'content'		=> __('Expiration date','cftp_admin'),
                                                            'hide'			=> 'phone',
                                                        ),
                                                        array(
                                                            'content'		=> __('Preview','cftp_admin'),
                                                            'hide'			=> 'phone,tablet',
                                                        ),
                                                        array(
                                                            'content'		=> __('Download','cftp_admin'),
                                                            'hide'			=> 'phone',
                                                        ),
                                                    );
        
                            $table->thead( $thead_columns );

                            foreach ($available_files as $file_id) {
                                $file = new \ProjectSend\Classes\Files();
                                $file->get($file_id);
        
                                $table->addRow();

                                /**
                                 * Prepare the information to be used later on the cells array
                                 */

                                /** Checkbox */
                                $checkbox = ($file->expired == false) ? '<input type="checkbox" name="files[]" value="' . $file->id . '" class="batch_checkbox" />' : null;

                                /** File title */
                                $file_title_content = '<strong>' . $file->title . '</strong>';
                                if ($file->expired == false) {
                                    $filetitle = '<a href="' . $file->download_link . '" target="_blank">' . $file_title_content . '</a>';
                                }
                                else {
                                    $filetitle = $file_title_content;
                                }
                                
                                /** Extension */
                                $extension_cell = '<span class="badge bg-success label_big">' . $file->extension . '</span>';

                                /** Date */
                                $date = format_date($file->uploaded_date);
                                
                                /** Expiration */
                                if ( $file->expires == '1' ) {
                                    if ( $file->expired == false ) {
                                        $badge_class = 'bg-primary';
                                    } else {
                                        $badge_class = 'bg-danger';
                                    }
                                    
                                    $badge_label = date( get_option('timeformat'), strtotime( $file->expiry_date ) );
                                } else {
                                    $badge_class = 'bg-success';
                                    $badge_label = __('Never','cftp_template');
                                }
                                
                                $expiration_cell = '<span class="badge ' . $badge_class . ' label_big">' . $badge_label . '</span>';

                                /** Thumbnail */
                                $preview_cell = '';
                                if ( $file->expired == false) {
                                    if ( $file->isImage() ) {
                                        $thumbnail = make_thumbnail( $file->full_path, null, TEMPLATE_THUMBNAILS_WIDTH, TEMPLATE_THUMBNAILS_HEIGHT );
                                        if ( !empty( $thumbnail['thumbnail']['url'] ) ) {
                                            $preview_cell = '
                                                <a href="#" class="get-preview" data-url="'.BASE_URI.'process.php?do=get_preview&file_id='.$file->id.'">
                                                    <img src="' . $thumbnail['thumbnail']['url'] . '" class="thumbnail" alt="' . $file->title .'" />
                                                </a>';
                                        }
                                    } else {
                                        if ($file->embeddable) {
                                            $preview_cell = '<button class="btn btn-warning btn-sm btn-wide get-preview" data-url="'.BASE_URI.'process.php?do=get_preview&file_id='.$file->id.'">'.__('Preview', 'cftp_admin').'</button>';
                                        }
                                    }
                                }

                                /** Download */
                                if ($file->expired == true) {
                                    $download_link		= 'javascript:void(0);';
                                    $download_btn_class	= 'btn btn-danger btn-sm disabled';
                                    $download_text		= __('File expired','cftp_template');
                                }
                                else {
                                    $download_btn_class	= 'btn btn-primary btn-sm btn-wide';
                                    $download_text		= __('Download','cftp_template');
                                }
                                $download_cell = '<a href="' . $file->download_link . '" class="' . $download_btn_class . '" target="_blank">' . $download_text . '</a>';


                                
                                $tbody_cells = array(
                                                        array(
                                                            'content'		=> $checkbox,
                                                        ),
                                                        array(
                                                            'content'		=> $filetitle,
                                                            'attributes'	=> array(
                                                                                    'class'		=> array( 'file_name' ),
                                                                                ),
                                                        ),
                                                        array(
                                                            'content'		=> $extension_cell,
                                                            'attributes'	=> array(
                                                                                    'class'		=> array( 'extra' ),
                                                                                ),
                                                        ),
                                                        array(
                                                            'content'		=> $file->description,
                                                            'attributes'	=> array(
                                                                                    'class'		=> array( 'description' ),
                                                                                ),
                                                        ),
                                                        array(
                                                            'content'		=> $file->size_formatted,
                                                        ),
                                                        array(
                                                            'content'		=> $date,
                                                        ),
                                                        array(
                                                            'content'		=> $expiration_cell,
                                                        ),
                                                        array(
                                                            'content'		=> $preview_cell,
                                                            'attributes'	=> array(
                                                                                    'class'		=> array( 'extra' ),
                                                                                ),
                                                        ),
                                                        array(
                                                            'content'		=> $download_cell,
                                                            'attributes'	=> array(
                                                                                    'class'		=> array( 'text-center' ),
                                                                                ),
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
                                'link' => 'my_files/index.php',
                                'current' => $pagination_page,
                                'item_count' => $count_for_pagination,
                                'items_per_page' => TEMPLATE_RESULTS_PER_PAGE,
                            ]);
                        }
                    ?>
                </form>
            
            </div> <!-- right_column -->
        </div> <!-- wrapper -->
    </div> <!-- row -->
</div> <!-- container -->

<?php
    render_footer_text();

    render_json_variables();

    render_assets('js', 'footer');
    render_assets('css', 'footer');

    render_custom_assets('body_bottom');
?>
</body>
</html>
