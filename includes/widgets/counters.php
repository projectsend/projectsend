<?php
    $allowed = array(9);
    if (!in_array(CURRENT_USER_LEVEL,$allowed)) {
        exit;
    }

    /** Get the data to show on the bars graphic */
    $statement = $dbh->query("SELECT distinct id FROM " . TABLE_FILES );
    $total_files = $statement->rowCount();

    $statement = $dbh->query("SELECT distinct id FROM " . TABLE_USERS . " WHERE level = '0'");
    $total_clients = $statement->rowCount();

    $statement = $dbh->query("SELECT distinct id FROM " . TABLE_GROUPS);
    $total_groups = $statement->rowCount();

    $statement = $dbh->query("SELECT distinct id FROM " . TABLE_USERS . " WHERE level != '0'");
    $total_users = $statement->rowCount();

    $statement = $dbh->query("SELECT distinct id FROM " . TABLE_CATEGORIES);
    $total_categories = $statement->rowCount();

    $statement = $dbh->query("SELECT distinct id FROM " . TABLE_DOWNLOADS);
    $total_downloads = $statement->rowCount();
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
                </ul>
            </div>
        </div>
    </div>
