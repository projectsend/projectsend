<?php
/**
 * Batch encrypt unencrypted files
 */
require_once 'bootstrap.php';
check_access_enhanced(['edit_settings']);

$active_nav = 'tools';
$page_title = __('Encrypt Files', 'cftp_admin');
$page_id = 'encrypt_files';

// Check if encryption is enabled
if (!\ProjectSend\Classes\Encryption::isEnabled()) {
    $message = __('File encryption is not enabled.', 'cftp_admin') . ' ';
    $message .= '<a href="' . BASE_URI . 'options.php?section=encryption" class="btn btn-sm btn-primary ms-2">';
    $message .= '<i class="fa fa-cog"></i> ' . __('Enable Encryption', 'cftp_admin');
    $message .= '</a>';
    $flash->warning($message);
}

// Get all unencrypted files with pagination
global $dbh;

// Count total unencrypted files
$count_query = "SELECT COUNT(*) FROM " . TABLE_FILES . " WHERE encrypted = 0";
$count_statement = $dbh->prepare($count_query);
$count_statement->execute();
$total_files = $count_statement->fetchColumn();

// Pagination setup
$pagination_page = (isset($_GET["page"])) ? (int)$_GET["page"] : 1;
$allowed_per_page = [5, 10, 20, 50];
$pagination_results_per_page = (isset($_GET["per_page"]) && in_array((int)$_GET["per_page"], $allowed_per_page)) ? (int)$_GET["per_page"] : 10;
$pagination_start = ($pagination_page - 1) * $pagination_results_per_page;

// Get paginated results
$query = "SELECT id, filename, url, CONCAT(original_url, filename) as full_path, size
          FROM " . TABLE_FILES . "
          WHERE encrypted = 0
          ORDER BY timestamp DESC
          LIMIT :limit_start, :limit_number";

$statement = $dbh->prepare($query);
$statement->bindParam(':limit_start', $pagination_start, PDO::PARAM_INT);
$statement->bindParam(':limit_number', $pagination_results_per_page, PDO::PARAM_INT);
$statement->execute();
$unencrypted_files = $statement->fetchAll(PDO::FETCH_ASSOC);
$count = $statement->rowCount();

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>

<?php addCsrf(); ?>

<div class="row">
    <div class="col-12">
        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i>
            <strong><?php _e('Batch File Encryption', 'cftp_admin'); ?></strong><br>
            <?php _e('This tool will encrypt all files that are currently stored unencrypted. Files are encrypted one by one to prevent server timeout. This process cannot be undone - files will remain encrypted after this operation.', 'cftp_admin'); ?>
            <?php if ($total_files > $pagination_results_per_page): ?>
                <br><br>
                <strong><?php _e('Note:', 'cftp_admin'); ?></strong>
                <?php echo sprintf(__('Showing %d files per page. You can encrypt files on this page, then move to the next page to continue.', 'cftp_admin'), $pagination_results_per_page); ?>
            <?php endif; ?>
        </div>

        <?php if ($total_files > 0): ?>
        <div class="row mt-4 form_actions_count">
            <div class="col-12 col-md-6">
                <p>
                    <?php echo sprintf(__('Found %d elements', 'cftp_admin'), $total_files); ?>
                    <?php if ($total_files > $pagination_results_per_page): ?>
                        <small class="text-muted">(<?php echo sprintf(__('showing %d on this page', 'cftp_admin'), $count); ?>)</small>
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-12 col-md-6">
                <div class="row row-cols-lg-auto g-3 justify-content-end align-content-end">
                    <div class="col">
                        <label for="per-page-selector" class="form-label"><?php _e('Show:', 'cftp_admin'); ?></label>
                    </div>
                    <div class="col">
                        <select id="per-page-selector" class="form-select form-select-sm">
                            <?php foreach ($allowed_per_page as $option): ?>
                                <option value="<?php echo $option; ?>" <?php echo ($pagination_results_per_page == $option) ? 'selected' : ''; ?>>
                                    <?php echo $option; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Section -->
        <div id="encryption-progress" style="display: none;">
            <div class="mb-3">
                <div class="progress" style="height: 30px;">
                    <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated"
                         role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                        <span id="progress-text">0%</span>
                    </div>
                </div>
            </div>
            <div id="current-file-status" class="alert alert-info">
                <span id="status-message"><?php _e('Preparing...', 'cftp_admin'); ?></span>
            </div>
        </div>

        <!-- Results Section -->
        <div id="encryption-results" style="display: none;">
            <h4><?php _e('Results', 'cftp_admin'); ?></h4>
            <div class="alert alert-success">
                <i class="fa fa-check"></i>
                <strong><?php _e('Success:', 'cftp_admin'); ?></strong>
                <span id="success-count">0</span> <?php _e('files encrypted', 'cftp_admin'); ?>
            </div>
            <div class="alert alert-danger" id="error-section" style="display: none;">
                <i class="fa fa-times"></i>
                <strong><?php _e('Errors:', 'cftp_admin'); ?></strong>
                <span id="error-count">0</span> <?php _e('files failed', 'cftp_admin'); ?>
                <ul id="error-list" class="mt-2"></ul>
            </div>
        </div>

        <!-- File List Table -->
                <?php
                // Generate the table using the Table class
                $table = new \ProjectSend\Classes\Layout\Table([
                    'id' => 'encrypt_files_tbl',
                    'class' => 'footable table',
                    'origin' => basename(__FILE__),
                ]);

                $thead_columns = array(
                    array(
                        'content' => __('File Name', 'cftp_admin'),
                    ),
                    array(
                        'content' => __('Size', 'cftp_admin'),
                        'hide' => 'phone',
                    ),
                    array(
                        'content' => __('Status', 'cftp_admin'),
                    ),
                    array(
                        'content' => __('Actions', 'cftp_admin'),
                        'hide' => 'phone',
                    ),
                );
                $table->thead($thead_columns);

                foreach ($unencrypted_files as $file) {
                    $table->addRow([
                        'data-file-id' => $file['id']
                    ]);

                    // File name
                    $table->addCell([
                        'content' => html_output($file['filename'])
                    ]);

                    // Size - handle NULL or invalid values
                    $size_display = ($file['size'] && is_numeric($file['size']))
                        ? format_file_size($file['size'])
                        : '<span class="text-muted">' . __('Unknown', 'cftp_admin') . '</span>';
                    $table->addCell([
                        'content' => $size_display
                    ]);

                    // Status
                    $table->addCell([
                        'content' => '<span class="badge bg-secondary file-status">' . __('Pending', 'cftp_admin') . '</span>',
                        'attributes' => ['class' => 'file-status-cell']
                    ]);

                    // Actions
                    $table->addCell([
                        'actions' => true,
                        'content' => '<button type="button" class="btn btn-sm btn-primary encrypt-single-btn" data-file-id="' . $file['id'] . '">
                            <i class="fa fa-lock"></i> <span class="button_label">' . __('Encrypt', 'cftp_admin') . '</span>
                        </button>'
                    ]);

                    $table->end_row();
                }

                echo $table->render();
                ?>

        <!-- Batch Encryption Buttons -->
        <div class="mt-3 mb-4">
            <button type="button" id="start-encryption" class="btn btn-primary">
                <i class="fa fa-lock"></i>
                <?php if ($total_files > $pagination_results_per_page): ?>
                    <?php echo sprintf(__('Encrypt Files on This Page (%d)', 'cftp_admin'), $count); ?>
                <?php else: ?>
                    <?php _e('Start Encrypting Files', 'cftp_admin'); ?>
                <?php endif; ?>
            </button>
            <button type="button" id="stop-encryption" class="btn btn-danger" style="display: none;">
                <i class="fa fa-stop"></i> <?php _e('Stop', 'cftp_admin'); ?>
            </button>
        </div>

        <?php
        // Pagination
        if ($total_files > $pagination_results_per_page) {
            $pagination = new \ProjectSend\Classes\Layout\Pagination;
            echo $pagination->make([
                'link' => 'encrypt-files.php?per_page=' . $pagination_results_per_page,
                'current' => $pagination_page,
                'item_count' => $total_files,
                'items_per_page' => $pagination_results_per_page,
            ]);
        }
        ?>
        <?php else: ?>
        <div class="alert alert-success">
            <i class="fa fa-check-circle"></i>
            <?php _e('All files are already encrypted. No action needed.', 'cftp_admin'); ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    let fileIds = <?php echo json_encode(array_column($unencrypted_files, 'id')); ?>;
    let currentIndex = 0;
    let successCount = 0;
    let errorCount = 0;
    let isStopped = false;

    // Per-page selector
    $('#per-page-selector').on('change', function() {
        const perPage = $(this).val();
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('per_page', perPage);
        currentUrl.searchParams.set('page', 1); // Reset to first page
        window.location.href = currentUrl.toString();
    });

    // Individual encrypt button
    $('.encrypt-single-btn').on('click', function() {
        const $btn = $(this);
        const fileId = $btn.data('file-id');
        const $row = $btn.closest('tr');
        const fileName = $row.find('td:first').text();

        if (!confirm('<?php _e('Are you sure you want to encrypt this file? This operation cannot be undone.', 'cftp_admin'); ?>')) {
            return;
        }

        // Disable button and show loading
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> <span class="button_label"><?php _e('Encrypting...', 'cftp_admin'); ?></span>');
        $row.find('.file-status-cell').html('<span class="badge bg-warning file-status"><i class="fa fa-spinner fa-spin"></i> <?php _e('Encrypting...', 'cftp_admin'); ?></span>');

        // Make AJAX call to encrypt this file
        $.ajax({
            url: 'process.php?do=encrypt_single_file',
            type: 'POST',
            data: {
                csrf_token: $('input[name="csrf_token"]').val(),
                file_id: fileId
            },
            success: function(response) {
                try {
                    let result = JSON.parse(response);
                    if (result.status === 'success') {
                        $row.find('.file-status-cell').html('<span class="badge bg-success file-status"><i class="fa fa-check"></i> <?php _e('Encrypted', 'cftp_admin'); ?></span>');
                        $btn.html('<i class="fa fa-check"></i> <span class="button_label"><?php _e('Done', 'cftp_admin'); ?></span>');

                        // Reload page after short delay to show updated list
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        $row.find('.file-status-cell').html('<span class="badge bg-danger file-status"><i class="fa fa-times"></i> <?php _e('Failed', 'cftp_admin'); ?></span>');
                        $btn.prop('disabled', false).html('<i class="fa fa-lock"></i> <span class="button_label"><?php _e('Encrypt', 'cftp_admin'); ?></span>');
                        alert('<?php _e('Error:', 'cftp_admin'); ?> ' + result.message);
                    }
                } catch (e) {
                    $row.find('.file-status-cell').html('<span class="badge bg-danger file-status"><i class="fa fa-times"></i> <?php _e('Failed', 'cftp_admin'); ?></span>');
                    $btn.prop('disabled', false).html('<i class="fa fa-lock"></i> <span class="button_label"><?php _e('Encrypt', 'cftp_admin'); ?></span>');
                    alert('<?php _e('Error: Invalid response', 'cftp_admin'); ?>');
                }
            },
            error: function() {
                $row.find('.file-status-cell').html('<span class="badge bg-danger file-status"><i class="fa fa-times"></i> <?php _e('Failed', 'cftp_admin'); ?></span>');
                $btn.prop('disabled', false).html('<i class="fa fa-lock"></i> <span class="button_label"><?php _e('Encrypt', 'cftp_admin'); ?></span>');
                alert('<?php _e('AJAX error occurred', 'cftp_admin'); ?>');
            }
        });
    });

    $('#start-encryption').click(function() {
        <?php if ($total_files > $pagination_results_per_page): ?>
        if (!confirm('<?php echo sprintf(__('Are you sure you want to encrypt the %d files on this page? This operation cannot be undone.', 'cftp_admin'), $count); ?>')) {
            return;
        }
        <?php else: ?>
        if (!confirm('<?php _e('Are you sure you want to encrypt all unencrypted files? This operation cannot be undone.', 'cftp_admin'); ?>')) {
            return;
        }
        <?php endif; ?>

        // Reset counters
        currentIndex = 0;
        successCount = 0;
        errorCount = 0;
        isStopped = false;

        // Show/hide elements
        $(this).hide();
        $('#stop-encryption').show();
        $('#encryption-progress').show();
        $('#encryption-results').hide();
        $('#error-section').hide();
        $('#error-list').empty();

        // Disable individual encrypt buttons during batch operation
        $('.encrypt-single-btn').prop('disabled', true);

        // Reset all file statuses
        $('.file-status-cell').html('<span class="badge bg-secondary file-status"><?php _e('Pending', 'cftp_admin'); ?></span>');

        // Start encryption
        encryptNextFile();
    });

    $('#stop-encryption').click(function() {
        isStopped = true;
        $(this).hide();
        $('#start-encryption').show();
        $('#status-message').text('<?php _e('Stopped by user', 'cftp_admin'); ?>');
        showResults();
    });

    function encryptNextFile() {
        if (isStopped || currentIndex >= fileIds.length) {
            // Finished
            showResults();
            return;
        }

        let fileId = fileIds[currentIndex];
        let $row = $('tr[data-file-id="' + fileId + '"]');
        let fileName = $row.find('td:first').text();

        // Update progress
        let progress = Math.round((currentIndex / fileIds.length) * 100);
        $('#progress-bar').css('width', progress + '%').attr('aria-valuenow', progress);
        $('#progress-text').text(progress + '%');
        $('#status-message').html('<?php _e('Encrypting:', 'cftp_admin'); ?> <strong>' + fileName + '</strong>');

        // Update row status
        $row.find('.file-status-cell').html('<span class="badge bg-warning file-status"><i class="fa fa-spinner fa-spin"></i> <?php _e('Encrypting...', 'cftp_admin'); ?></span>');

        // Make AJAX call to encrypt this file
        $.ajax({
            url: 'process.php?do=encrypt_single_file',
            type: 'POST',
            data: {
                csrf_token: $('input[name="csrf_token"]').val(),
                file_id: fileId
            },
            success: function(response) {
                try {
                    let result = JSON.parse(response);
                    if (result.status === 'success') {
                        successCount++;
                        $row.find('.file-status-cell').html('<span class="badge bg-success file-status"><i class="fa fa-check"></i> <?php _e('Encrypted', 'cftp_admin'); ?></span>');
                    } else {
                        errorCount++;
                        $row.find('.file-status-cell').html('<span class="badge bg-danger file-status"><i class="fa fa-times"></i> <?php _e('Failed', 'cftp_admin'); ?></span>');
                        $('#error-list').append('<li>' + fileName + ': ' + result.message + '</li>');
                    }
                } catch (e) {
                    errorCount++;
                    $row.find('.file-status-cell').html('<span class="badge bg-danger file-status"><i class="fa fa-times"></i> <?php _e('Failed', 'cftp_admin'); ?></span>');
                    $('#error-list').append('<li>' + fileName + ': Invalid response</li>');
                }

                // Move to next file
                currentIndex++;
                setTimeout(encryptNextFile, 100); // Small delay between files
            },
            error: function() {
                errorCount++;
                $row.find('.file-status-cell').html('<span class="badge bg-danger file-status"><i class="fa fa-times"></i> <?php _e('Failed', 'cftp_admin'); ?></span>');
                $('#error-list').append('<li>' + fileName + ': AJAX error</li>');

                // Move to next file
                currentIndex++;
                setTimeout(encryptNextFile, 100);
            }
        });
    }

    function showResults() {
        // Update progress to 100%
        $('#progress-bar').css('width', '100%').attr('aria-valuenow', 100);
        $('#progress-text').text('100%');
        $('#status-message').text('<?php _e('Complete', 'cftp_admin'); ?>');

        // Show results
        $('#encryption-results').show();
        $('#success-count').text(successCount);
        $('#error-count').text(errorCount);

        if (errorCount > 0) {
            $('#error-section').show();
        }

        // Hide stop button, show start button
        $('#stop-encryption').hide();
        $('#start-encryption').show();

        // Re-enable individual encrypt buttons
        $('.encrypt-single-btn').prop('disabled', false);

        // If this was a paginated view and we successfully encrypted files, suggest reload
        <?php if ($total_files > $pagination_results_per_page): ?>
        if (successCount > 0) {
            setTimeout(function() {
                if (confirm('<?php _e('Files encrypted successfully! Reload the page to see remaining unencrypted files?', 'cftp_admin'); ?>')) {
                    window.location.reload();
                }
            }, 1000);
        }
        <?php endif; ?>
    }
});
</script>

<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
