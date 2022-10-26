<?php
$ld = 'cftp_template';

$count = $files['pagination']['total'];

if ($count == 0) {
    if (isset($_GET['search'])) {
        $flash->error(__('Your search keywords returned no results.', 'cftp_admin'));
    } else {
        $flash->error(__('There are no files available.', 'cftp_admin'));
    }
}

$groups = get_groups([
    'public' => true,
]);

// Search + filters bar data
$search_form_action = 'public.php';
$groups_select_options = [];
foreach ($groups as $group) {
    $groups_select_options[$group['id']] = [
        'name' => $group['name'] .' ('.count_public_files_in_group($group['id']).')',
        'attributes' => [
            'data-token' => $group['public_token'],
        ],
    ];
}

$filters_form = [
    'action' => $current_url,
    'items' => [
        'group' => [
            'current' => (isset($_GET['group'])) ? $_GET['group'] : null,
            'placeholder' => [
                'value' => '0',
                'label' => __('Not in any group', 'cftp_admin') .' ('.count_public_files_not_in_groups().')',
            ],
            'options' => $groups_select_options,
        ]
    ],
    'hidden_inputs' => [
        'token' => (isset($_GET['token'])) ? htmlentities($_GET['token']) : '',
    ],
];

// Results count and form actions 
$elements_found_count = $count;
$bulk_actions_items = [];

// Include layout files
$flash_size = 'full';
include_once ADMIN_VIEWS_DIR . DS . 'header-unlogged.php';

include_once LAYOUT_DIR . DS . 'search-filters-bar.php';
?>
<form action="" name="files_list" method="get" class="form-inline batch_actions">
    <?php include_once LAYOUT_DIR . DS . 'form-counts-actions.php'; ?>

    <?php if ($mode == 'group' && !empty($group_props['description'])) { ?>
        <div class="row">
            <div class="col-12">
                <div class="group_description">
                    <h3><?php _e('About this group', 'cftp_admin'); ?></h3>
                    <?php echo htmlentities_allowed($group_props['description']); ?>
                </div>
            </div>
        </div>
    <?php } ?>

    <div class="row">
        <div class="col-12">
            <?php
            if ($count > 0) {
                // Generate the table using the class.
                $table = new \ProjectSend\Classes\Layout\Table([
                    'id' => 'files_list',
                    'class' => 'footable table',
                ]);

                $thead_columns = array(
                    array(
                        'sortable' => true,
                        'sort_url' => 'filename',
                        'content' => __('Title', 'cftp_admin'),
                    ),
                    array(
                        'content' => __('Type', 'cftp_admin'),
                        'hide' => 'phone',
                    ),
                    array(
                        'sortable' => true,
                        'sort_url' => 'description',
                        'content' => __('Description', 'cftp_admin'),
                        'hide' => 'phone',
                        'attributes' => array(
                            'class' => array('description'),
                        ),
                    ),
                    array(
                        'content' => __('Size', 'cftp_admin'),
                        'hide' => 'phone',
                    ),
                    array(
                        'sortable' => true,
                        'sort_url' => 'timestamp',
                        'sort_default' => true,
                        'content' => __('Date', 'cftp_admin'),
                    ),
                    array(
                        'content' => __('Expiration date', 'cftp_admin'),
                        'hide' => 'phone',
                    ),
                    array(
                        'content' => __('Preview', 'cftp_admin'),
                        'hide' => 'phone,tablet',
                        'condition' => (get_option('public_listing_enable_preview') == 1),
                    ),
                    array(
                        'content' => __('Download', 'cftp_admin'),
                        'hide' => 'phone',
                    ),
                );

                $table->thead($thead_columns);

                foreach ($files['files_ids'] as $file_id) {
                    $file = new \ProjectSend\Classes\Files($file_id);

                    $table->addRow();

                    /** File title */
                    $title_content = '<strong>' . $file->title . '</strong>';
                    if ($file->title != $file->filename_original) {
                        $title_content .= '<br><small>'.$file->filename_original.'</small>';
                    }
                    if (file_is_image($file->full_path)) {
                        $dimensions = $file->getDimensions();
                        if (!empty($dimensions)) {
                            $title_content .= '<br><div class="file_meta"><small>'.$dimensions['width'].' x '.$dimensions['height'].' px</small></div>';
                        }
                    }


                    /** Extension */
                    $extension_cell = '<span class="badge bg-success label_big">' . $file->extension . '</span>';

                    /** Date */
                    $date = format_date($file->uploaded_date);

                    /** Expiration */
                    if ($file->expires == '1') {
                        if ($file->expired == false) {
                            $badge_class = 'bg-primary';
                        } else {
                            $badge_class = 'bg-danger';
                        }

                        $value = date(get_option('timeformat'), strtotime($file->expiry_date));
                    } else {
                        $badge_class = 'bg-success';
                        $badge_label = __('Never', 'cftp_template');
                    }

                    $expiration_cell = '<span class="badge ' . $badge_class . ' label_big">' . $badge_label . '</span>';

                    /** Thumbnail */
                    $preview_cell = '';
                    if ($file->expired == false) {
                        if ($file->isImage()) {
                            $thumbnail = make_thumbnail($file->full_path, null, 50, 50);
                            if (!empty($thumbnail['thumbnail']['url'])) {
                                $preview_cell = '
                                        <a href="#" class="get-preview" data-url="' . BASE_URI . 'process.php?do=get_preview&file_id=' . $file->id . '">
                                            <img src="' . $thumbnail['thumbnail']['url'] . '" class="thumbnail" alt="' . $file->title . '" />
                                        </a>';
                            }
                        } else {
                            if ($file->embeddable) {
                                $preview_cell = '<button class="btn btn-warning btn-sm btn-wide get-preview" data-url="' . BASE_URI . 'process.php?do=get_preview&file_id=' . $file->id . '">' . __('Preview', 'cftp_admin') . '</button>';
                            }
                        }
                    }

                    /** Download */
                    if ($file->expired == true) {
                        $download_link = 'javascript:void(0);';
                        $download_btn_class = 'btn btn-danger btn-sm disabled';
                        $download_text = __('File expired', 'cftp_template');
                    } else {
                        $download_btn_class = 'btn btn-primary btn-sm';
                        $download_text = __('Download', 'cftp_template');
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
                            'content' => $title_content,
                            'attributes' => array(
                                'class' => array('file_name'),
                            ),
                        ),
                        array(
                            'content' => $extension_cell,
                            'attributes' => array(
                                'class' => array('extra'),
                            ),
                        ),
                        array(
                            'content' => $file->description,
                            'attributes' => array(
                                'class' => array('description'),
                            ),
                        ),
                        array(
                            'content' => $file->size_formatted,
                        ),
                        array(
                            'content' => $date,
                        ),
                        array(
                            'content' => $expiration_cell,
                        ),
                        array(
                            'content' => $preview_cell,
                            'attributes' => array(
                                'class' => array('extra'),
                            ),
                            'condition' => (get_option('public_listing_enable_preview') == 1),
                        ),
                        array(
                            'content' => $download_cell,
                            'attributes' => array(
                                'class' => array('text-center'),
                            ),
                        ),
                    );

                    foreach ($tbody_cells as $cell) {
                        $table->addCell($cell);
                    }

                    $table->end_row();
                }

                echo $table->render();

                // PAGINATION
                $pagination = new \ProjectSend\Classes\Layout\Pagination;
                echo $pagination->make([
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
    <div class="col-12">
        <?php
            $links = [];
            if (!user_is_logged_in()) {
                $links[] = 'register';
            }
            $links[] = 'homepage';
            login_form_links($links);
        ?>
    </div>
</div>

<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
