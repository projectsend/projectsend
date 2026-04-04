<?php
/**
 * Uploads options form configuration
 * Contains upload-related settings moved from general options
 */

// Define the form sections and fields for uploads
$form_sections = [
    [
        'title' => __('File Organization', 'cftp_admin'),
        'description' => __('Configure how uploaded files are organized and stored.', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'checkbox',
                'name' => 'uploads_organize_folders_by_date',
                'label' => __("Organize uploads in folders based on year and month", 'cftp_admin'),
                'note' => __("For new uploads only. Will not affect existing files.", 'cftp_admin')
            ],
            [
                'type' => 'select',
                'name' => 'upload_chunk_size',
                'label' => __('Chunk size', 'cftp_admin'),
                'options' => array_combine([1, 5, 10, 20, 50, 100], array_map(function($size) { return $size . ' mb.'; }, [1, 5, 10, 20, 50, 100])),
                'required' => true,
                'note' => __("Uploaded files are split into chunks which are then compiled on your server. Be sure to check by uploading one small and large files after changing this setting to make sure your internet connection and server can handle them.", 'cftp_admin')
            ],
            [
                'type' => 'custom',
                'name' => 'default_upload_storage',
                'render_callback' => function($field) {
                    $current_value = get_option('default_upload_storage', 'local');

                    echo '<div class="form-group row">';
                    echo '<label for="default_upload_storage" class="col-sm-4 control-label">' . __('Default storage destination', 'cftp_admin') . '</label>';
                    echo '<div class="col-sm-8">';
                    echo '<select name="default_upload_storage" id="default_upload_storage" class="form-select">';

                    // Local storage option
                    $selected = ($current_value === 'local') ? 'selected' : '';
                    echo '<option value="local" ' . $selected . '>' . __('Local storage', 'cftp_admin') . '</option>';

                    // External storage integrations
                    $integrations_handler = new \ProjectSend\Classes\Integrations();
                    $active_integrations = $integrations_handler->getAll(true); // Only active

                    foreach ($active_integrations as $integration) {
                        $selected = ($current_value === $integration['id']) ? 'selected' : '';
                        $type_config = \ProjectSend\Classes\Integrations::getTypeConfig($integration['type']);
                        $type_name = $type_config ? $type_config['name'] : ucfirst($integration['type']);
                        echo '<option value="' . $integration['id'] . '" ' . $selected . '>' .
                             html_output($integration['name']) . ' (' . $type_name . ')</option>';
                    }

                    echo '</select>';
                    echo '<div class="form-text">' . __('Default storage destination for users without storage selection permission. Users with permission can choose per upload.', 'cftp_admin') . '</div>';
                    echo '</div>';
                    echo '</div>';
                }
            ]
        ]
    ],
    [
        'title' => __('File Defaults', 'cftp_admin'),
        'description' => __('Default settings that will be applied to newly uploaded files.', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'checkbox',
                'name' => 'files_default_expire',
                'label' => __("Files expire by default", 'cftp_admin'),
                'note' => __('Users can always set an expiration date for files. This option just makes the checkbox marked by default in the editor.', 'cftp_admin') . ' ' .
                         __('For clients not allowed to set it, this setting will be directly applied to the file.', 'cftp_admin')
            ],
            [
                'type' => 'text',
                'name' => 'files_default_expire_days_after',
                'label' => __('After these many days:', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'files_default_public',
                'label' => __("Files are public by default", 'cftp_admin'),
                'note' => __('Users can always set a download to be public. This option just makes the checkbox marked by default in the editor.', 'cftp_admin') . ' ' .
                         __('For clients not allowed to set it, this setting will be directly applied to the file.', 'cftp_admin')
            ]
        ]
    ],
    [
        'title' => __('File Editor', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'checkbox',
                'name' => 'files_descriptions_use_ckeditor',
                'label' => __("Use the visual editor on files descriptions", 'cftp_admin')
            ]
        ]
    ],
    [
        'title' => __('Downloads', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'custom',
                'name' => 'download_method',
                'render_callback' => function($field) {
                    ?>
                    <div class="form-group row">
                        <label for="download_method" class="col-sm-4 control-label"><?php _e('Download method', 'cftp_admin'); ?></label>
                        <div class="col-sm-8">
                            <select class="form-select" name="download_method" id="download_method" required>
                                <option value="php" <?php echo (get_option('download_method') == 'php') ? 'selected="selected"' : ''; ?>>php</option>
                                <option value="apache_xsendfile" <?php echo (get_option('download_method') == 'apache_xsendfile') ? 'selected="selected"' : ''; ?>>XSendFile (apache)</option>
                                <option value="nginx_xaccel" <?php echo (get_option('download_method') == 'nginx_xaccel') ? 'selected="selected"' : ''; ?>>X-Accel (nginx)</option>
                                <option value="litespeed" <?php echo (get_option('download_method') == 'litespeed') ? 'selected="selected"' : ''; ?>>X-LiteSpeed-Location (LiteSpeed)</option>
                            </select>
                            <div class="method_note none" data-method="php">
                                <p class="field_note form-text"><?php _e("Serving files with php is the default method and does not require any changes to your webserver. However, very large files could download with errors depending on your php configuration.", 'cftp_admin'); ?></p>
                            </div>
                            <div class="method_note none" data-method="apache_xsendfile">
                                <p class="field_note form-text"><?php _e("XSendfile improves downloads by allowing the web server to send the file directly bypassing php and it's limitations. This in an advanced feature that requires you to install and enable a module on your server.", 'cftp_admin'); ?></p>
                                <p class="field_note form-text"><?php _e("Be aware that if the module is not set up correctly, downloads will trigger but the files will have a length of 0 bytes.", 'cftp_admin'); ?></p>
                            </div>
                            <div class="method_note none" data-method="litespeed">
                                <p class="field_note form-text"><?php _e("LiteSpeed's internal redirect allows the web server to serve files directly, bypassing PHP and its limitations. This is similar to Apache's XSendFile but uses the X-LiteSpeed-Location header.", 'cftp_admin'); ?></p>
                                <p class="field_note form-text"><?php _e("This method works out of the box on LiteSpeed/OpenLiteSpeed servers. Make sure that the upload directory is accessible by LiteSpeed.", 'cftp_admin'); ?></p>
                            </div>
                            <div class="method_note none" data-method="nginx_xaccel">
                                <p class="field_note form-text"><?php _e("X-Accel is a method available in nginx that allows the system to serve files directly, bypassing php and it's limitations. To configure it, you need to edit your server block and add the following code:", 'cftp_admin'); ?></p>
                                <pre>location <?php echo XACCEL_FILES_URL; ?> {
    internal;
    alias <?php echo UPLOADED_FILES_ROOT; ?>/;
}</pre>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            ],
            [
                'type' => 'checkbox',
                'name' => 'download_logging_ignore_file_author',
                'label' => __("Do not log downloads by the file's uploader", 'cftp_admin'),
                'note' => __("When a user or client downloads their own files, do not log the download or add to the downloads count.", 'cftp_admin')
            ]
        ],
        'divider' => false // No divider at the end
    ]
];

// Render the form sections
render_options_form_sections($form_sections);