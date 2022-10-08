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
define('TEMPLATE_THUMBNAILS_WIDTH', '50');
define('TEMPLATE_THUMBNAILS_HEIGHT', '50');

$filter_by_category = isset($_GET['category']) ? $_GET['category'] : null;

include_once ROOT_DIR . '/templates/common.php'; // include the required functions for every template

$window_title = __('File downloads', 'cftp_template');

$page_id = 'default_template';

$body_class = array('template', 'default-template', 'hide_title');

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
        $flash->warning(__('There are no files available.', 'cftp_admin'));
    }
}

// Header buttons
if (current_user_can_upload()) {
    $header_action_buttons = [
        [
            'url' => 'upload.php',
            'label' => __('Upload file', 'cftp_admin'),
        ],
    ];
}

// Search + filters bar data
$search_form_action = 'index.php';
$filters_form = [
    'action' => '',
    'items' => [],
];
if (!empty($cat_ids)) {
    $selected_parent = (isset($_GET['category'])) ? [$_GET['category']] : [];
    $category_filter = [];
    $generate_categories_options = generate_categories_options($get_categories['categories'], 0, $selected_parent, 'include', $cat_ids);
    foreach ($generate_categories_options as $category_id => $category) {
        $category_filter[$category_id] = $category['name'];
    }
    $filters_form['items']['category'] = [
        'current' => (isset($_GET['category'])) ? $_GET['category'] : null,
        'placeholder' => [
            'value' => '0',
            'label' => __('All categories', 'cftp_admin')
        ],
        'options' => $category_filter,
    ];
}

// Results count and form actions 
$elements_found_count = (isset($count_for_pagination)) ? $count_for_pagination : 0;
$bulk_actions_items = [
    'none' => __('Select action', 'cftp_admin'),
    'zip' => __('Download zipped', 'cftp_admin'),
];

// Include layout files
include_once ADMIN_VIEWS_DIR . DS . 'header.php';

include_once LAYOUT_DIR . DS . 'search-filters-bar.php';
?>
<form action="" name="files_list" method="get" class="form-inline batch_actions">
    <div class="row">
        <div class="col-12">
            <?php include_once LAYOUT_DIR . DS . 'form-counts-actions.php'; ?>

            <?php
            if (isset($count) && $count > 0) {
                // Generate the table using the class.
                $table = new \ProjectSend\Classes\Layout\Table([
                    'id' => 'files_list',
                    'class' => 'footable table',
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
                    ),
                    array(
                        'content' => __('Download', 'cftp_admin'),
                        'hide' => 'phone',
                    ),
                );

                $table->thead($thead_columns);

                foreach ($available_files as $file_id) {
                    $file = new \ProjectSend\Classes\Files($file_id);

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
                    } else {
                        $filetitle = $file_title_content;
                    }
                    $filetitle .= '<br><small>'.$file->filename_original.'</small>';

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

                        $badge_label = date(get_option('timeformat'), strtotime($file->expiry_date));
                    } else {
                        $badge_class = 'bg-success';
                        $badge_label = __('Never', 'cftp_template');
                    }

                    $expiration_cell = '<span class="badge ' . $badge_class . ' label_big">' . $badge_label . '</span>';

                    /** Thumbnail */
                    $preview_cell = '';
                    if ($file->expired == false) {
                        if ($file->isImage()) {
                            $thumbnail = make_thumbnail($file->full_path, null, TEMPLATE_THUMBNAILS_WIDTH, TEMPLATE_THUMBNAILS_HEIGHT);
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
                        $download_btn_class = 'btn btn-primary btn-sm btn-wide';
                        $download_text = __('Download', 'cftp_template');
                    }
                    $download_cell = '<a href="' . $file->download_link . '" class="' . $download_btn_class . '" target="_blank">' . $download_text . '</a>';



                    $tbody_cells = array(
                        array(
                            'content' => $checkbox,
                        ),
                        array(
                            'content' => $filetitle,
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
            'link' => 'my_files/index.php',
            'current' => $pagination_page,
            'item_count' => $count_for_pagination,
            'items_per_page' => TEMPLATE_RESULTS_PER_PAGE,
        ]);
    }

render_footer_text();

render_json_variables();

render_assets('js', 'footer');
render_assets('css', 'footer');

render_custom_assets('body_bottom');
?>
</body>

</html>