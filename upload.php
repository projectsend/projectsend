<?php
/**
 * Uploading files from computer, step 1
 * Shows the plupload form that handles the uploads and moves
 * them to a temporary folder. When the queue is empty, the user
 * is redirected to step 2, and prompted to enter the name,
 * description and client for each uploaded file.
 */
require_once 'bootstrap.php';

$active_nav = 'files';

$page_title = __('Upload files', 'cftp_admin');

$page_id = 'upload_form';

// Check if user is logged in
redirect_if_not_logged_in();

// Check if user has upload permission
if (!current_user_can('upload')) {
    // Special case: clients might be allowed to upload via global setting
    if (current_role_in(['Client']) && get_option('clients_can_upload') != 1) {
        exit_with_error_code(403);
    } else if (!current_role_in(['Client'])) {
        // Non-client without upload permission
        exit_with_error_code(403);
    }
}

if (LOADED_LANG != 'en') {
    $plupload_lang_file = 'vendor/moxiecode/plupload/js/i18n/' . LOADED_LANG . '.js';
    if (file_exists(ROOT_DIR . DS . $plupload_lang_file)) {
        add_asset('js', 'plupload_language', BASE_URI . '/' . $plupload_lang_file, '3.1.5', 'footer');
    }
}

// Encryption settings (needed for JavaScript below)
$encryption_enabled = \ProjectSend\Classes\Encryption::isEnabled();
$encryption_required = \ProjectSend\Classes\Encryption::isRequired();
$show_encryption_option = $encryption_enabled && !$encryption_required;

message_no_clients();

if (defined('UPLOAD_MAX_FILESIZE')) {
    $msg = __('Click on Add files to select all the files that you want to upload, and then click continue. On the next step, you will be able to set a name and description for each uploaded file. Remember that the maximum allowed file size (in mb.) is ', 'cftp_admin') . ' <strong>' . UPLOAD_MAX_FILESIZE . '</strong>';
    $flash->info($msg);
}

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
$chunk_size = get_option('upload_chunk_size');

// Calculate available disk quota
$user_disk_quota = CURRENT_USER_DISK_QUOTA; // In MB, 0 = unlimited
$user_disk_usage = CURRENT_USER_DISK_USAGE; // In bytes
$quota_available_bytes = 0;
$quota_unlimited = ($user_disk_quota == 0);

if (!$quota_unlimited) {
    $quota_bytes = $user_disk_quota * 1048576; // Convert MB to bytes
    $quota_available_bytes = $quota_bytes - $user_disk_usage;
    // Don't allow negative available space
    if ($quota_available_bytes < 0) {
        $quota_available_bytes = 0;
    }
}
?>
<script type="text/javascript">
            $(function() {
                // Disk quota information
                var quotaUnlimited = <?php echo $quota_unlimited ? 'true' : 'false'; ?>;
                var quotaAvailableBytes = <?php echo $quota_available_bytes; ?>;
                var quotaTotalBytes = <?php echo $quota_unlimited ? 0 : ($user_disk_quota * 1048576); ?>;
                var quotaUsedBytes = <?php echo $user_disk_usage; ?>;

                $("#uploader").pluploadQueue({
                    runtimes: 'html5',
                    url: 'includes/upload.process.php',
                    chunk_size: '<?php echo (!empty($chunk_size)) ? $chunk_size : '1'; ?>mb',
                    rename: true,
                    dragdrop: true,
                    multipart: true,
                    filters: {
                        max_file_size: '<?php echo UPLOAD_MAX_FILESIZE; ?>mb'
                        <?php
                        if (!user_can_upload_any_file_type(CURRENT_USER_ID)) {
                        ?>,
                            mime_types: [{
                                title: "Allowed files",
                                extensions: "<?php echo get_option('allowed_file_types'); ?>"
                            }]
                        <?php
                        }
                        ?>
                    },
                    //flash_swf_url: 'vendor/moxiecode/plupload/js/Moxie.swf',
                    //silverlight_xap_url: 'vendor/moxiecode/plupload/js/Moxie.xap',
                    preinit: {
                        Init: function(up, info) {
                            //$('#uploader_container').removeAttr("title");
                        }
                    },
                    init: {
                        FilesAdded: function(up, files) {
                            // Check disk quota before allowing files to be added
                            if (!quotaUnlimited) {
                                var totalSize = 0;
                                $.each(up.files, function(i, file) {
                                    totalSize += file.size;
                                });

                                if (totalSize > quotaAvailableBytes) {
                                    // Remove the newly added files
                                    $.each(files, function(i, file) {
                                        up.removeFile(file);
                                    });

                                    // Show error message
                                    var quotaAvailableMB = (quotaAvailableBytes / 1048576).toFixed(2);
                                    var quotaTotalMB = (quotaTotalBytes / 1048576).toFixed(2);
                                    var quotaUsedMB = (quotaUsedBytes / 1048576).toFixed(2);

                                    alert('<?php _e("Disk quota exceeded!", "cftp_admin"); ?>\n\n' +
                                          '<?php _e("Your quota:", "cftp_admin"); ?> ' + quotaTotalMB + ' MB\n' +
                                          '<?php _e("Current usage:", "cftp_admin"); ?> ' + quotaUsedMB + ' MB\n' +
                                          '<?php _e("Available:", "cftp_admin"); ?> ' + quotaAvailableMB + ' MB\n\n' +
                                          '<?php _e("Please remove some files before uploading more.", "cftp_admin"); ?>');

                                    return false;
                                }
                            }
                        },
                        BeforeUpload: function(up, file) {
                            up.settings.multipart_params = up.settings.multipart_params || {};

                            // Pass the CSRF token with each chunk
                            up.settings.multipart_params.csrf_token = $('#csrf_token').val();

                            // Pass the storage selection with each file upload
                            var selectedStorage = $('#selected_storage').val();
                            up.settings.multipart_params.storage_selection = selectedStorage;

                            // Pass the encryption setting with each file upload
                            var encryptFile = $('#encrypt_file').val();
                            up.settings.multipart_params.encrypt_file = encryptFile;
                        }
                    }
                });

                // Handle storage selection
                <?php if (current_user_can('upload_storage_select')): ?>
                $('#storage_selector').on('change', function() {
                    var selectedStorage = $(this).val();
                    $('#selected_storage').val(selectedStorage);
                    console.log('Storage selected:', selectedStorage);
                });

                // Set initial storage value
                $(document).ready(function() {
                    var initialStorage = $('#storage_selector').val() || '<?php echo get_option('default_upload_storage', 'local'); ?>';
                    $('#selected_storage').val(initialStorage);
                });
                <?php else: ?>
                // Set default storage for users without permission
                $(document).ready(function() {
                    $('#selected_storage').val('<?php echo get_option('default_upload_storage', 'local'); ?>');
                });
                <?php endif; ?>

                // Handle encryption checkbox
                <?php if ($show_encryption_option): ?>
                $('#encrypt_file_checkbox').on('change', function() {
                    var encryptEnabled = $(this).is(':checked') ? '1' : '0';
                    $('#encrypt_file').val(encryptEnabled);
                    console.log('Encryption enabled:', encryptEnabled);
                });

                // Set initial encryption value
                $(document).ready(function() {
                    var initialEncryption = $('#encrypt_file_checkbox').is(':checked') ? '1' : '0';
                    $('#encrypt_file').val(initialEncryption);
                });
                <?php elseif ($encryption_required): ?>
                // Encryption is required, always set to 1
                $(document).ready(function() {
                    $('#encrypt_file').val('1');
                });
                <?php endif; ?>
            });
</script>

<div class="row">
    <div class="col-12 col-lg-7">
        <?php include_once FORMS_DIR . DS . 'upload.php'; ?>
    </div>

    <div class="col-12 col-lg-5">
        <div id="upload-sidebar">
            <?php include_once FORMS_DIR . DS . 'upload-sidebar.php'; ?>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="after_form_buttons">
            <button type="submit" name="Submit" class="btn btn-wide btn-primary" id="btn-submit"><?php _e('Upload files','cftp_admin'); ?></button>
        </div>
        <div class="message message_info message_uploading">
            <p><?php _e("Your files are being uploaded! Progress indicators may take a while to update, but work is still being done behind the scenes.",'cftp_admin'); ?></p>
        </div>
    </div>
</div>

</form>

<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
