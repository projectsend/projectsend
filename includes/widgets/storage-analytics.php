<?php
if (!current_user_can('view_storage_analytics')) {
    exit;
}

// Get file type statistics with sizes
$sql = "SELECT
    CASE
        WHEN LOWER(SUBSTRING_INDEX(original_url, '.', -1)) IN ('jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp') THEN 'Images'
        WHEN LOWER(SUBSTRING_INDEX(original_url, '.', -1)) IN ('pdf') THEN 'PDF'
        WHEN LOWER(SUBSTRING_INDEX(original_url, '.', -1)) IN ('doc', 'docx', 'txt', 'rtf') THEN 'Documents'
        WHEN LOWER(SUBSTRING_INDEX(original_url, '.', -1)) IN ('xls', 'xlsx', 'csv') THEN 'Spreadsheets'
        WHEN LOWER(SUBSTRING_INDEX(original_url, '.', -1)) IN ('mp4', 'avi', 'mov', 'wmv', 'flv') THEN 'Videos'
        WHEN LOWER(SUBSTRING_INDEX(original_url, '.', -1)) IN ('mp3', 'wav', 'flac', 'aac') THEN 'Audio'
        WHEN LOWER(SUBSTRING_INDEX(original_url, '.', -1)) IN ('zip', 'rar', '7z', 'tar', 'gz') THEN 'Archives'
        ELSE 'Other'
    END as file_type,
    COUNT(*) as file_count,
    SUM(size) as total_size
FROM " . TABLE_FILES . "
GROUP BY file_type
ORDER BY total_size DESC";

$statement = $dbh->prepare($sql);
$statement->execute();
$file_types = $statement->fetchAll(PDO::FETCH_ASSOC);

// Get total storage statistics
$total_sql = "SELECT
    COUNT(*) as total_files,
    SUM(size) as total_storage,
    AVG(size) as avg_file_size,
    MIN(size) as min_size,
    MAX(size) as max_size
FROM " . TABLE_FILES;
$total_statement = $dbh->prepare($total_sql);
$total_statement->execute();
$totals = $total_statement->fetch(PDO::FETCH_ASSOC);

// Get largest files
$largest_sql = "SELECT original_url, size, timestamp
FROM " . TABLE_FILES . "
WHERE size > 0
ORDER BY size DESC
LIMIT 5";
$largest_statement = $dbh->prepare($largest_sql);
$largest_statement->execute();
$largest_files = $largest_statement->fetchAll(PDO::FETCH_ASSOC);

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

// Calculate storage usage from file system (simplified for performance)
$total_storage = 0;
$file_count = $totals['total_files'];

// Try to get disk usage if upload directory is accessible
$disk_total = false;
$disk_free = false;
$disk_used = false;

if (defined('UPLOADED_FILES_DIR') && is_dir(UPLOADED_FILES_DIR)) {
    $disk_total = disk_total_space(UPLOADED_FILES_DIR);
    $disk_free = disk_free_space(UPLOADED_FILES_DIR);
    if ($disk_total && $disk_free) {
        $disk_used = $disk_total - $disk_free;
    }
}
?>
<div class="widget" id="widget_storage_analytics">
    <h4><?php _e('Storage Analytics', 'cftp_admin'); ?></h4>
    <div class="widget_int">
        <div class="row">
            <div class="col-md-4">
                <h5><?php _e('Storage Overview', 'cftp_admin'); ?></h5>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <strong><?php echo number_format($totals['total_files']); ?></strong>
                        <?php _e('Total Files', 'cftp_admin'); ?>
                    </li>
                    <li class="mb-2">
                        <strong><?php echo formatFileSize($totals['total_storage']); ?></strong>
                        <?php _e('Used Storage', 'cftp_admin'); ?>
                    </li>
                    <li class="mb-2">
                        <strong><?php echo formatFileSize($totals['avg_file_size']); ?></strong>
                        <?php _e('Average File Size', 'cftp_admin'); ?>
                    </li>
                    <li class="mb-2">
                        <strong><?php echo formatFileSize($totals['max_size']); ?></strong>
                        <?php _e('Largest File', 'cftp_admin'); ?>
                    </li>
                </ul>

                <?php if (current_user_can('edit_settings')) { ?>
                    <div class="mt-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-recalculate-storage">
                            <i class="fa fa-refresh"></i> <?php _e('Recalculate Storage', 'cftp_admin'); ?>
                        </button>
                        <div id="recalculate-storage-status" class="mt-2 small"></div>
                    </div>
                <?php } ?>

                <?php if ($disk_total) { ?>
                    <div class="mt-3">
                        <h6><?php _e('Disk Usage', 'cftp_admin'); ?></h6>
                        <div class="progress mb-2" style="height: 8px;">
                            <div class="progress-bar" role="progressbar"
                                 style="width: <?php echo round(($disk_used / $disk_total) * 100, 1); ?>%">
                            </div>
                        </div>
                        <small class="text-muted">
                            <?php echo formatFileSize($disk_used); ?> / <?php echo formatFileSize($disk_total); ?>
                            (<?php echo round(($disk_used / $disk_total) * 100, 1); ?>% <?php _e('used', 'cftp_admin'); ?>)
                        </small>
                    </div>
                <?php } ?>
            </div>

            <div class="col-md-4">
                <h5><?php _e('File Types', 'cftp_admin'); ?></h5>
                <div class="file-types-list">
                    <?php foreach ($file_types as $type) { ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>
                                <?php echo $type['file_type']; ?>
                                <small class="text-muted">(<?php echo $type['file_count']; ?>)</small>
                            </span>
                            <span class="badge bg-secondary">
                                <?php echo formatFileSize($type['total_size']); ?>
                            </span>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="col-md-4">
                <h5><?php _e('Largest Files', 'cftp_admin'); ?></h5>
                <div class="largest-files-list">
                    <?php if (empty($largest_files)) { ?>
                        <p class="text-muted small"><?php _e('No files found', 'cftp_admin'); ?></p>
                    <?php } else { ?>
                        <?php foreach ($largest_files as $file) { ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between">
                                    <span class="text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($file['original_url']); ?>">
                                        <small><?php echo htmlspecialchars(basename($file['original_url'])); ?></small>
                                    </span>
                                    <span class="badge bg-warning">
                                        <?php echo formatFileSize($file['size']); ?>
                                    </span>
                                </div>
                                <small class="text-muted">
                                    <?php echo date('M j, Y', strtotime($file['timestamp'])); ?>
                                </small>
                            </div>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>