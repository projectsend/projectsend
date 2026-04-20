<?php
/**
 * LDAP Authentication Configuration
 * Refactored to use array-based configuration - matches original exactly
 */

// Define the form sections and fields
$form_sections = [
    [
        'title' => __('Authentication Settings', 'cftp_admin'),
        'description' => sprintf(__('Configure LDAP/Active Directory authentication settings. Note: %s requires all accounts to be available locally. When a user connects via LDAP, a local account will be created automatically.', 'cftp_admin'), SYSTEM_NAME),
        'html_before' => '<div class="options_column">',
        'fields' => [
            [
                'type' => 'select',
                'name' => 'ldap_signin_enabled',
                'label' => __('Enable LDAP signin', 'cftp_admin'),
                'options' => [
                    'false' => __('No', 'cftp_admin'),
                    'true' => __('Yes', 'cftp_admin')
                ]
            ],
            [
                'type' => 'text',
                'name' => 'ldap_hosts',
                'label' => __('LDAP server', 'cftp_admin'),
                'placeholder' => 'ldap://server.domain.com:389',
                'note' => '<small class="form-text text-muted">' . __('LDAP server URL (e.g., ldap://server.domain.com:389 or ldaps://server.domain.com:636)', 'cftp_admin') . '</small>'
            ],
            [
                'type' => 'number',
                'name' => 'ldap_port',
                'label' => __('LDAP port', 'cftp_admin'),
                'placeholder' => '389',
                'min' => 1,
                'max' => 65535,
                'value' => get_option('ldap_port', null, '389'),
                'note' => '<small class="form-text text-muted">' . __('Standard ports: 389 (LDAP), 636 (LDAPS)', 'cftp_admin') . '</small>'
            ],
            [
                'type' => 'text',
                'name' => 'ldap_bind_dn',
                'label' => __('Base DN', 'cftp_admin'),
                'placeholder' => 'dc=company,dc=com',
                'note' => '<small class="form-text text-muted">' . __('Base Distinguished Name for LDAP searches', 'cftp_admin') . '</small>'
            ],
            [
                'type' => 'text',
                'name' => 'ldap_admin_user',
                'label' => __('Admin username', 'cftp_admin'),
                'placeholder' => 'cn=admin,dc=company,dc=com',
                'note' => '<small class="form-text text-muted">' . __('Admin user DN for binding to LDAP server', 'cftp_admin') . '</small>'
            ],
            [
                'type' => 'custom',
                'name' => 'ldap_admin_password',
                'render_callback' => function($field) {
                    ?>
                    <div class="form-group row">
                        <label for="ldap_admin_password" class="col-sm-4 control-label"><?php _e('Admin password','cftp_admin'); ?></label>
                        <div class="col-sm-8">
                            <input type="password" name="ldap_admin_password" id="ldap_admin_password" class="form-control" value="<?php echo get_option('ldap_admin_password'); ?>" />
                            <small class="form-text text-muted"><?php _e('Password for the admin user','cftp_admin'); ?></small>
                        </div>
                    </div>
                    <?php
                }
            ],
            [
                'type' => 'text',
                'name' => 'ldap_search_base',
                'label' => __('User search base', 'cftp_admin'),
                'placeholder' => 'ou=users,dc=company,dc=com',
                'note' => '<small class="form-text text-muted">' . __('Base DN where users are located', 'cftp_admin') . '</small>'
            ],
            [
                'type' => 'text',
                'name' => 'ldap_username_attribute',
                'label' => __('Username attribute', 'cftp_admin'),
                'placeholder' => 'uid',
                'value' => get_option('ldap_username_attribute', null, 'uid'),
                'note' => '<small class="form-text text-muted">' . __('Attribute used for username (uid, sAMAccountName, userPrincipalName)', 'cftp_admin') . '</small>'
            ],
            [
                'type' => 'text',
                'name' => 'ldap_search_filter',
                'label' => __('Search filter', 'cftp_admin'),
                'placeholder' => '(uid={username})',
                'value' => get_option('ldap_search_filter', null, '(uid={username})'),
                'note' => '<small class="form-text text-muted">' . __('Filter for finding users. Use {username} as placeholder', 'cftp_admin') . '</small>'
            ],
            [
                'type' => 'text',
                'name' => 'ldap_email_attribute',
                'label' => __('Email attribute', 'cftp_admin'),
                'placeholder' => 'mail',
                'value' => get_option('ldap_email_attribute', null, 'mail'),
                'note' => '<small class="form-text text-muted">' . __('Attribute containing user email address', 'cftp_admin') . '</small>'
            ],
            [
                'type' => 'text',
                'name' => 'ldap_name_attribute',
                'label' => __('Full name attribute', 'cftp_admin'),
                'placeholder' => 'cn',
                'value' => get_option('ldap_name_attribute', null, 'cn'),
                'note' => '<small class="form-text text-muted">' . __('Attribute containing user full name', 'cftp_admin') . '</small>'
            ],
            [
                'type' => 'text',
                'name' => 'ldap_account_suffix',
                'label' => __('Account suffix', 'cftp_admin'),
                'placeholder' => '@company.com',
                'note' => '<small class="form-text text-muted">' . __('Domain suffix added to usernames (optional, for Active Directory)', 'cftp_admin') . '</small>'
            ],
            [
                'type' => 'custom',
                'name' => 'ldap_use_tls',
                'render_callback' => function($field) {
                    ?>
                    <div class="form-group row">
                        <label for="ldap_use_tls" class="col-sm-4 control-label"><?php _e('Use TLS','cftp_admin'); ?></label>
                        <div class="col-sm-8">
                            <select class="form-select" name="ldap_use_tls" id="ldap_use_tls">
                                <option value="false" <?php echo (get_option('ldap_use_tls', null, 'false') == 'false') ? 'selected="selected"' : ''; ?>><?php _e('No','cftp_admin'); ?></option>
                                <option value="true" <?php echo (get_option('ldap_use_tls', null, 'false') == 'true') ? 'selected="selected"' : ''; ?>><?php _e('Yes','cftp_admin'); ?></option>
                            </select>
                            <small class="form-text text-muted"><?php _e('Use TLS encryption for LDAP connections','cftp_admin'); ?></small>
                        </div>
                    </div>
                    <?php
                }
            ]
        ],
        'html_after' => '</div>'
    ],
    [
        'title' => __('New Accounts', 'cftp_admin'),
        'description' => __('Configure settings for automatically created LDAP user accounts.', 'cftp_admin'),
        'html_before' => '<div class="options_column">',
        'fields' => [
            [
                'type' => 'custom',
                'name' => 'ldap_auto_create_users',
                'render_callback' => function($field) {
                    ?>
                    <div class="form-group row">
                        <label for="ldap_auto_create_users" class="col-sm-4 control-label"><?php _e('Auto-create LDAP users','cftp_admin'); ?></label>
                        <div class="col-sm-8">
                            <select class="form-select" name="ldap_auto_create_users" id="ldap_auto_create_users">
                                <option value="false" <?php echo (get_option('ldap_auto_create_users', null, 'true') == 'false') ? 'selected="selected"' : ''; ?>><?php _e('No','cftp_admin'); ?></option>
                                <option value="true" <?php echo (get_option('ldap_auto_create_users', null, 'true') == 'true') ? 'selected="selected"' : ''; ?>><?php _e('Yes','cftp_admin'); ?></option>
                            </select>
                            <small class="form-text text-muted"><?php _e('Automatically create local accounts for LDAP users','cftp_admin'); ?></small>
                        </div>
                    </div>
                    <?php
                }
            ],
            [
                'type' => 'custom',
                'name' => 'ldap_default_role',
                'render_callback' => function($field) {
                    // Get all active roles from database
                    $roles = \ProjectSend\Classes\Roles::getAllRoles(true);
                    // Get the client role ID as the default fallback
                    $client_role_id = \ProjectSend\Classes\Roles::getClientRoleId();
                    $current_role = get_option('ldap_default_role', null, $client_role_id);
                    ?>
                    <div class="form-group row">
                        <label for="ldap_default_role" class="col-sm-4 control-label"><?php _e('Default role for new LDAP users','cftp_admin'); ?></label>
                        <div class="col-sm-8">
                            <select class="form-select" name="ldap_default_role" id="ldap_default_role">
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>" <?php echo ($current_role == $role['id']) ? 'selected="selected"' : ''; ?>>
                                        <?php echo html_output($role['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted"><?php _e('Role assigned to new users created from LDAP','cftp_admin'); ?></small>
                        </div>
                    </div>
                    <?php
                }
            ],
            [
                'type' => 'custom',
                'name' => 'ldap_test_connection',
                'render_callback' => function($field) {
                    ?>
                    <div class="form-group row">
                        <div class="col-sm-4"></div>
                        <div class="col-sm-8">
                            <button type="button" class="btn btn-secondary" id="test_ldap_connection"><?php _e('Test LDAP Connection','cftp_admin'); ?></button><br>
                            <small class="form-text text-muted"><?php _e('Test the connection to your LDAP server','cftp_admin'); ?></small>
                            <div id="ldap_test_result" class="mt-2"></div>
                        </div>
                    </div>
                    <?php
                }
            ]
        ],
        'html_after' => '</div>
<script>
$(document).ready(function() {
    $("#test_ldap_connection").click(function() {
        var button = $(this);
        var originalText = button.text();
        var resultDiv = $("#ldap_test_result");

        button.prop("disabled", true);
        button.html(\'<i class="fa fa-cog fa-spin fa-fw"></i> <?php _e("Testing...", "cftp_admin"); ?>\');
        resultDiv.html("").removeClass("alert-success alert-danger");

        $.ajax({
            url: json_strings.uri.base + "process.php?do=test_ldap_connection",
            type: "POST",
            data: {
                csrf_token: document.getElementById("csrf_token").value
            },
            success: function(response) {
                var result = JSON.parse(response);
                if (result.status === "success") {
                    resultDiv.addClass("alert alert-success").html(\'<i class="fa fa-check"></i> \' + result.message);
                } else {
                    resultDiv.addClass("alert alert-danger").html(\'<i class="fa fa-times"></i> \' + result.message);
                }
            },
            error: function() {
                resultDiv.addClass("alert alert-danger").html(\'<i class="fa fa-times"></i> <?php _e("Connection test failed", "cftp_admin"); ?>\');
            },
            complete: function() {
                button.prop("disabled", false);
                button.html(originalText);
            }
        });
    });
});
</script>',
        'divider' => false // No divider at the end
    ]
];

// Render the form sections
render_options_form_sections($form_sections);