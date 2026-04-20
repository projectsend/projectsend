<?php
/**
 * Email options form configuration
 * Refactored to use array-based configuration - matches original exactly
 */

// Define the form sections and fields
$form_sections = [
    [
        'title' => __('"From" information', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'email',
                'name' => 'admin_email_address',
                'label' => __('E-mail address', 'cftp_admin'),
                'required' => true
            ],
            [
                'type' => 'text',
                'name' => 'mail_from_name',
                'label' => __('Name', 'cftp_admin'),
                'required' => true
            ]
        ]
    ],
    [
        'title' => __('System performance', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'checkbox',
                'name' => 'notifications_send_when_saving_files',
                'label' => __('Send "New file" email notifications during the file saving process.', 'cftp_admin'),
                'note' => __('By unchecking this option, notifications are not sent during the file uploading and editing operations which results in much faster page loading and a better user experience.', 'cftp_admin') . '<br><strong>' . __('Warning: only disable this setting if you have a cron job that takes care of sending the notifications.', 'cftp_admin') . '</strong>'
            ]
        ]
    ],
    [
        'title' => __('Send copies', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'checkbox',
                'name' => 'mail_copy_user_upload',
                'label' => __('When a system user uploads files', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'mail_copy_client_upload',
                'label' => __('When a client uploads files', 'cftp_admin')
            ],
            [
                'type' => 'custom',
                'name' => 'copy_note',
                'render_callback' => function($field) {
                    ?>
                    <div class="options_nested_note">
                        <p><?php _e('Define here who will receive copies of this emails. These are sent as BCC so neither recipient will see the other addresses.','cftp_admin'); ?></p>
                    </div>
                    <?php
                }
            ],
            [
                'type' => 'checkbox',
                'name' => 'mail_copy_main_user',
                'label' => __('Address supplied above (on "From")', 'cftp_admin'),
                'class' => 'mail_copy_main_user'
            ],
            [
                'type' => 'text',
                'name' => 'mail_copy_addresses',
                'label' => __('Also to this addresses', 'cftp_admin'),
                'class' => 'mail_data form-control',
                'note' => __('Separate e-mail addresses with a comma.', 'cftp_admin')
            ]
        ]
    ],

    [
        'title' => __('Expiration', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'number',
                'name' => 'notifications_max_tries',
                'label' => __('Maximum sending attempts', 'cftp_admin'),
                'min' => 1,
                'max' => 10,
                'step' => 1,
                'required' => true,
                'note' => __('Define how many times the system will attempt to send each notification.', 'cftp_admin')
            ],
            [
                'type' => 'number',
                'name' => 'notifications_max_days',
                'label' => __('Days before expiring', 'cftp_admin'),
                'min' => 0,
                'max' => 365,
                'step' => 1,
                'required' => true,
                'note' => __('Notifications older than this will not be sent.', 'cftp_admin') . '<br /><strong>' . __('Set to 0 to disable.', 'cftp_admin') . '</strong>'
            ],
            [
                'type' => 'number',
                'name' => 'notifications_max_emails_at_once',
                'label' => __('Max. emails to send at once', 'cftp_admin'),
                'min' => 0,
                'max' => 10000,
                'step' => 1,
                'required' => true,
                'note' => __('Sending too many emails at once can lead to issues. If you set up a notifications cron job, you can set this to a convenient, safe amount of emails to attempt to send per run (ie: 20).', 'cftp_admin') . '<br /><strong>' . __('Set to 0 to disable.', 'cftp_admin') . '</strong>'
            ]
        ]
    ],
    [
        'title' => __('E-mail sending options', 'cftp_admin'),
        'description' => __('Here you can select which mail system will be used when sending the notifications. If you have a valid e-mail account, SMTP is the recommended option.', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'custom',
                'name' => 'mail_system_use',
                'render_callback' => function($field) {
                    ?>
                    <div class="form-group row">
                        <label for="mail_system_use" class="col-sm-4 control-label"><?php _e('Mailer','cftp_admin'); ?></label>
                        <div class="col-sm-8">
                          <?php if ((!empty($_ENV['MAIL_SYSTEM_USE']) || !empty($_ENV['MAIL_SMTP_HOST'])))
                            echo '
                                <div class="alert alert-warning" role="alert">
                                  Settings overwritten by enviroment variables.
                                </div>';
                          ?>
                            <select class="form-select" name="mail_system_use" id="mail_system_use" required>
                                <option value="mail" <?php echo (get_option('mail_system_use') == 'mail') ? 'selected="selected"' : ''; ?>>PHP Mail (basic)</option>
                                <option value="smtp" <?php echo (get_option('mail_system_use') == 'smtp') ? 'selected="selected"' : ''; ?>>SMTP</option>
                                <option value="gmail" <?php echo (get_option('mail_system_use') == 'gmail') ? 'selected="selected"' : ''; ?>>Gmail</option>
                                <option value="sendmail" <?php echo (get_option('mail_system_use') == 'sendmail') ? 'selected="selected"' : ''; ?>>Sendmail</option>
                            </select>
                        </div>
                    </div>
                    <?php
                }
            ],
            [
                'type' => 'text',
                'name' => 'mail_smtp_user',
                'label' => __('Username', 'cftp_admin'),
                'class' => 'mail_data form-control',
                'wrapper_class' => 'form-group row mail-auth-field',
                'note' => __('Usually your e-mail address', 'cftp_admin')
            ],
            [
                'type' => 'custom',
                'name' => 'mail_smtp_pass',
                'render_callback' => function($field) {
                    ?>
                    <div class="form-group row mail-auth-field">
                        <label for="mail_smtp_pass" class="col-sm-4 control-label"><?php _e('Password','cftp_admin'); ?></label>
                        <div class="col-sm-8">
                            <input type="password" name="mail_smtp_pass" id="mail_smtp_pass" class="mail_data form-control" value="<?php echo html_output(get_option('mail_smtp_pass')); ?>" />
                        </div>
                    </div>
                    <?php
                }
            ]
        ]
    ],
    [
        'title' => __('SMTP options', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'text',
                'name' => 'mail_smtp_host',
                'label' => __('Host', 'cftp_admin'),
                'class' => 'mail_data form-control',
                'wrapper_class' => 'form-group row mail-smtp-field'
            ],
            [
                'type' => 'text',
                'name' => 'mail_smtp_port',
                'label' => __('Port', 'cftp_admin'),
                'class' => 'mail_data form-control',
                'wrapper_class' => 'form-group row mail-smtp-field'
            ],
            [
                'type' => 'select',
                'name' => 'mail_smtp_auth',
                'label' => __('Authentication', 'cftp_admin'),
                'options' => [
                    'none' => __('No Authentication', 'cftp_admin'),
                    'use' => __('Username/Password', 'cftp_admin')
                ],
                'required' => true,
                'wrapper_class' => 'form-group row mail-smtp-field'
            ],
            [
                'type' => 'select',
                'name' => 'mail_smtp_secure',
                'label' => __('Connection security', 'cftp_admin'),
                'options' => [
                    'none' => __('None', 'cftp_admin'),
                    'ssl' => 'SMTPS',
                    'tls' => 'STARTTLS'
                ],
                'required' => true,
                'wrapper_class' => 'form-group row mail-smtp-field'
            ],
            [
                'type' => 'checkbox',
                'name' => 'mail_ssl_verify_peer',
                'label' => __('Verify peer', 'cftp_admin'),
                'class' => 'mail_ssl_verify_peer',
                'wrapper_class' => 'form-group row mail-smtp-field'
            ],
            [
                'type' => 'checkbox',
                'name' => 'mail_ssl_verify_peer_name',
                'label' => __('Verify peer name', 'cftp_admin'),
                'class' => 'mail_ssl_verify_peer_name',
                'wrapper_class' => 'form-group row mail-smtp-field'
            ],
            [
                'type' => 'checkbox',
                'name' => 'mail_ssl_allow_self_signed',
                'label' => __('Allow self signed', 'cftp_admin'),
                'class' => 'mail_ssl_allow_self_signed',
                'wrapper_class' => 'form-group row mail-smtp-field'
            ]
        ]
    ],
    [
        'fields' => [
            [
                'type' => 'custom',
                'name' => 'test_link',
                'render_callback' => function($field) {
                    ?>
                    <p class="warning">
                        <a href="email-test.php">
                            <?php _e('After saving your options, you can test your configuration here', 'cftp_admin'); ?>
                        </a>
                    </p>
                    <?php
                }
            ]
        ],
        'divider' => false // No divider at the end
    ]
];

// Render the form sections
render_options_form_sections($form_sections);
