<?php
/**
 * Shows a table of details of a file download information
 */
$allowed_levels = array(9, 8, 7);
require_once 'bootstrap.php';

$active_nav = 'files';

$page_title = __('Download information', 'cftp_admin');

// Check if the id parameter is on the URI
if (!isset($_GET['id'])) {
    exit_with_error_code(403);
}

$file_id = (int)$_GET['id'];
if (!download_information_exists($file_id)) {
    exit_with_error_code(403);
}

// Get the file information from the database
$file = get_file_by_id($file_id);
$general_stats = generate_downloads_count($file_id);
$file_stats = $general_stats[$file_id];

$filename_on_disk = (!empty($file['original_url'])) ? $file['original_url'] : $file['url'];

$page_title .= ': ' . $filename_on_disk;

// Make a list of users names
$users_names = [];
global $dbh;
$names = $dbh->prepare("SELECT id, name FROM " . TABLE_USERS);
$names->execute();
if ($names->rowCount() > 0) {
    $names->setFetchMode(PDO::FETCH_ASSOC);
    while ($row = $names->fetch()) {
        $users_names[$row['id']] = $row['name'];
    }
}

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>
<div class="row">
    <div class="col-12">
        <form action="download-information.php" name="groups_list" method="get" class="form-inline">
            <?php echo form_add_existing_parameters(); ?>

            <div class="row">
                <div class="col-sm-12">
                    <h3><?php _e('Total downloads', 'cftp_admin'); ?>: <span class="badge bg-primary"><strong><?php echo $file_stats['total']; ?></strong></span></h3>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <?php
                    $params = [];
                    $cq = "SELECT * FROM " . TABLE_DOWNLOADS . " WHERE file_id = :id";

                    /**
                     * Add the order.
                     * Defaults to order by: name, order: ASC
                     */
                    $cq .= sql_add_order(TABLE_GROUPS, 'timestamp', 'desc');

                    $statement = $dbh->prepare($cq);

                    $params[':id'] = $file_id;
                    $statement->execute($params);

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

                    // Generate the table using the class.
                    $table_attributes = array(
                        'id' => 'download_info_tbl',
                        'class' => 'footable table',
                    );
                    $table = new \ProjectSend\Classes\TableGenerate($table_attributes);

                    $thead_columns = array(
                        array(
                            'sortable' => true,
                            'sort_url' => 'timestamp',
                            'sort_default' => true,
                            'content' => __('Date', 'cftp_admin'),
                        ),
                        array(
                            'content' => __('Time', 'cftp_admin'),
                        ),
                        array(
                            'sortable' => true,
                            'sort_url' => 'user_id',
                            'content' => __('Client', 'cftp_admin'),
                        ),
                        array(
                            'sortable' => true,
                            'sort_url' => 'anonymous',
                            'content' => __('Anonymous', 'cftp_admin'),
                        ),
                        array(
                            'sortable' => true,
                            'sort_url' => 'remote_ip',
                            'content' => __("Client's IP", 'cftp_admin'),
                            'hide' => 'phone',
                        ),
                        array(
                            'sortable' => true,
                            'sort_url' => 'remote_host',
                            'content' => __("Client's hostname", 'cftp_admin'),
                            'hide' => 'phone',
                        ),
                    );
                    $table->thead($thead_columns);

                    $tfoot_columns = array(
                        array(
                            'content' => '',
                        ),
                        array(
                            'content' => '',
                        ),
                        array(
                            'content' => __('Unique logged in clients/users', 'cftp_admin') . ': <span class="badge bg-primary">' . $file_stats['unique_clients'] . '</span>',
                        ),
                        array(
                            'content' => __('Total public downloads', 'cftp_admin') . ': <span class="badge bg-primary">' . $file_stats['anonymous_users'] . '</span>',
                        ),
                        array(
                            'content' => '',
                        ),
                        array(
                            'content' => '',
                        ),
                    );
                    $table->tfoot($tfoot_columns);

                    $sql->setFetchMode(PDO::FETCH_ASSOC);
                    while ($row = $sql->fetch()) {
                        $table->addRow();

                        // Check if it's from a know user or anonymous
                        $anon_yes = __('Yes', 'cftp_admin');
                        $anon_no = __('No', 'cftp_admin');
                        $badge_label = ($row['anonymous'] == '1') ? $anon_yes : $anon_no;
                        $badge_class = ($row['anonymous'] == '1') ? 'bg-warning' : 'bg-success';

                        // Downloader
                        $downloader_row = null;
                        if (!empty($row['user_id'])) {
                            $user = new \ProjectSend\Classes\Users;
                            $user->get($row['user_id']);
                            if ($user->exists) {
                                if ($user->isClient()) {
                                    $link = BASE_URI . 'clients-edit.php?id=' . $user->id;
                                } else {
                                    $link = BASE_URI . 'users-edit.php?id=' . $user->id;
                                }
                                $downloader_row = '<a href="' . $link . '">' . $user->name . '<br>' . $user->email . '</a>';
                            }
                        }

                        // Add the cells to the row
                        $tbody_cells = array(
                            array(
                                'content' => format_date($row['timestamp']),
                            ),
                            array(
                                'content' => format_time($row['timestamp']),
                            ),
                            array(
                                'content' => $downloader_row,
                            ),
                            array(
                                'content' => '<span class="badge ' . $badge_class . '">' . $badge_label . '</span>',
                            ),
                            array(
                                'content' => html_output($row['remote_ip']),
                            ),
                            array(
                                'content' => html_output($row['remote_host']),
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
                            'link' => 'download-information.php',
                        'current' => $pagination_page,
                        'item_count' => $count_for_pagination,
                    ]);
                    ?>
                </div>
            </div>
        </form>
    </div>
</div>
<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
