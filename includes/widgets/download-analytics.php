<?php
if (!current_user_can('view_download_analytics')) {
    exit;
}

// Get download statistics
$total_downloads_sql = "SELECT COUNT(*) as total_downloads FROM " . TABLE_DOWNLOADS;
$total_downloads_statement = $dbh->prepare($total_downloads_sql);
$total_downloads_statement->execute();
$total_downloads = $total_downloads_statement->fetchColumn();

// Get downloads this week
$week_downloads_sql = "SELECT COUNT(*) as week_downloads
FROM " . TABLE_DOWNLOADS . "
WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
$week_downloads_statement = $dbh->prepare($week_downloads_sql);
$week_downloads_statement->execute();
$week_downloads = $week_downloads_statement->fetchColumn();

// Get downloads today
$today_downloads_sql = "SELECT COUNT(*) as today_downloads
FROM " . TABLE_DOWNLOADS . "
WHERE DATE(timestamp) = CURDATE()";
$today_downloads_statement = $dbh->prepare($today_downloads_sql);
$today_downloads_statement->execute();
$today_downloads = $today_downloads_statement->fetchColumn();

// Get most downloaded files
$popular_sql = "SELECT f.original_url, COUNT(d.id) as download_count
FROM " . TABLE_DOWNLOADS . " d
JOIN " . TABLE_FILES . " f ON d.file_id = f.id
GROUP BY d.file_id, f.original_url
ORDER BY download_count DESC
LIMIT 5";
$popular_statement = $dbh->prepare($popular_sql);
$popular_statement->execute();
$popular_files = $popular_statement->fetchAll(PDO::FETCH_ASSOC);

// Get recent downloads with client information
$recent_sql = "SELECT f.original_url, d.timestamp, u.name as client_name
FROM " . TABLE_DOWNLOADS . " d
JOIN " . TABLE_FILES . " f ON d.file_id = f.id
LEFT JOIN " . TABLE_USERS . " u ON d.user_id = u.id
ORDER BY d.timestamp DESC
LIMIT 5";
$recent_statement = $dbh->prepare($recent_sql);
$recent_statement->execute();
$recent_downloads = $recent_statement->fetchAll(PDO::FETCH_ASSOC);


// Get top downloading clients
$top_clients_sql = "SELECT u.name, COUNT(d.id) as download_count
FROM " . TABLE_DOWNLOADS . " d
LEFT JOIN " . TABLE_USERS . " u ON d.user_id = u.id
WHERE d.timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY d.user_id, u.name
ORDER BY download_count DESC
LIMIT 5";
$top_clients_statement = $dbh->prepare($top_clients_sql);
$top_clients_statement->execute();
$top_clients = $top_clients_statement->fetchAll(PDO::FETCH_ASSOC);

// Get unique downloaders
$unique_sql = "SELECT COUNT(DISTINCT user_id) as unique_downloaders
FROM " . TABLE_DOWNLOADS . "
WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$unique_statement = $dbh->prepare($unique_sql);
$unique_statement->execute();
$unique_downloaders = $unique_statement->fetchColumn();
?>
<div class="widget" id="widget_download_analytics">
    <h4><?php _e('Download Analytics', 'cftp_admin'); ?></h4>
    <div class="widget_int">
        <div class="row">
            <div class="col-md-3">
                <h5><?php _e('Download Stats', 'cftp_admin'); ?></h5>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <strong><?php echo number_format($total_downloads); ?></strong>
                        <?php _e('total downloads', 'cftp_admin'); ?>
                    </li>
                    <li class="mb-2">
                        <strong><?php echo $week_downloads; ?></strong>
                        <?php _e('this week', 'cftp_admin'); ?>
                    </li>
                    <li class="mb-2">
                        <strong><?php echo $today_downloads; ?></strong>
                        <?php _e('today', 'cftp_admin'); ?>
                    </li>
                    <li class="mb-2">
                        <strong><?php echo $unique_downloaders; ?></strong>
                        <?php _e('active users', 'cftp_admin'); ?>
                    </li>
                </ul>
            </div>

            <div class="col-md-3">
                <h5><?php _e('Popular Files', 'cftp_admin'); ?></h5>
                <div class="popular-files-list">
                    <?php if (empty($popular_files)) { ?>
                        <p class="text-muted small"><?php _e('No downloads yet', 'cftp_admin'); ?></p>
                    <?php } else { ?>
                        <?php foreach ($popular_files as $file) { ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($file['original_url']); ?>">
                                    <small><?php echo htmlspecialchars(basename($file['original_url'])); ?></small>
                                </span>
                                <span class="badge bg-primary"><?php echo $file['download_count']; ?></span>
                            </div>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>

            <div class="col-md-3">
                <h5><?php _e('Recent Activity', 'cftp_admin'); ?></h5>
                <div class="recent-downloads-list">
                    <?php if (empty($recent_downloads)) { ?>
                        <p class="text-muted small"><?php _e('No recent downloads', 'cftp_admin'); ?></p>
                    <?php } else { ?>
                        <?php foreach ($recent_downloads as $download) { ?>
                            <div class="mb-2">
                                <div class="text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($download['original_url']); ?>">
                                    <small><?php echo htmlspecialchars(basename($download['original_url'])); ?></small>
                                </div>
                                <small class="text-muted">
                                    <?php echo $download['client_name'] ? htmlspecialchars($download['client_name']) : __('Anonymous', 'cftp_admin'); ?>
                                    - <?php echo date('M j, H:i', strtotime($download['timestamp'])); ?>
                                </small>
                            </div>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>

            <div class="col-md-3">
                <h5><?php _e('Top Clients', 'cftp_admin'); ?></h5>
                <div class="top-clients-list">
                    <?php if (empty($top_clients)) { ?>
                        <p class="text-muted small"><?php _e('No downloads this month', 'cftp_admin'); ?></p>
                    <?php } else { ?>
                        <?php foreach ($top_clients as $client) { ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-truncate" style="max-width: 120px;" title="<?php echo htmlspecialchars($client['name']); ?>">
                                    <small><?php echo $client['name'] ? htmlspecialchars($client['name']) : __('Anonymous', 'cftp_admin'); ?></small>
                                </span>
                                <span class="badge bg-success"><?php echo $client['download_count']; ?></span>
                            </div>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>
        </div>

    </div>
</div>