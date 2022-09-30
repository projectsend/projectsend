<?php
    $blocks = array(
        'social_networks' => array(
            'title' => __('Social Networks','cftp_admin'),
            'description' => sprintf(__('Note: %s requires all accounts to be available locally. When a user connects via a social network or any other external source, a local account will be created with a random password.','cftp_admin'), SYSTEM_NAME),
            'items' => array(
                'facebook' => array(
                    'name' => 'Facebook',
                    'icon' => 'facebook',
                    'fields' => array(
                        'enabled' => 'facebook_signin_enabled',
                        'id' => 'facebook_client_id',
                        'secret' => 'facebook_client_secret',
                    ),
                    'callback' => true,
                    'instructions' => LINK_DOC_FACEBOOK_LOGIN,
                ),
                'google' => array(
                    'name' => 'Google',
                    'icon' => 'google',
                    'fields' => array(
                        'enabled' => 'google_signin_enabled',
                        'id' => 'google_client_id',
                        'secret' => 'google_client_secret',
                    ),
                    'callback' => true,
                    'instructions' => LINK_DOC_GOOGLE_SIGN_IN,
                ),
                'linkedin' => array(
                    'name' => 'Linked In',
                    'icon' => 'linkedin',
                    'fields' => array(
                        'enabled' => 'linkedin_signin_enabled',
                        'id' => 'linkedin_client_id',
                        'secret' => 'linkedin_client_secret',
                    ),
                    'callback' => true,
                    'instructions' => LINK_DOC_LINKEDIN_LOGIN,
                ),
                'twitter' => array(
                    'name' => 'Twitter',
                    'icon' => 'twitter',
                    'fields' => array(
                        'enabled' => 'twitter_signin_enabled',
                        'id' => 'twitter_client_id',
                        'secret' => 'twitter_client_secret',
                    ),
                    'callback' => true,
                ),
                'windowslive' => array(
                    'name' => 'Windows Live',
                    'icon' => 'windows',
                    'fields' => array(
                        'enabled' => 'windowslive_signin_enabled',
                        'id' => 'windowslive_client_id',
                        'secret' => 'windowslive_client_secret',
                    ),
                    'callback' => true,
                ),
                'yahoo' => array(
                    'name' => 'Yahoo',
                    'icon' => 'yahoo',
                    'fields' => array(
                        'enabled' => 'yahoo_signin_enabled',
                        'id' => 'yahoo_client_id',
                        'secret' => 'yahoo_client_secret',
                    ),
                    'callback' => true,
                ),
                'microsoftgraph' => array(
                    'name' => 'Microsoft Graph (Azure)',
                    'icon' => 'windows',
                    'fields' => array(
                        'enabled' => 'microsoftgraph_signin_enabled',
                        'id' => 'microsoftgraph_client_id',
                        'secret' => 'microsoftgraph_client_secret',
                        'tenant' => 'microsoftgraph_client_tenant',
                    ),
                    'callback' => true,
                ),
            ),
        ),
    );

    foreach ($blocks as $block => $block_data) {
?>
        <h3><?php echo $block_data['title']; ?></h3>
        <p><?php echo $block_data['description']; ?></p>
<?php

        foreach ($block_data['items'] as $network => $item_data) {
            $enabled = get_option($item_data['fields']['enabled']);
?>
            <h5><i class="fa fa-<?php echo $item_data['icon']; ?>"></i> <?php echo $item_data['name']; ?></h5>

            <div class="options_column">
                <div class="form-group row">
                    <label for="<?php echo $item_data['fields']['enabled']; ?>" class="col-sm-4 control-label"><?php _e('Enabled','cftp_admin'); ?></label>
                    <div class="col-sm-8">
                        <select class="form-select" name="<?php echo $item_data['fields']['enabled']; ?>" id="<?php echo $item_data['fields']['enabled']; ?>">
                            <option value="true" <?php echo ($enabled == 'true') ? 'selected="selected"' : ''; ?>><?php _e('Yes','cftp_admin'); ?></option>
                            <option value="false" <?php echo (empty($enabled) || $enabled != 'true') ? 'selected="selected"' : ''; ?>><?php _e('No','cftp_admin'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="<?php echo $item_data['fields']['id']; ?>" class="col-sm-4 control-label"><?php _e('App ID','cftp_admin'); ?></label>
                    <div class="col-sm-8">
                        <input type="text" name="<?php echo $item_data['fields']['id']; ?>" id="<?php echo $item_data['fields']['id']; ?>" class="form-control empty" value="<?php echo html_output(get_option($item_data['fields']['id'])); ?>" />
                    </div>
                </div>
                <div class="form-group row">
                    <label for="<?php echo $item_data['fields']['secret']; ?>" class="col-sm-4 control-label"><?php _e('App Secret','cftp_admin'); ?></label>
                    <div class="col-sm-8">
                        <input type="text" name="<?php echo $item_data['fields']['secret']; ?>" id="<?php echo $item_data['fields']['secret']; ?>" class="form-control empty" value="<?php echo html_output(get_option($item_data['fields']['secret'])); ?>" />
                    </div>
                </div>
                <?php if (!empty($item_data['fields']['tenant'])) { ?>
                    <div class="form-group row">
                        <label for="<?php echo $item_data['fields']['tenant']; ?>" class="col-sm-4 control-label"><?php _e('App Tenant','cftp_admin'); ?></label>
                        <div class="col-sm-8">
                            <input type="text" name="<?php echo $item_data['fields']['tenant']; ?>" id="<?php echo $item_data['fields']['tenant']; ?>" class="form-control empty" value="<?php echo html_output(get_option($item_data['fields']['tenant'])); ?>" />
                        </div>
                    </div>
                <?php } ?>
                <?php if (!empty($item_data['callback'])) { ?>
                    <div class="form-group row">
                        <div class="col-sm-4">
                            <?php _e('Callback URI','cftp_admin'); ?>
                        </div>
                        <div class="col-sm-8">
                            <?php /*<span class="format_url"><?php echo BASE_URI . 'login-callback.php?service='.$network; ?></span>*/ ?>
                            <span class="format_url"><?php echo BASE_URI . 'login-callback.php'; ?></span>
                        </div>
                    </div>
                <?php } ?>
                <?php if (!empty($item_data['instructions'])) { ?>
                    <div class="form-group row">
                        <div class="col-sm-8 col-sm-offset-4">
                            <a href="<?php echo $item_data['instructions']; ?>" class="external_link" target="_blank"><?php _e('How do I obtain this credentials?','cftp_admin'); ?></a>
                        </div>
                    </div>
                <?php } ?>
            </div>
<?php
        }
    }

    // LDAP
    /*
?>
<div class="options_divide"></div>

<h3><?php _e('Other services','cftp_admin'); ?></h3>
<?php
    $ldap_enabled = get_option('ldap_signin_enabled');
?>
<h5><i class="fa fa-users"></i> LDAP</h5>
<p><?php _e('Please note that the "mail" attribute will be used to identify users', 'cftp_admin'); ?></p>

<div class="options_column">
    <div class="form-group row">
        <label for="ldap_signin_enabled" class="col-sm-4 control-label"><?php _e('Enabled','cftp_admin'); ?></label>
        <div class="col-sm-8">
            <select class="form-select" name="ldap_signin_enabled" id="ldap_signin_enabled">
                <option value="true" <?php echo ($ldap_enabled == 'true') ? 'selected="selected"' : ''; ?>><?php _e('Yes','cftp_admin'); ?></option>
                <option value="false" <?php echo (empty($ldap_enabled) || $ldap_enabled != 'true') ? 'selected="selected"' : ''; ?>><?php _e('No','cftp_admin'); ?></option>
            </select>
        </div>
    </div>
    <?php
        $ldap_fields = array(
            'server' => array(
                'label' => __('Server','cftp_admin'),
                'name' => 'ldap_server',
                'type' => 'text',
                'note' => sprintf(__('Enter the host name, including protocol and port if needed. Example format: %s', 'cftp_admin'), 'ldap://<server_address>:<port>')
            ),
            'bind_dn' => array(
                'label' => __('Bind DN','cftp_admin'),
                'name' => 'ldap_bind_dn',
                'type' => 'text',
            ),
            'admin_user' => array(
                'label' => __('Admin user','cftp_admin'),
                'name' => 'ldap_admin_user',
                'type' => 'text',
            ),
            'admin_password' => array(
                'label' => __('Admin password','cftp_admin'),
                'name' => 'ldap_admin_password',
                'type' => 'password',
            ),
            'search_base' => array(
                'label' => __('Search base','cftp_admin'),
                'name' => 'ldap_search_base',
                'type' => 'text',
                'note' => sprintf(__('The tree where to search users on. Eg: %s', 'cftp_admin'), 'cn=Users,dc=domain,dc=com'),
            ),
        );
        foreach ($ldap_fields as $field => $field_data) {
    ?>
            <div class="form-group row">
                <label for="<?php echo $field_data['name']; ?>" class="col-sm-4 control-label"><?php echo $field_data['label']; ?></label>
                <div class="col-sm-8">
                    <input type="<?php echo $field_data['type']; ?>" name="<?php echo $field_data['name']; ?>" id="<?php echo $field_data['name']; ?>" class="form-control empty" value="<?php echo html_output(get_option($field_data['name'])); ?>" />
                    <?php if (!empty($field_data['note'])) { ?>
                        <p class="field_note"><?php echo $field_data['note']; ?></p>
                    <?php } ?>
                </div>
            </div>
    <?php
        }

        $ldap_protocol_version = get_option('ldap_protocol_version');
    ?>
    <div class="form-group row">
        <label for="ldap_protocol_version" class="col-sm-4 control-label"><?php _e('Protocol Version','cftp_admin'); ?></label>
        <div class="col-sm-8">
            <select class="form-select" name="ldap_protocol_version" id="ldap_protocol_version">
                <option value="3" <?php echo (!empty($ldap_protocol_version) && $ldap_protocol_version == '3') ? 'selected="selected"' : ''; ?>><?php _e('3','cftp_admin'); ?></option>
                <option value="2" <?php echo (!empty($ldap_protocol_version) && $ldap_protocol_version == '2') ? 'selected="selected"' : ''; ?>><?php _e('2','cftp_admin'); ?></option>
            </select>
        </div>
    </div>
</div>
*/ ?>
<?php /*
<h5><i class="fa fa-openid"></i> OpenID</h5>
<?php
    $oidc_enabled = get_option('oidc_signin_enabled');
?>
<div class="options_column">
    <div class="form-group row">
        <label for="oidc_signin_enabled" class="col-sm-4 control-label"><?php _e('Enabled','cftp_admin'); ?></label>
        <div class="col-sm-8">
            <select class="form-select" name="oidc_signin_enabled" id="oidc_signin_enabled">
                <option value="true" <?php echo ($oidc_enabled == 'true') ? 'selected="selected"' : ''; ?>><?php _e('Yes','cftp_admin'); ?></option>
                <option value="false" <?php echo (empty($oidc_enabled) || $oidc_enabled != 'true') ? 'selected="selected"' : ''; ?>><?php _e('No','cftp_admin'); ?></option>
            </select>
        </div>
    </div>
    <?php
        $oidc_fields = array(
            'url' => array(
                'label' => __('Identifier URL','cftp_admin'),
                'name' => 'oidc_identifier_url',
            ),
        );
        foreach ($oidc_fields as $field => $field_data) {
    ?>
            <div class="form-group row">
                <label for="<?php echo $field_data['name']; ?>" class="col-sm-4 control-label"><?php echo $field_data['label']; ?></label>
                <div class="col-sm-8">
                    <input type="text" name="<?php echo $field_data['name']; ?>" id="<?php echo $field_data['name']; ?>" class="form-control empty" value="<?php echo html_output(get_option($field_data['name'])); ?>" />
                </div>
            </div>
    <?php
        }
    ?>
</div>
*/
