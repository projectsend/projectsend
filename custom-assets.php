<?php
/**
 * Custom HTML/CSS/JS assets administration.
 *
 */
$allowed_levels = array(9);
require_once 'bootstrap.php';

$active_nav = 'tools';

$page_title = __('Custom HTML/CSS/JS', 'cftp_admin');
include_once ADMIN_VIEWS_DIR . DS . 'header.php';

$current_url = get_form_action_with_existing_parameters(basename(__FILE__));

/**
 * Apply the corresponding action to the selected clients.
 */
if (isset($_POST['action'])) {
    /** Continue only if 1 or more clients were selected. */
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
?>
<div class="row">
    <div class="col-12">
        <div class="form_actions_left">
            <div class="form_actions_limit_results">
                <?php show_search_form('custom-assets.php'); ?>

                <form action="custom-assets.php" name="assets_filters" method="get" class="form-inline">
                    <?php form_add_existing_parameters(array('enabled', 'action')); ?>
                    <div class="form-group row group_float">
                        <select class="form-select form-control-short" name="enabled" id="enabled">
                            <?php
                            $status_options = array(
                                '2' => __('All statuses', 'cftp_admin'),
                                '1' => __('Enabled', 'cftp_admin'),
                                '0' => __('Disabled', 'cftp_admin'),
                            );
                            foreach ($status_options as $val => $text) {
                            ?>
                                <option value="<?php echo $val; ?>" <?php if (isset($_GET['enabled']) && $_GET['enabled'] == $val) { echo 'selected="selected"'; } ?>>
                                    <?php echo $text; ?>
                                </option>
                            <?php
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" id="btn_proceed_filter_assets" class="btn btn-sm btn-pslight"><?php _e('Filter', 'cftp_admin'); ?></button>
                </form>
            </div>
        </div>

        <form action="<?php echo $current_url; ?>" name="assets_list" method="post" class="form-inline batch_actions">
            <?php addCsrf(); ?>
            <div class="form_actions_right">
                <div class="form_actions">
                    <div class="form_actions_submit">
                        <div class="form-group row group_float">
                            <label class="control-label hidden-xs hidden-sm"><i class="fa fa-check"></i> <?php _e('Selected clients actions', 'cftp_admin'); ?>:</label>
                            <select class="form-select form-control-short" name="action" id="action">
                                <?php
                                $actions_options = array(
                                    'none' => __('Select action', 'cftp_admin'),
                                    'enable' => __('Enable', 'cftp_admin'),
                                    'disable' => __('Disable', 'cftp_admin'),
                                    'delete' => __('Delete', 'cftp_admin'),
                                );
                                foreach ($actions_options as $val => $text) {
                                ?>
                                    <option value="<?php echo $val; ?>"><?php echo $text; ?></option>
                                <?php
                                }
                                ?>
                            </select>
                        </div>
                        <button type="submit" id="do_action" class="btn btn-sm btn-pslight"><?php _e('Proceed', 'cftp_admin'); ?></button>
                    </div>
                </div>
            </div>
    </div>
</div>

<div class="row">
    <div class="col-12 col-md-6 form_actions_count">
        <div>
            <p><?php echo sprintf(__('Found %d elements', 'cftp_admin'), (int)$count_for_pagination); ?></p>
        </div>
    </div>
    <div class="col-12 col-md-6 form_actions_count">
        <div class="text-right">
            <a href="custom-assets-add.php?language=html" class="btn btn-pslight btn-sm"><?php _e('New HTML', 'cftp_admin'); ?></a>
            <a href="custom-assets-add.php?language=css" class="btn btn-pslight btn-sm"><?php _e('New CSS', 'cftp_admin'); ?></a>
            <a href="custom-assets-add.php?language=js" class="btn btn-pslight btn-sm"><?php _e('New JS', 'cftp_admin'); ?></a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <?php
        if (!$count) {
            if (isset($no_results_error)) {
                switch ($no_results_error) {
                    case 'search':
                        $no_results_message = __('Your search keywords returned no results.', 'cftp_admin');
                        break;
                    case 'filter':
                        $no_results_message = __('The filters you selected returned no results.', 'cftp_admin');
                        break;
                }
            } else {
                $no_results_message = __('There are no assets at the moment', 'cftp_admin');
            }
            echo system_message('danger', $no_results_message);
        }

        if ($count > 0) {
            /**
             * Generate the table using the class.
             */
            $table_attributes = array(
                'id' => 'assets_tbl',
                'class' => 'footable table',
            );
            $table = new \ProjectSend\Classes\TableGenerate($table_attributes);

            $thead_columns        = array(
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

                $asset = new \ProjectSend\Classes\CustomAsset;
                $asset->get($row["id"]);

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

            // PAGINATION
            $pagination = new \ProjectSend\Classes\PaginationLayout;
            echo $pagination->make([
            'link' => 'custom-assets.php',
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
