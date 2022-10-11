<?php
/**
 * Custom HTML/CSS/JS assets administration.
 *
 */
$allowed_levels = array(9);

$active_nav = 'tools';

$page_title = __('Custom HTML/CSS/JS', 'cftp_admin');

$current_url = get_form_action_with_existing_parameters(basename(__FILE__));

// Apply the corresponding bulk action
if (isset($_POST['action'])) {
    if (!empty($_POST['batch'])) {
        $custom_assets = $_POST['batch'];

        switch ($_POST['action']) {
            case 'enable':
                foreach ($custom_assets as $asset_id) {
                    $asset = new \ProjectSend\Classes\CustomAsset();
                    if ($asset->get($asset_id)) {
                        $enable = $asset->enable();
                    }
                }

                $flash->success(__('The selected assets were marked as enabled.', 'cftp_admin'));
                break;
            case 'disable':
                foreach ($custom_assets as $asset_id) {
                    $asset = new \ProjectSend\Classes\CustomAsset();
                    if ($asset->get($asset_id)) {
                        $disable = $asset->disable();
                    }
                }

                $flash->success(__('The selected assets were marked as disabled.', 'cftp_admin'));
                break;
            case 'delete':
                foreach ($custom_assets as $asset_id) {
                    $asset = new \ProjectSend\Classes\CustomAsset();
                    if ($asset->get($asset_id)) {
                        $delete = $asset->delete();
                    }
                }

                $flash->success(__('The selected assets were deleted.', 'cftp_admin'));
                break;
        }
    } else {
        $flash->error(__('Please select at least one asset.', 'cftp_admin'));
    }

    ps_redirect($current_url);
}

$params = [];
$cq = "SELECT id FROM " . TABLE_CUSTOM_ASSETS;
$next_clause = ' WHERE';

// Add the search terms
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $cq .= " WHERE (title LIKE :title OR content LIKE :content)";
    $next_clause = ' AND';
    $no_results_error = 'search';

    $search_terms = '%' . $_GET['search'] . '%';
    $params[':title'] = $search_terms;
    $params[':content'] = $search_terms;
}

// Add the enabled filter
if (isset($_GET['enabled']) && $_GET['enabled'] != '2') {
    $cq .= $next_clause . " enabled = :enabled";
    $next_clause = ' AND';
    $no_results_error = 'filter';
    $params[':enabled']    = (int)$_GET['enabled'];
}


// Add the order
$cq .= sql_add_order(TABLE_CUSTOM_ASSETS, 'id', 'desc');

// Pre-query to count the total results
$count_sql = $dbh->prepare($cq);
$count_sql->execute($params);
$count_for_pagination = $count_sql->rowCount();

// Repeat the query but this time, limited by pagination
$cq .= " LIMIT :limit_start, :limit_number";
$sql = $dbh->prepare($cq);

$pagination_page = (isset($_GET["page"])) ? $_GET["page"] : 1;
$pagination_start = ($pagination_page - 1) * get_option('pagination_results_per_page');
$params[':limit_start'] = $pagination_start;
$params[':limit_number'] = get_option('pagination_results_per_page');

$sql->execute($params);
$count = $sql->rowCount();

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
        $flash->warning(__('There are no assets yet.', 'cftp_admin'));
    }
}

// Header buttons
$header_action_buttons = [
    [
        'url' => 'custom-assets-add.php?language=html',
        'label' => __('New HTML', 'cftp_admin'),
    ],
    [
        'url' => 'custom-assets-add.php?language=css',
        'label' => __('New CSS', 'cftp_admin'),
    ],
    [
        'url' => 'custom-assets-add.php?language=js',
        'label' => __('New js', 'cftp_admin'),
    ],
];

// Search + filters bar data
$search_form_action = 'custom-assets.php';
$filters_form = [
    'action' => $current_url,
    'items' => [
        'enabled' => [
            'current' => (isset($_GET['enabled'])) ? $_GET['enabled'] : null,
            'placeholder' => [
                'value' => '2',
                'label' => __('All statuses', 'cftp_admin')
            ],
            'options' => [
                '1' => __('Enabled', 'cftp_admin'),
                '0' => __('Disabled', 'cftp_admin'),    
            ],
        ]
    ]
];

// Results count and form actions 
$elements_found_count = $count_for_pagination;
$bulk_actions_items = [
    'none' => __('Select action', 'cftp_admin'),
    'enable' => __('Enable', 'cftp_admin'),
    'disable' => __('Disable', 'cftp_admin'),
    'delete' => __('Delete', 'cftp_admin'),
];

// Include layout files
include_once VIEWS_PARTS_DIR.DS.'header.php';

include_once LAYOUT_DIR . DS . 'search-filters-bar.php';
?>

<form action="<?php echo $current_url; ?>" name="assets_list" method="post" class="form-inline batch_actions">
    <?php \ProjectSend\Classes\Csrf::addCsrf(); ?>
    <?php include_once LAYOUT_DIR . DS . 'form-counts-actions.php'; ?>

    <div class="row">
        <div class="col-12">
            <?php
            if ($count > 0) {
                // Generate the table using the class.
                $table = new \ProjectSend\Classes\Layout\Table([
                    'id' => 'assets_tbl',
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
                        'sort_url' => 'title',
                        'content' => __('Title', 'cftp_admin'),
                    ),
                    array(
                        'sortable' => true,
                        'sort_url' => 'enabled',
                        'content' => __('Enabled', 'cftp_admin'),
                    ),
                    array(
                        'sortable' => true,
                        'sort_url' => 'language',
                        'content' => __('Language', 'cftp_admin'),
                    ),
                    array(
                        'sortable' => true,
                        'sort_url' => 'location',
                        'content' => __('Location', 'cftp_admin'),
                    ),
                    array(
                        'sortable' => true,
                        'sort_url' => 'position',
                        'content' => __('Position', 'cftp_admin'),
                    ),
                    array(
                        'content' => __('Actions', 'cftp_admin'),
                        'hide' => 'phone',
                    ),
                );
                $table->thead($thead_columns);

                $sql->setFetchMode(PDO::FETCH_ASSOC);
                while ($row = $sql->fetch()) {
                    $table->addRow();

                    $asset = new \ProjectSend\Classes\CustomAsset($row["id"]);

                    /* Get active status */
                    $enabled_label = ($asset->enabled == 0) ? __('Disabled', 'cftp_admin') : __('Enabled', 'cftp_admin');
                    $enabled_class = ($asset->enabled == 0) ? 'bg-danger' : 'bg-success';

                    /**
                     * Add the cells to the row
                     */
                    $tbody_cells = array(
                        array(
                            'checkbox' => true,
                            'value' => $asset->id,
                        ),
                        array(
                            'content' => $asset->title,
                        ),
                        array(
                            'content' => '<span class="badge ' . $enabled_class . '">' . $enabled_label . '</span>',
                        ),
                        array(
                            'content' => $asset->language_formatted,
                        ),
                        array(
                            'content' => format_asset_location_name($asset->location),
                        ),
                        array(
                            'content' => format_asset_position_name($asset->position),
                        ),
                        array(
                            'actions' => true,
                            'content' =>  '<a href="custom-assets-edit.php?id=' . $asset->id . '" class="btn btn-sm btn-danger">' . __("Edit", "cftp_admin") . '</a>' . "\n",
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
            'link' => 'custom-assets.php',
            'current' => $pagination_page,
            'item_count' => $count_for_pagination,
        ]);
    }
?>
    
<?php
include_once VIEWS_PARTS_DIR.DS.'footer.php';
