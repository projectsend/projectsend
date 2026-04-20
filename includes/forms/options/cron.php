<?php
/**
 * Cron options form configuration
 * Refactored to use array-based configuration - matches original exactly
 */

// Define the form sections and fields
$form_sections = [
    [
        'title' => __('Tasks settings', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'custom',
                'name' => 'cron_enable',
                'render_callback' => function($field) {
                    ?>
                    <div class="form-group row">
                        <div class="col-sm-8 offset-sm-4">
                            <label for="cron_enable">
                                <input type="checkbox" value="1" name="cron_enable" id="cron_enable" class="checkbox_options" <?php echo (get_option('cron_enable') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e("Enable schedule tasks",'cftp_admin'); ?>
                            </label>

                            <p class="field_note form-text"><?php _e("Sample command to execute the cron job every 5 minutes. You may need to adjust the frequency and paths to match your server's settings.",'cftp_admin'); ?><br>
                            <?php _e("The >/dev/null part will discard the result and prevent you from getting a email from your OS after each run.",'cftp_admin'); ?>
                                <input type="text" class="form-control" readonly value="<?php echo CRON_COMMAND_EXAMPLE; ?>" id="cron_command_example"> <i class="fa fa-copy copy_text" data-target="cron_command_example"></i>
                            </p>
                        </div>
                    </div>
                    <?php
                }
            ],
            [
                'type' => 'custom',
                'name' => 'cron_command_line_only',
                'render_callback' => function($field) {
                    ?>
                    <div class="form-group row">
                        <div class="col-sm-8 offset-sm-4">
                            <label for="cron_command_line_only">
                                <input type="checkbox" value="1" name="cron_command_line_only" id="cron_command_line_only" class="checkbox_options" <?php echo (get_option('cron_command_line_only') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e("Enable cron execution via command line only",'cftp_admin'); ?>
                            </label>

                            <p class="field_note form-text"><?php _e("It's recommended to keep this checked to prevent unathorized executions of the tasks by accessing the URL directly.",'cftp_admin'); ?></p>

                            <p class="field_note form-text"><?php _e('If you disable this option, use the following URL to run your cron job via HTTP request:','cftp_admin'); ?><br>
                                <input type="text" class="form-control" readonly value="<?php echo CRON_URL; ?>" id="cron_command"> <i class="fa fa-copy copy_text" data-target="cron_command"></i>
                            </p>
                        </div>
                    </div>
                    <?php
                }
            ],
            [
                'type' => 'text',
                'name' => 'cron_key',
                'label' => __('Cron securiy key', 'cftp_admin'),
                'note' => __('This key must be present in the URL to validate the cron job and execute the required actions.', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'cron_send_emails',
                'label' => __("Send pending email notifications", 'cftp_admin'),
                'note' => __('Combine this option with the notifications setting "Max. emails to send at once" to throttle emails and prevent issues.', 'cftp_admin')
            ],
            [
                'type' => 'custom',
                'name' => 'cron_delete_expired_files',
                'render_callback' => function($field) {
                    ?>
                    <div class="form-group row">
                        <div class="col-sm-8 offset-sm-4">
                            <label for="cron_delete_expired_files">
                                <input type="checkbox" value="1" name="cron_delete_expired_files" id="cron_delete_expired_files" class="checkbox_options" <?php echo (get_option('cron_delete_expired_files') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e("Delete expired files",'cftp_admin'); ?>
                            </label>
                            <p class="field_note form-text"><?php echo sprintf(__("Current date/time according to your settings is %s.",'cftp_admin'), date('Y-m-d H:i:s')); ?></p>
                        </div>
                    </div>
                    <?php
                }
            ],
            [
                'type' => 'checkbox',
                'name' => 'cron_delete_orphan_files',
                'label' => __("Delete orphan files", 'cftp_admin')
            ],
            [
                'type' => 'select',
                'name' => 'cron_delete_orphan_files_types',
                'label' => __('Orphan files to delete:', 'cftp_admin'),
                'options' => [
                    'all' => __('All orphan files', 'cftp_admin'),
                    'not_allowed' => __('Only files with extensions that are not allowed', 'cftp_admin')
                ],
                'required' => true
            ]
        ]
    ],
    [
        'title' => __('Execution results', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'checkbox',
                'name' => 'cron_save_log_database',
                'label' => __("Save results on database", 'cftp_admin'),
                'note' => __('Important: each run of the cron job will add a record to the database. Watch the size of the cron log table and clean it if it gets too big.', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'cron_email_summary_send',
                'label' => __("E-mail results summary after each execution", 'cftp_admin')
            ],
            [
                'type' => 'text',
                'name' => 'cron_email_summary_address_to',
                'label' => __('E-mail to send the summary to', 'cftp_admin'),
                'note' => __('Leaving this field empty will send the results to the default "from" address.', 'cftp_admin')
            ]
        ],
        'divider' => false // No divider at the end
    ]
];

// Render the form sections
render_options_form_sections($form_sections);
