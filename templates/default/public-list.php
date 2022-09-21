<?php
$ld = 'cftp_template';

include_once ADMIN_VIEWS_DIR . DS . 'header-unlogged.php';
?>
<div class="row">
    <div class="col-xs-12">
        <div class="form_actions_left">
            <div class="form_actions_limit_results">
                <?php show_search_form(); ?>

                <?php
                    $groups = get_groups([
                        'public' => true,
                    ]);
                    if ( !empty( $groups ) ) {
                ?>
                        <form action="" name="group_filter" method="get" class="form-inline form_filters">
                            <!-- <input type="hidden" name="token" value="<?php echo htmlentities($_GET['token']); ?>"> -->
                            <?php form_add_existing_parameters( array('group') ); ?>
                            <div class="form-group group_float">
                                <select name="group" id="group" class="txtfield form-control">
                                    <option value="0"><?php _e('Not in group','cftp_admin'); ?></option>
                                    <optgroup><?php _e('Groups','cftp_admin'); ?></optgroup>
                                    <?php
                                        foreach ($groups as $group) {
                                    ?>
                                            <option value="<?php echo $group['id']; ?>" data-token="<?php echo $group['public_token']; ?>" <?php if (isset($_GET['group']) && (int)$_GET['group'] == $group['id']) { echo 'selected'; } ?>><?php echo $group['name']; ?></option>
                                    <?php
                                        }
                                    ?>
                                </select>
                            </div>
                            <button type="submit" id="btn_proceed_filter_files" class="btn btn-sm btn-default"><?php _e('Go','cftp_admin'); ?></button>
                        </form>
                <?php
                    }
                ?>
            </div>
        </div>
        <div class="form_actions_right">
        </div>

        <div class="right_clear"></div><br />
    </div>
</div>

<form action="" name="files_list" method="get" class="form-inline batch_actions">
    <div class="row">
        <div class="col-xs-12">
            <?php form_add_existing_parameters(); ?>

            <div class="form_actions_count">
                <?php $count = $files['pagination']['total']; ?>
                <p><?php echo sprintf(__('Found %d elements','cftp_admin'), (int)$count); ?></p>
            </div>
        </div>
    </div>

    <?php if ($mode == 'group' && !empty($group_props['description'])) { ?>
        <div class="row">
            <div class="col-xs-12">
                <div class="group_description">
                    <h3><?php _e('About this group','cftp_admin'); ?></h3>
                    <?php echo htmlentities_allowed($group_props['description']); ?>
                </div>
            </div>
        </div>
    <?php } ?>

    <div class="row">
        <div class="col-xs-12">
            <?php
                if ($count == 0) {
                    if (isset($_GET['search'])) {
                        $no_results_message = __('Your search keywords returned no results.','cftp_admin');
                    }
                    else {
                        $no_results_message = __('There are no files available.','cftp_template');
                    }
                    echo system_message('danger',$no_results_message);
                }


                if ($count > 0) {
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
                                                    'condition'     => (get_option('public_listing_enable_preview') == 1),
                                                ),
                                                array(
                                                    'content'		=> __('Download','cftp_admin'),
                                                    'hide'			=> 'phone',
                                                ),
                                            );

                    $table->thead( $thead_columns );

                    foreach ($files['files_ids'] as $file_id) {
                        $file = new \ProjectSend\Classes\Files();
                        $file->get($file_id);

                        $table->addRow();

                        /** File title */
                        $filetitle = '<strong>' . $file->title . '</strong>';
                        
                        /** Extension */
                        $extension_cell = '<span class="label label-success label_big">' . $file->extension . '</span>';

                        /** Date */
                        $date = format_date($file->uploaded_date);
                        
                        /** Expiration */
                        if ( $file->expires == '1' ) {
                            if ( $file->expired == false ) {
                                $class = 'primary';
                            } else {
                                $class = 'danger';
                            }
                            
                            $value = date( get_option('timeformat'), strtotime( $file->expiry_date ) );
                        } else {
                            $class = 'success';
                            $value = __('Never','cftp_template');
                        }
                        
                        $expiration_cell = '<span class="label label-' . $class . ' label_big">' . $value . '</span>';

                        /** Thumbnail */
                        $preview_cell = '';
                        if ( $file->expired == false) {
                            if ( $file->isImage() ) {
                                $thumbnail = make_thumbnail( $file->full_path, null, 50, 50 );
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
                            $download_btn_class	= 'btn btn-primary btn-sm';
                            $download_text		= __('Download','cftp_template');
                        }

                        $download_cell = '';
                        if (get_option('public_listing_use_download_link') == 1 && $file->isPublic()) {
                            $download_cell = '<a href="' . $file->download_link . '" class="' . $download_btn_class . '" target="_blank">' . $download_text . '</a>';
                            if ($file->expired != true) {
                                $download_cell .= ' ' . '<a href="' . $file->public_url . '" class="' . $download_btn_class . '" target="_blank">' . __('Direct link') . '</a>';
                            }
                        }
                        
                        $tbody_cells = array(
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
                                                    'condition'     => (get_option('public_listing_enable_preview') == 1),
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
                        'link' => 'public.php',
                        'current' => $pagination_page,
                        'item_count' => $files['pagination']['total'],
                        'items_per_page' => $per_page,
                    ]);
                }
            ?>
        </div>
    </div>
</form>

<div class="row">
    <div class="col-xs-12">
        <div class="login_form_links">
            <?php
                if ( !user_is_logged_in() && get_option('clients_can_register') == '1') {
            ?>
                    <p id="register_link"><?php _e("Don't have an account yet?",'cftp_admin'); ?> <a href="<?php echo BASE_URI; ?>register.php"><?php _e('Register as a new client.','cftp_admin'); ?></a></p>
            <?php
                }
            ?>
            <p><a href="<?php echo BASE_URI; ?>" target="_self"><?php _e('Go back to the homepage.','cftp_admin'); ?></a></p>
        </div>
    </div>
</div>

<?php
    include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
