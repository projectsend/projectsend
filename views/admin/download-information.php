<?php
/**
 * Shows a table of details of a file download information
 */
$allowed_levels = array(9, 8, 7);

$active_nav = 'files';

$page_title = __('Download information', 'cftp_admin');

// Check if the id parameter is on the URI
if (!isset($_GET['id'])) {
    exit_with_error_code(403);
}

$file_id = (int)$_GET['id'];
$file = new \ProjectSend\Classes\Files($file_id);
if (!$file->recordExists()) {
    exit_with_error_code(403);
}

// Get the file information from the database
$downloads = get_downloads_information($file_id)[$file_id];

if ($downloads['total'] == 0) {
    $flash->error(__('There are no recorded downloads for this file','cftp_admin'));
}

$page_title .= ': ' . $file->filename_original;

if ($downloads['total'] > 0) {
    $params = [];
    $cq = "SELECT * FROM " . get_table('downloads') . " WHERE file_id = :id";
    
    /**
     * Add the order.
     * Defaults to order by: name, order: ASC
     */
    $cq .= sql_add_order(get_table('groups'), 'timestamp', 'desc');
    
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
    
    // Make a list of users names
    $users_names = [];
    $dbh = get_dbh();
    $names = $dbh->prepare("SELECT id, name FROM " . get_table('users'));
    $names->execute();
    if ($names->rowCount() > 0) {
        $names->setFetchMode(PDO::FETCH_ASSOC);
        while ($row = $names->fetch()) {
            $users_names[$row['id']] = $row['name'];
        }
    }
}

include_once VIEWS_PARTS_DIR.DS.'header.php';

if ($downloads['total'] > 0) {
?>
    <div class="row">
        <div class="col-sm-12">
            <h3><?php _e('Total downloads', 'cftp_admin'); ?>: <span class="badge bg-primary"><strong><?php echo $downloads['total']; ?></strong></span></h3>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-12">
            <?php
            // Generate the table using the class.
            $table = new \ProjectSend\Classes\Layout\Table([
                'id' => 'download_info_tbl',
                'class' => 'footable table',
            ]);

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
                    'content' => __('Unique logged in clients/users', 'cftp_admin') . ': <span class="badge bg-primary">' . $downloads['unique_clients'] . '</span>',
                ),
                array(
                    'content' => __('Total public downloads', 'cftp_admin') . ': <span class="badge bg-primary">' . $downloads['anonymous_users'] . '</span>',
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
                    $user = new \ProjectSend\Classes\Users($row['user_id']);
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
            $pagination = new \ProjectSend\Classes\Layout\Pagination;
            echo $pagination->make([
                'link' => 'download-information.php',
                'current' => $pagination_page,
                'item_count' => $count_for_pagination,
            ]);
            ?>
        </div>
    </div>
<?php
}

include_once VIEWS_PARTS_DIR.DS.'footer.php';
