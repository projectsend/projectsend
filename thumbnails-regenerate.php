<?php
/**
 * Regenerate thumbnails for uploaded images
 */
require_once 'bootstrap.php';
check_access_enhanced(['edit_settings']);

$page_title = __('Regenerate Thumbnails', 'cftp_admin');

$page_id = 'thumbnails_regenerate';

$active_nav = 'tools';
include_once ADMIN_VIEWS_DIR . DS . 'header.php';

global $flash;

// Define convertible image formats
$image_formats = ['png', 'jpg', 'jpeg', 'gif'];

// Get statistics for all convertible images
$all_images_stats = count_uploaded_files($image_formats);

// Get statistics per format - use the by_format data from the all_images_stats for efficiency
// But also ensure we have a count for each format even if it's 0
$format_stats = [];
foreach ($image_formats as $format) {
    $format_stats[$format] = isset($all_images_stats['by_format'][$format]) ? $all_images_stats['by_format'][$format] : 0;
}

// Get date range for uploaded files
global $dbh;
$date_range_query = "SELECT MIN(DATE(timestamp)) as min_date, MAX(DATE(timestamp)) as max_date FROM " . TABLE_FILES . " WHERE 1=1";
$date_range_sql = $dbh->prepare($date_range_query);
$date_range_sql->execute();
$date_range = $date_range_sql->fetch(PDO::FETCH_ASSOC);

// Handle date filter from GET parameters
$filter_start_date = isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date'], ENT_QUOTES, 'UTF-8') : null;
$filter_end_date = isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date'], ENT_QUOTES, 'UTF-8') : null;

// Re-calculate statistics with filtered dates if provided
if ($filter_start_date || $filter_end_date) {
    $all_images_stats = count_uploaded_files($image_formats, $filter_start_date, $filter_end_date);
    
    // Update per-format stats with filtered data
    $format_stats = [];
    foreach ($image_formats as $format) {
        $format_stats[$format] = isset($all_images_stats['by_format'][$format]) ? $all_images_stats['by_format'][$format] : 0;
    }
}

if ($_POST) {
    // TODO: AJAX processing will be implemented later
}
?>

<!-- Filter Results Info -->
<?php if ($date_range['min_date'] && $date_range['max_date']): ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i>
            <?php 
            if ($filter_start_date || $filter_end_date) {
                $message = __('Current filter shows files', 'cftp_admin');
                if ($filter_start_date && $filter_end_date) {
                    $message .= ' ' . sprintf(__('uploaded between %s and %s', 'cftp_admin'), 
                        date('F j, Y', strtotime($filter_start_date)), 
                        date('F j, Y', strtotime($filter_end_date)));
                } elseif ($filter_start_date) {
                    $message .= ' ' . sprintf(__('uploaded from %s onwards', 'cftp_admin'), 
                        date('F j, Y', strtotime($filter_start_date)));
                } elseif ($filter_end_date) {
                    $message .= ' ' . sprintf(__('uploaded until %s', 'cftp_admin'), 
                        date('F j, Y', strtotime($filter_end_date)));
                }
                echo $message;
            } else {
                echo sprintf(__('Showing all files uploaded between %s and %s', 'cftp_admin'), 
                    date('F j, Y', strtotime($date_range['min_date'])), 
                    date('F j, Y', strtotime($date_range['max_date'])));
            }
            ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Date Range Filter Section -->
<div class="row">
    <div class="col-12">
        <div class="ps-card">
            <div class="ps-card-body">
                <h5><?php _e('Date Range Filter', 'cftp_admin'); ?></h5>
                        <form action="thumbnails-regenerate.php" method="get" id="date-filter-form">
                            <div class="row align-items-end">
                                <div class="col-md-4">
                                    <label for="filter_start_date"><?php _e('Start Date', 'cftp_admin'); ?></label>
                                    <input type="date" 
                                           name="start_date" 
                                           id="filter_start_date" 
                                           class="form-control" 
                                           value="<?php echo $filter_start_date ?? ''; ?>"
                                           min="<?php echo $date_range['min_date'] ?? ''; ?>"
                                           max="<?php echo $date_range['max_date'] ?? ''; ?>">
                                    <small class="form-text text-muted"><?php _e('Leave empty for all files from beginning', 'cftp_admin'); ?></small>
                                </div>
                                <div class="col-md-4">
                                    <label for="filter_end_date"><?php _e('End Date', 'cftp_admin'); ?></label>
                                    <input type="date" 
                                           name="end_date" 
                                           id="filter_end_date" 
                                           class="form-control" 
                                           value="<?php echo $filter_end_date ?? ''; ?>"
                                           min="<?php echo $date_range['min_date'] ?? ''; ?>"
                                           max="<?php echo $date_range['max_date'] ?? ''; ?>">
                                    <small class="form-text text-muted"><?php _e('Leave empty for all files until now', 'cftp_admin'); ?></small>
                                </div>
                                <div class="col-md-4">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fa fa-filter"></i>
                                            <?php _e('Filter', 'cftp_admin'); ?>
                                        </button>
                                        <?php if ($filter_start_date || $filter_end_date): ?>
                                            <a href="thumbnails-regenerate.php" class="btn btn-secondary ms-2">
                                                <i class="fa fa-times"></i>
                                                <?php _e('Clear', 'cftp_admin'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <small class="form-text text-muted">&nbsp;</small>
                                </div>
                            </div>
                        </form>
            </div>
        </div>
    </div>
</div>

<!-- Regeneration Options Section -->
<div class="row mt-3">
    <div class="col-12">
        <div class="ps-card">
            <div class="ps-card-body">
                <h5><?php _e('Regeneration Options', 'cftp_admin'); ?></h5>
                
                <form action="thumbnails-regenerate.php" name="regenerate_thumbnails" method="post" enctype="multipart/form-data" class="form-horizontal" id="thumbnails-form">
                    <?php addCsrf(); ?>

                    <!-- Hidden inputs to maintain date filter when regenerating -->
                    <?php if ($filter_start_date): ?>
                        <input type="hidden" name="filtered_start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>">
                    <?php endif; ?>
                    <?php if ($filter_end_date): ?>
                        <input type="hidden" name="filtered_end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>">
                    <?php endif; ?>

                    <div class="form-group">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><?php _e('Thumbnail Dimensions', 'cftp_admin'); ?></h6>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="thumbnail_width"><?php _e('Width (pixels)', 'cftp_admin'); ?></label>
                                                <input type="number"
                                                       name="thumbnail_width"
                                                       id="thumbnail_width"
                                                       class="form-control"
                                                       value="<?php echo get_option('thumbnails_width', false, 200); ?>"
                                                       placeholder="200"
                                                       min="50"
                                                       max="1000"
                                                       required>
                                                <small class="form-text text-muted"><?php _e('Recommended: 150-300px', 'cftp_admin'); ?></small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="thumbnail_height"><?php _e('Height (pixels)', 'cftp_admin'); ?></label>
                                                <input type="number"
                                                       name="thumbnail_height"
                                                       id="thumbnail_height"
                                                       class="form-control"
                                                       value="<?php echo get_option('thumbnails_height', false, 200); ?>"
                                                       placeholder="200"
                                                       min="50"
                                                       max="1000"
                                                       required>
                                                <small class="form-text text-muted"><?php _e('Recommended: 150-300px', 'cftp_admin'); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <h6><?php _e('Format Selection', 'cftp_admin'); ?></h6>
                                    <div class="fw-bold fs-5 mb-3" style="color: var(--main_color);">
                                        <?php echo number_format($all_images_stats['total']); ?> <?php _e('total images', 'cftp_admin'); ?>
                                    </div>
                                    <div class="form-group">
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($image_formats as $format):
                                                $has_files = $format_stats[$format] > 0;
                                                $disabled = !$has_files ? 'disabled' : '';
                                                $checked = $has_files ? 'checked' : '';
                                            ?>
                                                <div class="format-selector <?php echo !$has_files ? 'format-disabled' : ''; ?>">
                                                    <input class="format-checkbox"
                                                           type="checkbox"
                                                           name="formats[]"
                                                           id="format_<?php echo $format; ?>"
                                                           value="<?php echo $format; ?>"
                                                           <?php echo $checked; ?>
                                                           <?php echo $disabled; ?>>
                                                    <label class="format-label" for="format_<?php echo $format; ?>">
                                                        <div class="format-name"><?php echo strtoupper($format); ?></div>
                                                        <div class="format-count"><?php echo number_format($format_stats[$format]); ?></div>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted"><?php _e('Select which image formats to process', 'cftp_admin'); ?></small>
                                </div>
                            </div>

                            </div>
                    </div>

                    <div class="after_form_buttons">
                        <button type="button"
                                name="submit"
                                class="btn btn-wide btn-primary mb-5"
                                id="regenerate-btn"
                                <?php echo ($all_images_stats['total'] == 0) ? 'disabled' : ''; ?>>
                            <i class="fa fa-refresh"></i>
                            <?php _e('Start Thumbnail Regeneration', 'cftp_admin'); ?>
                        </button>
                        
                        <?php if ($all_images_stats['total'] == 0): ?>
                            <div class="alert alert-warning mt-3">
                                <i class="fa fa-info-circle"></i>
                                <?php _e('No convertible images found. Upload some PNG, JPG, JPEG, or GIF files first.', 'cftp_admin'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
