<?php
    if (!current_user_can('view_dashboard_counters')) {
        exit;
    }

    /** Get the data to show on the bars graphic */
    $statement = $dbh->query("SELECT distinct id FROM " . TABLE_FILES );
    $total_files = $statement->rowCount();

    $statement = $dbh->query("SELECT distinct id FROM " . TABLE_USERS . " WHERE role_id = (SELECT id FROM " . TABLE_ROLES . " WHERE name = 'Client')");
    $total_clients = $statement->rowCount();

    $statement = $dbh->query("SELECT distinct id FROM " . TABLE_GROUPS);
    $total_groups = $statement->rowCount();

    $statement = $dbh->query("SELECT distinct id FROM " . TABLE_USERS . " WHERE role_id != (SELECT id FROM " . TABLE_ROLES . " WHERE name = 'Client')");
    $total_users = $statement->rowCount();

    $statement = $dbh->query("SELECT distinct id FROM " . TABLE_CATEGORIES);
    $total_categories = $statement->rowCount();

    $statement = $dbh->query("SELECT distinct id FROM " . TABLE_DOWNLOADS);
    $total_downloads = $statement->rowCount();

    // Get total storage used
    $storage_sql = "SELECT SUM(size) as total_storage FROM " . TABLE_FILES;
    $storage_statement = $dbh->prepare($storage_sql);
    $storage_statement->execute();
    $total_storage_bytes = $storage_statement->fetchColumn();

    // Helper function to format file sizes
    if (!function_exists('formatFileSize')) {
        function formatFileSize($bytes) {
            if ($bytes >= 1073741824) {
                return number_format($bytes / 1073741824, 2) . ' GB';
            } elseif ($bytes >= 1048576) {
                return number_format($bytes / 1048576, 2) . ' MB';
            } elseif ($bytes >= 1024) {
                return number_format($bytes / 1024, 2) . ' KB';
            } else {
                return $bytes . ' B';
            }
        }
    }

    $total_storage_formatted = formatFileSize($total_storage_bytes ?: 0);
?>
    <div class="row">
        <div class="col-12">
            <div class="widget_counters">
                <ul>
                    <li>
                        <h6><?php echo $total_files; ?></h6>
                        <h5><?php _e('Files','cftp_admin'); ?></h5>
                        <i class="fa fa-file"></i>
                    </li>
                    <li>
                        <h6><?php echo $total_downloads; ?></h6>
                        <h5><?php _e('Downloads','cftp_admin'); ?></h5>
                        <i class="fa fa-download"></i>
                    </li>
                    <li>
                        <h6><?php echo $total_clients; ?></h6>
                        <h5><?php _e('Clients','cftp_admin'); ?></h5>
                        <i class="fa fa-address-card"></i>
                    </li>
                    <li>
                        <h6><?php echo $total_groups; ?></h6>
                        <h5><?php _e('Groups','cftp_admin'); ?></h5>
                        <i class="fa fa-th-large"></i>
                    </li>
                    <li>
                        <h6><?php echo $total_users; ?></h6>
                        <h5><?php _e('System Users','cftp_admin'); ?></h5>
                        <i class="fa fa-users"></i>
                    </li>
                    <li>
                        <h6><?php echo $total_storage_formatted; ?></h6>
                        <h5><?php _e('Total Storage','cftp_admin'); ?></h5>
                        <i class="fa fa-hdd-o"></i>
                    </li>
                </ul>
            </div>
        </div>
    </div>
