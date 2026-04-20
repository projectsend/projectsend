<?php
/**
 * File Encryption options form configuration
 */

// Check if encryption master key is configured
$encryption_key_configured = defined('ENCRYPTION_MASTER_KEY') && !empty(ENCRYPTION_MASTER_KEY);

// Show warning if encryption key is not configured
if (!$encryption_key_configured) {
?>
    <div class="alert alert-warning">
        <h5><i class="fa fa-exclamation-triangle"></i> <?php _e('Encryption Master Key Not Configured', 'cftp_admin'); ?></h5>
        <p><?php _e('To enable file encryption, you must add an encryption master key to your configuration file.', 'cftp_admin'); ?></p>
        <p><?php _e('Add the following line to', 'cftp_admin'); ?> <code>includes/sys.config.php</code>:</p>
        <pre class="bg-light p-2"><code>define('ENCRYPTION_MASTER_KEY', 'YOUR_GENERATED_KEY_HERE');</code></pre>
        <p><?php _e('To generate a secure key, run this command in your terminal:', 'cftp_admin'); ?></p>
        <pre class="bg-light p-2"><code>php -r "echo base64_encode(random_bytes(32));"</code></pre>
        <p class="mb-0"><strong><?php _e('Important:', 'cftp_admin'); ?></strong> <?php _e('Keep a secure backup of this key. If lost, encrypted files cannot be recovered.', 'cftp_admin'); ?></p>
    </div>
<?php
}

// Define the form sections and fields
$form_sections = [
    [
        'title' => __('Encryption Settings', 'cftp_admin'),
        'description' => __('Enable server-side encryption for uploaded files. Files are encrypted at rest using AES-256-GCM and automatically decrypted when downloaded.', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'checkbox',
                'name' => 'files_encryption_enabled',
                'label' => __('Enable file encryption feature', 'cftp_admin'),
                'note' => __('When enabled, the encryption feature becomes available. Files can be encrypted based on the setting below.', 'cftp_admin'),
                'disabled' => !$encryption_key_configured,
            ],
            [
                'type' => 'checkbox',
                'name' => 'files_encryption_required',
                'label' => __('Make encryption mandatory', 'cftp_admin'),
                'note' => __('When enabled, ALL uploaded files will be automatically encrypted. When disabled, users can choose whether to encrypt each file during upload. Note: The encryption feature must be enabled above for this option to work.', 'cftp_admin')
            ],
            [
                'type' => 'number',
                'name' => 'files_encryption_max_file_size',
                'label' => __('Maximum file size for encryption (MB)', 'cftp_admin'),
                'min' => 0,
                'note' => __('Maximum file size that can be encrypted. Set to 0 for no limit. Note: Very large files may take longer to encrypt/decrypt.', 'cftp_admin'),
                'required' => true
            ]
        ]
    ],
    [
        'title' => __('Encryption Tools', 'cftp_admin'),
        'description' => __('Manage and encrypt existing files.', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'custom',
                'name' => 'encryption_tools',
                'render_callback' => function($field) {
                    ?>
                    <div class="form-group row">
                        <div class="col-sm-8 offset-sm-4">
                            <a href="<?php echo BASE_URI; ?>encrypt-files.php" class="btn btn-primary">
                                <i class="fa fa-lock"></i> <?php _e('Encrypt Unencrypted Files', 'cftp_admin'); ?>
                            </a>
                            <p class="field_note form-text mt-2">
                                <?php _e('Batch encrypt files that were uploaded before encryption was enabled.', 'cftp_admin'); ?>
                            </p>
                        </div>
                    </div>
                    <?php
                }
            ]
        ],
        'divider' => false
    ]
];

// Render the form sections
render_options_form_sections($form_sections);
