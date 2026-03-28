<?php
/**
 * Security options form configuration
 * Refactored to use array-based configuration - matches original exactly
 */

// Define the form sections and fields
$form_sections = [
    [
        'title' => __('Updates', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'checkbox',
                'name' => 'prevent_updates_check',
                'label' => __('Disable checking for new versions (use if your dashboard takes too long to load)', 'cftp_admin')
            ]
        ]
    ],
    [
        'title' => __('Allowed file extensions', 'cftp_admin'),
        'description' => __('Be careful when changing this options. They could affect not only the system but the whole server it is installed on.', 'cftp_admin') . '<br /><strong>' . __('Important', 'cftp_admin') . '</strong>: ' . __('Separate allowed file types with a comma.', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'select',
                'name' => 'file_types_limit_to',
                'label' => __('Limit file types uploading to', 'cftp_admin'),
                'options' => [
                    'noone' => __('No one', 'cftp_admin'),
                    'all' => __('Everyone', 'cftp_admin'),
                    'clients' => __('Clients only', 'cftp_admin')
                ],
                'required' => true
            ],
            [
                'type' => 'custom',
                'name' => 'allowed_file_types',
                'render_callback' => function($field) {
                    ?>
                    <div class="form-group row">
                        <input name="allowed_file_types" id="allowed_file_types" value="<?php echo get_option('allowed_file_types'); ?>" required />
                    </div>
                    <?php
                }
            ]
        ]
    ],
    [
        'title' => __('SVG files', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'checkbox',
                'name' => 'svg_show_as_thumbnail',
                'label' => __('Show thumbnails for SVG files', 'cftp_admin')
            ]
        ]
    ],

    [
        'title' => __('Passwords', 'cftp_admin'),
        'description' => __('When setting up a password for an account, require at least:', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'custom',
                'name' => 'pass_require_upper',
                'render_callback' => function($field) {
                    global $json_strings;
                    ?>
                    <div class="form-group row">
                        <div class="col-sm-8 offset-sm-4">
                            <label for="pass_require_upper">
                                <input type="checkbox" value="1" name="pass_require_upper" id="pass_require_upper" class="checkbox_options" <?php echo (get_option('pass_require_upper') == 1) ? 'checked="checked"' : ''; ?> /> <?php echo $json_strings['validation']['req_upper']; ?>
                            </label>
                        </div>
                    </div>
                    <?php
                }
            ],
            [
                'type' => 'custom',
                'name' => 'pass_require_lower',
                'render_callback' => function($field) {
                    global $json_strings;
                    ?>
                    <div class="form-group row">
                        <div class="col-sm-8 offset-sm-4">
                            <label for="pass_require_lower">
                                <input type="checkbox" value="1" name="pass_require_lower" id="pass_require_lower" class="checkbox_options" <?php echo (get_option('pass_require_lower') == 1) ? 'checked="checked"' : ''; ?> /> <?php echo $json_strings['validation']['req_lower']; ?>
                            </label>
                        </div>
                    </div>
                    <?php
                }
            ],
            [
                'type' => 'custom',
                'name' => 'pass_require_number',
                'render_callback' => function($field) {
                    global $json_strings;
                    ?>
                    <div class="form-group row">
                        <div class="col-sm-8 offset-sm-4">
                            <label for="pass_require_number">
                                <input type="checkbox" value="1" name="pass_require_number" id="pass_require_number" class="checkbox_options" <?php echo (get_option('pass_require_number') == 1) ? 'checked="checked"' : ''; ?> /> <?php echo $json_strings['validation']['req_number']; ?>
                            </label>
                        </div>
                    </div>
                    <?php
                }
            ],
            [
                'type' => 'custom',
                'name' => 'pass_require_special',
                'render_callback' => function($field) {
                    global $json_strings;
                    ?>
                    <div class="form-group row">
                        <div class="col-sm-8 offset-sm-4">
                            <label for="pass_require_special">
                                <input type="checkbox" value="1" name="pass_require_special" id="pass_require_special" class="checkbox_options" <?php echo (get_option('pass_require_special') == 1) ? 'checked="checked"' : ''; ?> /> <?php echo $json_strings['validation']['req_special']; ?>
                            </label>
                        </div>
                    </div>
                    <?php
                }
            ]
        ]
    ],

    [
        'title' => __('CAPTCHA', 'cftp_admin'),
        'description' => __('Helps prevent SPAM on your login, registration and password forgotten forms.', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'custom',
                'name' => 'captcha_method',
                'render_callback' => function($field) {
                    ?>
                    <div class="form-group row">
                        <label for="captcha_method" class="col-sm-4 control-label"><?php _e('Captcha method','cftp_admin'); ?></label>
                        <div class="col-sm-8">
                            <select class="form-select" name="captcha_method" id="captcha_method" required>
                                <option value="0" <?php echo (get_option('captcha_method') == '0' || get_option('captcha_method') == null || get_option('captcha_method') == '') ? 'selected="selected"' : ''; ?>><?php _e('Do not use captcha','cftp_admin'); ?></option>
                                <?php
                                    $methods = captcha_get_methods();
                                    foreach ($methods as $method => $method_class) {
                                        $object = new $method_class;
                                ?>
                                        <option value="<?php echo $method; ?>" <?php echo (get_option('captcha_method') == $method) ? 'selected="selected"' : ''; ?>><?php echo $object->getMethodName(); ?></option>
                                <?php
                                    }
                                ?>
                            </select>
                        </div>
                    </div>
                    <?php
                }
            ],
            [
                'type' => 'custom',
                'name' => 'captcha_sections',
                'render_callback' => function($field) {
                    ?>
                    <div id="captcha_recaptchav2" class="captcha_options_block <?php if (get_option('captcha_method') != 'recaptchav2') { ?>d-none<?php } ?>">
                        <div class="form-group row">
                            <label for="recaptcha_site_key" class="col-sm-4 control-label"><?php _e('Site key','cftp_admin'); ?></label>
                            <div class="col-sm-8">
                                <input type="text" name="recaptcha_site_key" id="recaptcha_site_key" class="form-control" value="<?php echo html_output(get_option('recaptcha_site_key')); ?>" />
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="recaptcha_secret_key" class="col-sm-4 control-label"><?php _e('Secret key','cftp_admin'); ?></label>
                            <div class="col-sm-8">
                                <input type="text" name="recaptcha_secret_key" id="recaptcha_secret_key" class="form-control" value="<?php echo html_output(get_option('recaptcha_secret_key')); ?>" />
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-sm-8 offset-sm-4">
                                <a href="<?php echo LINK_DOC_RECAPTCHA; ?>" class="external_link" target="_blank"><?php _e('How do I obtain this credentials?','cftp_admin'); ?></a>
                            </div>
                        </div>
                    </div>

                    <div id="captcha_recaptchav3" class="captcha_options_block <?php if (get_option('captcha_method') != 'recaptchav3') { ?>d-none<?php } ?>">
                        <div class="form-group row">
                            <label for="recaptcha_v3_site_key" class="col-sm-4 control-label"><?php _e('Site key','cftp_admin'); ?></label>
                            <div class="col-sm-8">
                                <input type="text" name="recaptcha_v3_site_key" id="recaptcha_v3_site_key" class="form-control" value="<?php echo html_output(get_option('recaptcha_v3_site_key')); ?>" />
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="recaptcha_v3_secret_key" class="col-sm-4 control-label"><?php _e('Secret key','cftp_admin'); ?></label>
                            <div class="col-sm-8">
                                <input type="text" name="recaptcha_v3_secret_key" id="recaptcha_v3_secret_key" class="form-control" value="<?php echo html_output(get_option('recaptcha_v3_secret_key')); ?>" />
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="recaptcha_v3_score_threshold" class="col-sm-4 control-label"><?php _e('Score threshold','cftp_admin'); ?></label>
                            <div class="col-sm-8">
                                <input type="number" name="recaptcha_v3_score_threshold" id="recaptcha_v3_score_threshold" class="form-control" value="<?php echo html_output(get_option('recaptcha_v3_score_threshold')); ?>" min="0" max="1" step="0.1" />
                                <p class="field_note form-text"><?php _e('Score between 0.0 (likely bot) and 1.0 (likely human). Default: 0.5','cftp_admin'); ?></p>
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-sm-8 offset-sm-4">
                                <a href="<?php echo LINK_DOC_RECAPTCHA; ?>" class="external_link" target="_blank"><?php _e('How do I obtain this credentials?','cftp_admin'); ?></a>
                            </div>
                        </div>
                    </div>

                    <div id="captcha_cloudflare_turnstile" class="captcha_options_block <?php if (get_option('captcha_method') != 'cloudflare_turnstile') { ?>d-none<?php } ?>">
                        <div class="form-group row">
                            <label for="cloudflare_turnstile_site_key" class="col-sm-4 control-label"><?php _e('Site key','cftp_admin'); ?></label>
                            <div class="col-sm-8">
                                <input type="text" name="cloudflare_turnstile_site_key" id="cloudflare_turnstile_site_key" class="form-control" value="<?php echo html_output(get_option('cloudflare_turnstile_site_key')); ?>" />
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="cloudflare_turnstile_secret_key" class="col-sm-4 control-label"><?php _e('Secret key','cftp_admin'); ?></label>
                            <div class="col-sm-8">
                                <input type="text" name="cloudflare_turnstile_secret_key" id="cloudflare_turnstile_secret_key" class="form-control" value="<?php echo html_output(get_option('cloudflare_turnstile_secret_key')); ?>" />
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-sm-8 offset-sm-4">
                                <a href="<?php echo LINK_DOC_TURNSTILE; ?>" class="external_link" target="_blank"><?php _e('How do I obtain this credentials?','cftp_admin'); ?></a>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            ]
        ]
    ],

    [
        'title' => __('Two-factor authentication', 'cftp_admin'),
        'description' => __('Configure two-factor authentication methods available to users.', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'checkbox',
                'name' => 'two_factor_required',
                'label' => __('Require 2FA for all users', 'cftp_admin'),
                'note' => __('When enabled, all users must verify with a second factor after entering their credentials. If a user has not configured an authenticator app, an email code will be used as fallback.', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'two_factor_allow_email',
                'label' => __('Allow email-based codes', 'cftp_admin'),
                'note' => __('Send a one-time verification code via email.', 'cftp_admin')
            ],
            [
                'type' => 'checkbox',
                'name' => 'two_factor_allow_totp',
                'label' => __('Allow authenticator apps (TOTP)', 'cftp_admin'),
                'note' => __('Allow users to set up apps like Google Authenticator, Authy, or 1Password for verification codes.', 'cftp_admin')
            ]
        ]
    ],
    [
        'title' => __('Remember Me', 'cftp_admin'),
        'description' => __('Allow users to stay logged in across browser sessions using secure tokens.', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'checkbox',
                'name' => 'remember_me_enabled',
                'label' => __('Enable "Remember Me" functionality', 'cftp_admin'),
                'note' => __('Users will be able to stay logged in for extended periods using secure, rotating tokens.', 'cftp_admin')
            ],
            [
                'type' => 'number',
                'name' => 'remember_me_duration_days',
                'label' => __('Token duration (days)', 'cftp_admin'),
                'min' => 1,
                'max' => 365,
                'note' => __('How many days a remember me token remains valid. Default: 30 days.', 'cftp_admin')
            ],
            [
                'type' => 'number',
                'name' => 'remember_me_max_tokens_per_user',
                'label' => __('Max tokens per user', 'cftp_admin'),
                'min' => 1,
                'max' => 20,
                'note' => __('Maximum number of devices/browsers a user can stay logged in to simultaneously. Default: 5.', 'cftp_admin')
            ]
        ]
    ],
    [
        'title' => __('Log in throttle', 'cftp_admin'),
        'description' => __('Multiple failed log in attempts will increase timeouts for the originating IP address. Helps prevent brute force attacks.', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'custom',
                'name' => 'ip_whitelist',
                'render_callback' => function($field) {
                    ?>
                    <div class="form-group row">
                        <label for="ip_whitelist" class="col-sm-4 control-label"><?php _e('IP whitelist','cftp_admin'); ?></label>
                        <div class="col-sm-8 offset-sm-4">
                            <textarea name="ip_whitelist" id="ip_whitelist" class="form-control textarea_medium"><?php echo html_output(get_option('ip_whitelist')); ?></textarea>
                            <p class="field_note form-text"><?php _e('Enter one IP address per line','cftp_admin'); ?>.
                        </div>
                    </div>
                    <?php
                }
            ],
            [
                'type' => 'custom',
                'name' => 'ip_blacklist',
                'render_callback' => function($field) {
                    ?>
                    <div class="form-group row">
                        <label for="ip_blacklist" class="col-sm-4 control-label"><?php _e('IP blacklist','cftp_admin'); ?></label>
                        <div class="col-sm-8 offset-sm-4">
                            <textarea name="ip_blacklist" id="ip_blacklist" class="form-control textarea_medium"><?php echo html_output(get_option('ip_blacklist')); ?></textarea>
                            <p class="field_note form-text"><?php _e('Enter one IP address per line','cftp_admin'); ?>.
                        </div>
                    </div>
                    <?php
                }
            ]
        ],
        'divider' => false // No divider at the end
    ]
];

// Render the form sections
render_options_form_sections($form_sections);
