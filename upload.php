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

$allowed_levels = array(9, 8, 7);
if (get_option('clients_can_upload') == 1) {
    $allowed_levels[] = 0;
}

if (LOADED_LANG != 'en') {
    $plupload_lang_file = 'vendor/moxiecode/plupload/js/i18n/' . LOADED_LANG . '.js';
    if (file_exists(ROOT_DIR . DS . $plupload_lang_file)) {
        add_asset('js', 'plupload_language', BASE_URI . '/' . $plupload_lang_file, 'footer');
    }
}

message_no_clients();

$msg = __('Click on Add files to select all the files that you want to upload, and then click continue. On the next step, you will be able to set a name and description for each uploaded file. Remember that the maximum allowed file size (in mb.) is ', 'cftp_admin') . ' <strong>' . UPLOAD_MAX_FILESIZE . '</strong>';
$flash->info($msg);

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>
<div class="row">
    <div class="col-12">
        <script type="text/javascript">
            $(function() {
                $("#uploader").pluploadQueue({
                    runtimes: 'html5',
                    url: 'includes/upload.process.php',
                    chunk_size: '1mb',
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
                    flash_swf_url: 'vendor/moxiecode/plupload/js/Moxie.swf',
                    silverlight_xap_url: 'vendor/moxiecode/plupload/js/Moxie.xap',
                    preinit: {
                        Init: function(up, info) {
                            //$('#uploader_container').removeAttr("title");
                        }
                    },
                    init: {}
                });
            });
        </script>

        <?php include_once FORMS_DIR . DS . 'upload.php'; ?>
    </div>
</div>
<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
