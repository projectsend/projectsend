<?php
/**
 * Social Networks Login Configuration
 * Refactored to use array-based configuration - matches original exactly
 */

// Define the form sections and fields
$form_sections = [
    [
        'title' => __('Social Networks', 'cftp_admin'),
        'description' => sprintf(__('Note: %s requires all accounts to be available locally. When a user connects via a social network or any other external source, a local account will be created with a random password.', 'cftp_admin'), SYSTEM_NAME),
        'fields' => [
            [
                'type' => 'custom',
                'name' => 'social_networks_dynamic',
                'render_callback' => function($field) {
                    // Original social networks configuration
                    $social_networks = [
                        'facebook' => [
                            'name' => 'Facebook',
                            'icon' => 'facebook',
                            'fields' => [
                                'enabled' => 'facebook_signin_enabled',
                                'id' => 'facebook_client_id',
                                'secret' => 'facebook_client_secret',
                            ],
                            'callback' => true,
                            'instructions' => LINK_DOC_FACEBOOK_LOGIN,
                        ],
                        'google' => [
                            'name' => 'Google',
                            'icon' => 'google',
                            'fields' => [
                                'enabled' => 'google_signin_enabled',
                                'id' => 'google_client_id',
                                'secret' => 'google_client_secret',
                            ],
                            'callback' => true,
                            'instructions' => LINK_DOC_GOOGLE_SIGN_IN,
                        ],
                        'linkedin' => [
                            'name' => 'Linked In',
                            'icon' => 'linkedin',
                            'fields' => [
                                'enabled' => 'linkedin_signin_enabled',
                                'id' => 'linkedin_client_id',
                                'secret' => 'linkedin_client_secret',
                            ],
                            'callback' => true,
                            'instructions' => LINK_DOC_LINKEDIN_LOGIN,
                        ],
                        'x' => [
                            'name' => 'X (formerly Twitter)',
                            'icon' => 'twitter',
                            'fields' => [
                                'enabled' => 'x_signin_enabled',
                                'id' => 'x_client_id',
                                'secret' => 'x_client_secret',
                            ],
                            'callback' => true,
                            'note' => __('Requires OAuth2 credentials from developer.twitter.com — existing Twitter (OAuth1) keys will not work.', 'cftp_admin'),
                        ],
                        'windowslive' => [
                            'name' => 'Windows Live',
                            'icon' => 'windows',
                            'fields' => [
                                'enabled' => 'windowslive_signin_enabled',
                                'id' => 'windowslive_client_id',
                                'secret' => 'windowslive_client_secret',
                            ],
                            'callback' => true,
                        ],
                        'microsoftgraph' => [
                            'name' => 'Microsoft Graph',
                            'icon' => 'windows',
                            'fields' => [
                                'enabled' => 'microsoftgraph_signin_enabled',
                                'id' => 'microsoftgraph_client_id',
                                'secret' => 'microsoftgraph_client_secret',
                                'tenant' => 'microsoftgraph_client_tenant',
                            ],
                            'callback' => true,
                        ],
                        'yahoo' => [
                            'name' => 'Yahoo',
                            'icon' => 'yahoo',
                            'fields' => [
                                'enabled' => 'yahoo_signin_enabled',
                                'id' => 'yahoo_client_id',
                                'secret' => 'yahoo_client_secret',
                            ],
                            'callback' => true,
                        ],
                    ];

                    foreach ($social_networks as $item) {
                        $enabled_option = $item['fields']['enabled'];
                        $id_option = $item['fields']['id'];
                        $secret_option = $item['fields']['secret'];
                        $tenant_option = isset($item['fields']['tenant']) ? $item['fields']['tenant'] : null;

                        $enabled_value = get_option($enabled_option);
                        $id_value = get_option($id_option);
                        $secret_value = get_option($secret_option);
                        $tenant_value = $tenant_option ? get_option($tenant_option) : '';

                        $has_callback = isset($item['callback']) && $item['callback'];
                        $callback_url = $has_callback ? BASE_URI . 'login-callback.php' : '';
                        ?>

                        <h5><i class="fa fa-<?php echo $item['icon']; ?>"></i> <?php echo $item['name']; ?></h5>

                        <div class="options_column">
                            <div class="form-group row">
                                <label for="<?php echo $enabled_option; ?>" class="col-sm-4 control-label"><?php _e('Enabled','cftp_admin'); ?></label>
                                <div class="col-sm-8">
                                    <select class="form-select" name="<?php echo $enabled_option; ?>" id="<?php echo $enabled_option; ?>">
                                        <option value="false" <?php echo ($enabled_value == 'false') ? 'selected="selected"' : ''; ?>><?php _e('No','cftp_admin'); ?></option>
                                        <option value="true" <?php echo ($enabled_value == 'true') ? 'selected="selected"' : ''; ?>><?php _e('Yes','cftp_admin'); ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="<?php echo $id_option; ?>" class="col-sm-4 control-label"><?php _e('Client ID','cftp_admin'); ?></label>
                                <div class="col-sm-8">
                                    <input type="text" name="<?php echo $id_option; ?>" id="<?php echo $id_option; ?>" class="form-control" value="<?php echo $id_value; ?>" />
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="<?php echo $secret_option; ?>" class="col-sm-4 control-label"><?php _e('Client Secret','cftp_admin'); ?></label>
                                <div class="col-sm-8">
                                    <input type="password" name="<?php echo $secret_option; ?>" id="<?php echo $secret_option; ?>" class="form-control" value="<?php echo $secret_value; ?>" />
                                </div>
                            </div>

                            <?php if ($tenant_option) { ?>
                                <div class="form-group row">
                                    <label for="<?php echo $tenant_option; ?>" class="col-sm-4 control-label"><?php _e('Tenant ID','cftp_admin'); ?></label>
                                    <div class="col-sm-8">
                                        <input type="text" name="<?php echo $tenant_option; ?>" id="<?php echo $tenant_option; ?>" class="form-control" value="<?php echo $tenant_value; ?>" />
                                    </div>
                                </div>
                            <?php } ?>

                            <?php if ($has_callback) { ?>
                                <div class="form-group row">
                                    <label class="col-sm-4 control-label"><?php _e('Callback URL','cftp_admin'); ?></label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" value="<?php echo $callback_url; ?>" readonly />
                                        <small class="form-text text-muted"><?php _e('Use this URL when setting up your application','cftp_admin'); ?></small>
                                    </div>
                                </div>
                            <?php } ?>

                            <?php if (isset($item['instructions'])) { ?>
                                <div class="form-group row">
                                    <div class="col-sm-12">
                                        <a href="<?php echo $item['instructions']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                            <i class="fa fa-external-link"></i> <?php _e('Setup Instructions','cftp_admin'); ?>
                                        </a>
                                    </div>
                                </div>
                            <?php } ?>

                            <?php if (isset($item['note'])) { ?>
                                <div class="form-group row">
                                    <div class="col-sm-12">
                                        <div class="alert alert-warning py-2 mb-0">
                                            <i class="fa fa-exclamation-triangle"></i> <?php echo $item['note']; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>

                        <div class="options_divide"></div>

                        <?php
                    }
                }
            ]
        ]
    ],
    [
        'title' => __('Generic OIDC / SSO', 'cftp_admin'),
        'description' => __('Connect any OpenID Connect-compatible identity provider such as Keycloak, Authentik, Authelia, or Okta. Use your provider\'s issuer URL (the value of the "iss" claim).', 'cftp_admin'),
        'fields' => [
            [
                'type' => 'custom',
                'name' => 'oidc_settings',
                'render_callback' => function($field) {
                    $callback_url = BASE_URI . 'login-callback.php';
                    ?>
                    <div class="options_column">
                        <h5><i class="fa fa-lock"></i> <?php _e('OpenID Connect', 'cftp_admin'); ?></h5>

                        <div class="form-group row">
                            <label for="oidc_signin_enabled" class="col-sm-4 control-label"><?php _e('Enabled', 'cftp_admin'); ?></label>
                            <div class="col-sm-8">
                                <select class="form-select" name="oidc_signin_enabled" id="oidc_signin_enabled">
                                    <option value="false" <?php echo (get_option('oidc_signin_enabled') != 'true') ? 'selected="selected"' : ''; ?>><?php _e('No', 'cftp_admin'); ?></option>
                                    <option value="true" <?php echo (get_option('oidc_signin_enabled') == 'true') ? 'selected="selected"' : ''; ?>><?php _e('Yes', 'cftp_admin'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="oidc_display_name" class="col-sm-4 control-label"><?php _e('Button Label', 'cftp_admin'); ?></label>
                            <div class="col-sm-8">
                                <input type="text" name="oidc_display_name" id="oidc_display_name" class="form-control" value="<?php echo htmlspecialchars(get_option('oidc_display_name', null, 'SSO / OIDC'), ENT_QUOTES, 'UTF-8'); ?>" />
                                <small class="form-text text-muted"><?php _e('Label shown on the login page button', 'cftp_admin'); ?></small>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="oidc_issuer_url" class="col-sm-4 control-label"><?php _e('Issuer URL', 'cftp_admin'); ?></label>
                            <div class="col-sm-8">
                                <input type="url" name="oidc_issuer_url" id="oidc_issuer_url" class="form-control" value="<?php echo htmlspecialchars(get_option('oidc_issuer_url'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://auth.example.com/realms/myrealm" />
                                <small class="form-text text-muted"><?php _e('The OIDC issuer base URL. A discovery document must be available at {issuer}/.well-known/openid-configuration', 'cftp_admin'); ?></small>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="oidc_client_id" class="col-sm-4 control-label"><?php _e('Client ID', 'cftp_admin'); ?></label>
                            <div class="col-sm-8">
                                <input type="text" name="oidc_client_id" id="oidc_client_id" class="form-control" value="<?php echo htmlspecialchars(get_option('oidc_client_id'), ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="oidc_client_secret" class="col-sm-4 control-label"><?php _e('Client Secret', 'cftp_admin'); ?></label>
                            <div class="col-sm-8">
                                <input type="password" name="oidc_client_secret" id="oidc_client_secret" class="form-control" value="<?php echo htmlspecialchars(get_option('oidc_client_secret'), ENT_QUOTES, 'UTF-8'); ?>" />
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-sm-4 control-label"><?php _e('Callback URL', 'cftp_admin'); ?></label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" value="<?php echo $callback_url; ?>" readonly />
                                <small class="form-text text-muted"><?php _e('Register this as the redirect URI in your identity provider', 'cftp_admin'); ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="options_divide"></div>
                    <?php
                }
            ]
        ]
    ],
    [
        'title' => __('Additional Settings', 'cftp_admin'),
        'description' => __('Configure general behavior for social login users.', 'cftp_admin'),
        'html_before' => '<div class="options_column">',
        'fields' => [
            [
                'type' => 'custom',
                'name' => 'social_login_auto_enable',
                'render_callback' => function($field) {
                    ?>
                    <div class="form-group row">
                        <label for="social_login_auto_enable" class="col-sm-4 control-label"><?php _e('Auto-enable new users','cftp_admin'); ?></label>
                        <div class="col-sm-8">
                            <select class="form-select" name="social_login_auto_enable" id="social_login_auto_enable">
                                <option value="false" <?php echo (get_option('social_login_auto_enable', null, 'true') == 'false') ? 'selected="selected"' : ''; ?>><?php _e('No','cftp_admin'); ?></option>
                                <option value="true" <?php echo (get_option('social_login_auto_enable', null, 'true') == 'true') ? 'selected="selected"' : ''; ?>><?php _e('Yes','cftp_admin'); ?></option>
                            </select>
                            <small class="form-text text-muted"><?php _e('Automatically enable accounts created via social login','cftp_admin'); ?></small>
                        </div>
                    </div>
                    <?php
                }
            ],
            [
                'type' => 'custom',
                'name' => 'social_login_default_role',
                'render_callback' => function($field) {
                    ?>
                    <div class="form-group row">
                        <label for="social_login_default_role" class="col-sm-4 control-label"><?php _e('Default role for new social users','cftp_admin'); ?></label>
                        <div class="col-sm-8">
                            <select class="form-select" name="social_login_default_role" id="social_login_default_role">
                                <option value="0" <?php echo (get_option('social_login_default_role', null, '0') == '0') ? 'selected="selected"' : ''; ?>><?php _e('Client','cftp_admin'); ?></option>
                                <option value="7" <?php echo (get_option('social_login_default_role', null, '0') == '7') ? 'selected="selected"' : ''; ?>><?php _e('Uploader','cftp_admin'); ?></option>
                                <option value="8" <?php echo (get_option('social_login_default_role', null, '0') == '8') ? 'selected="selected"' : ''; ?>><?php _e('Account Manager','cftp_admin'); ?></option>
                            </select>
                            <small class="form-text text-muted"><?php _e('Role assigned to new users created from social login','cftp_admin'); ?></small>
                        </div>
                    </div>
                    <?php
                }
            ]
        ],
        'html_after' => '</div>',
        'divider' => false // No divider at the end
    ]
];

// Render the form sections
render_options_form_sections($form_sections);