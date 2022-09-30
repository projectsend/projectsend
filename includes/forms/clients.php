<?php
/**
 * Contains the form that is used when adding or editing clients.
 */
$name_placeholder = __("Will be visible on the client's file list", 'cftp_admin');

$clients_can_select_group = get_option('clients_can_select_group');

switch ($clients_form_type) {
        /** User is creating a new client */
    case 'new_client':
        $submit_value = __('Add client', 'cftp_admin');
        $disable_user = false;
        $require_pass = true;
        $form_action = 'clients-add.php';
        $info_box = true;
        $extra_fields = true;
        $group_field = true;
        $group_label = __('Groups', 'cftp_admin');
        $ignore_size = false;
        break;
        /** User is editing an existing client */
    case 'edit_client':
        $submit_value = __('Save client', 'cftp_admin');
        $disable_user = true;
        $require_pass = false;
        $form_action = 'clients-edit.php?id=' . $client_id;
        $info_box = false;
        $extra_fields = true;
        $group_field = true;
        $group_label = __('Groups', 'cftp_admin');
        $ignore_size = false;
        break;
        /** A client is creating a new account for himself */
    case 'new_client_self':
        $submit_value = (get_option('clients_auto_approve') == 1) ? __('Create account', 'cftp_admin') : __('Request account', 'cftp_admin');
        $disable_user = false;
        $require_pass = true;
        $form_action = 'register.php';
        $info_box = true;
        $extra_fields = false;
        $name_placeholder = __("Your full name", 'cftp_admin');
        $group_field = false;
        if ($clients_can_select_group == 'public' || $clients_can_select_group == 'all') {
            $group_field = true;
            $group_label = __('Request access to groups', 'cftp_admin');
        }
        break;
        /** A client is editing their profile */
    case 'edit_client_self':
        $submit_value = __('Update account', 'cftp_admin');
        $disable_user = true;
        $require_pass = false;
        $form_action = 'clients-edit.php?id=' . $client_id;
        $info_box = false;
        $extra_fields = false;
        $group_field = false;
        if ($clients_can_select_group == 'public' || $clients_can_select_group == 'all') {
            $group_field = true;
            $group_label = __('Request access to groups', 'cftp_admin');
            $override_groups_list = (!empty($found_requests[$client_id]['group_ids'])) ? $found_requests[$client_id]['group_ids'] : null;
        }
        $ignore_size = true;
        break;
}
?>

<form action="<?php echo html_output($form_action); ?>" name="client_form" id="client_form" method="post" class="form-horizontal" data-form-type="<?php echo $clients_form_type; ?>">
    <?php addCsrf(); ?>

    <div class="form-group row">
        <label for="name" class="col-sm-4 control-label"><?php _e('Name', 'cftp_admin'); ?></label>
        <div class="col-sm-8">
            <input type="text" name="name" id="name" class="form-control required" value="<?php echo (isset($client_arguments['name'])) ? format_form_value($client_arguments['name']) : ''; ?>" placeholder="<?php echo $name_placeholder; ?>" required />
        </div>
    </div>

    <div class="form-group row">
        <label for="username" class="col-sm-4 control-label"><?php _e('Log in username', 'cftp_admin'); ?></label>
        <div class="col-sm-8">
            <input type="text" name="username" id="username" class="form-control <?php if (!$disable_user) { echo 'required'; } ?>" maxlength="<?php echo MAX_USER_CHARS; ?>" value="<?php echo (isset($client_arguments['username'])) ? format_form_value($client_arguments['username']) : ''; ?>" <?php if ($disable_user) { echo 'readonly'; } ?> placeholder="<?php _e("Must be alphanumeric", 'cftp_admin'); ?>" required />
        </div>
    </div>

    <div class="form-group row">
        <label for="password" class="col-sm-4 control-label"><?php _e('Password', 'cftp_admin'); ?></label>
        <div class="col-sm-8">
            <div class="input-group">
                <input type="password" name="password" id="password" class="form-control attach_password_toggler <?php if ($require_pass) { echo 'required'; } ?>" maxlength="<?php echo MAX_PASS_CHARS; ?>" />
            </div>
            <button type="button" name="generate_password" id="generate_password" class="btn btn-light btn-sm btn_generate_password" data-ref="password" data-min="<?php echo MAX_GENERATE_PASS_CHARS; ?>" data-max="<?php echo MAX_GENERATE_PASS_CHARS; ?>"><?php _e('Generate', 'cftp_admin'); ?></button>
            <?php echo password_notes(); ?>
        </div>
    </div>

    <div class="form-group row">
        <label for="email" class="col-sm-4 control-label"><?php _e('E-mail', 'cftp_admin'); ?></label>
        <div class="col-sm-8">
            <input type="email" name="email" id="email" class="form-control required" value="<?php echo (isset($client_arguments['email'])) ? format_form_value($client_arguments['email']) : ''; ?>" placeholder="<?php _e("Must be valid and unique", 'cftp_admin'); ?>" required />
        </div>
    </div>

    <div class="form-group row">
        <label for="address" class="col-sm-4 control-label"><?php _e('Address', 'cftp_admin'); ?></label>
        <div class="col-sm-8">
            <input type="text" name="address" id="address" class="form-control" value="<?php echo (isset($client_arguments['address'])) ? format_form_value($client_arguments['address']) : ''; ?>" />
        </div>
    </div>

    <div class="form-group row">
        <label for="phone" class="col-sm-4 control-label"><?php _e('Telephone', 'cftp_admin'); ?></label>
        <div class="col-sm-8">
            <input type="text" name="phone" id="phone" class="form-control" value="<?php echo (isset($client_arguments['phone'])) ? format_form_value($client_arguments['phone']) : ''; ?>" />
        </div>
    </div>

    <?php
    if ($extra_fields == true) {
    ?>
        <div class="form-group row">
            <label for="contact" class="col-sm-4 control-label"><?php _e('Internal contact name', 'cftp_admin'); ?></label>
            <div class="col-sm-8">
                <input type="text" name="contact" id="contact" class="form-control" value="<?php echo (isset($client_arguments['contact'])) ? format_form_value($client_arguments['contact']) : ''; ?>" />
            </div>
        </div>

        <div class="form-group row">
            <label for="max_file_size" class="col-sm-4 control-label"><?php _e('Max. upload filesize', 'cftp_admin'); ?></label>
            <div class="col-sm-8">
                <div class="input-group">
                    <input type="text" name="max_file_size" id="max_file_size" class="form-control" value="<?php echo (isset($client_arguments['max_file_size'])) ? format_form_value($client_arguments['max_file_size']) : '0'; ?>" />
                    <span class="input-group-addon">MB</span>
                </div>
                <p class="field_note"><?php _e("Set to 0 to use the default system limit", 'cftp_admin'); ?> (<?php echo MAX_FILESIZE; ?> MB)</p>
            </div>
        </div>
        <?php
    }

    if ($group_field == true) {
        /**
         * Make a list of public groups in case clients can only request
         * membership to those
         */
        $arguments = [];

        /** Groups to search on based on the current user level */
        $role = (defined('CURRENT_USER_LEVEL')) ? CURRENT_USER_LEVEL : null;
        if (!empty($role) && in_array($role, [8, 9])) {
            /** An admin or client manager is creating a client account */
        } else {
            /** Someone is registering an account for himself */
            if ($clients_can_select_group == 'public') {
                $arguments['public'] = true;
            }
        }

        $sql_groups = get_groups($arguments);

        $selected_groups = (!empty($found_groups)) ? $found_groups : '';
        $my_current_groups = [];
        /** Dirty and awful quick test, mark as selected the current groups which have requests for a client that's editing their own account */
        if (isset($override_groups_list)) {
            $selected_groups = $override_groups_list;
            if (!empty($found_groups)) {
                foreach ($sql_groups as $array_key => $sql_group) {
                    if (in_array($sql_group['id'], $found_groups)) {
                        $my_current_groups[] = $sql_group;
                        unset($sql_groups[$array_key]);
                    }
                }
            }
        }

        if (count($sql_groups) > 0) {
        ?>
            <div class="form-group row assigns">
                <label for="groups_request" class="col-sm-4 control-label"><?php echo $group_label; ?></label>
                <div class="col-sm-8">
                    <select class="form-select chosen-select none" multiple="multiple" name="groups_request[]" id="groups-select" data-placeholder="<?php _e('Select one or more options. Type to search.', 'cftp_admin'); ?>">
                        <?php
                        foreach ($sql_groups as $group) {
                        ?>
                            <option value="<?php echo $group['id']; ?>" <?php if (!empty($selected_groups) && in_array($group['id'], $selected_groups)) { echo ' selected="selected"'; } ?>><?php echo $group['name']; ?></option>
                        <?php
                        }
                        ?>
                    </select>
                    <?php
                    if (!empty($role) && in_array($role, [8, 9])) {
                    ?>
                        <div class="list_mass_members">
                            <button type="button" class="btn btn-pslight add-all" data-target="groups-select"><?php _e('Add all', 'cftp_admin'); ?></button>
                            <button type="button" class="btn btn-pslight remove-all" data-target="groups-select"><?php _e('Remove all', 'cftp_admin'); ?></button>
                        </div>
                    <?php
                    }
                    ?>
                </div>
            </div>
    <?php
        }
    }

    if ($extra_fields == true) {
    ?>
        <div class="form-group row">
            <div class="col-sm-8 col-sm-offset-4">
                <label for="active">
                    <input type="checkbox" name="active" id="active" <?php echo (isset($client_arguments['active']) && $client_arguments['active'] == 1) ? 'checked="checked"' : ''; ?>> <?php _e('Active (client can log in)', 'cftp_admin'); ?>
                </label>
            </div>
        </div>

        <div class="form-group row">
            <div class="col-sm-8 col-sm-offset-4">
                <label for="can_upload_public">
                    <input type="checkbox" name="can_upload_public" id="can_upload_public" <?php echo (isset($client_arguments['can_upload_public']) && $client_arguments['can_upload_public'] == 1) ? 'checked="checked"' : ''; ?>> <?php _e('Can set own files as public', 'cftp_admin'); ?>
                </label>
                <?php if (get_option('clients_can_set_public') != 'allowed') { ?>
                    <p class="field_note"><?php _e("This has no effect according to your current settings.", 'cftp_admin'); ?> <a href="options.php?section=clients" target="blank"><?php _e("Go to settings", 'cftp_admin'); ?></a></p>
                <?php } ?>
            </div>
        </div>
    <?php
    }
    ?>

    <div class="form-group row">
        <div class="col-sm-8 col-sm-offset-4">
            <label for="notify_upload">
                <input type="checkbox" name="notify_upload" id="notify_upload" <?php echo (isset($client_arguments['notify_upload']) && $client_arguments['notify_upload'] == 1) ? 'checked="checked"' : ''; ?>> <?php _e('Notify new uploads by e-mail', 'cftp_admin'); ?>
            </label>
        </div>
    </div>

    <?php
    if ($clients_form_type == 'new_client') {
    ?>
        <div class="form-group row">
            <div class="col-sm-8 col-sm-offset-4">
                <label for="notify_account">
                    <input type="checkbox" name="notify_account" id="notify_account" <?php echo (isset($client_arguments['notify_account']) && $client_arguments['notify_account'] == 1) ? 'checked="checked"' : ''; ?>> <?php _e('Send welcome email', 'cftp_admin'); ?>
                </label>
            </div>
        </div>

        <div class="form-group row">
            <div class="col-sm-8 col-sm-offset-4">
                <label for="require_password_change">
                    <input type="checkbox" name="require_password_change" id="require_password_change" <?php echo (isset($client_arguments['require_password_change']) && $client_arguments['require_password_change'] == 1) ? 'checked="checked"' : ''; ?>> <?php _e('Require password change after first login', 'cftp_admin'); ?>
                </label>
            </div>
        </div>
    <?php
    }
    ?>

    <?php
    if ($clients_form_type == 'new_client_self') {
        recaptcha2_render_widget();
    }
    ?>

    <div class="inside_form_buttons">
        <button type="submit" class="btn btn-wide btn-primary"><?php echo html_output($submit_value); ?></button>
    </div>

    <?php
    if ($info_box == true) {
        $msg = __('This account information will be e-mailed to the address supplied above', 'cftp_admin');
        echo system_message('info', $msg);
    }
    ?>
</form>
