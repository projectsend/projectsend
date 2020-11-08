<h3><?php _e('New registrations','cftp_admin'); ?></h3>
<p><?php _e('Used only on self-registrations. These options will not apply to clients registered by system administrators.','cftp_admin'); ?></p>

<div class="form-group">
    <div class="col-sm-8 col-sm-offset-4">
        <label for="clients_can_register">
            <input type="checkbox" value="1" name="clients_can_register" id="clients_can_register" class="checkbox_options" <?php echo (get_option('clients_can_register') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Clients can register themselves','cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="form-group">
    <div class="col-sm-8 col-sm-offset-4">
        <label for="clients_auto_approve">
            <input type="checkbox" value="1" name="clients_auto_approve" id="clients_auto_approve" class="checkbox_options" <?php echo (get_option('clients_auto_approve') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Auto approve new accounts','cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="form-group">
    <label for="clients_auto_group" class="col-sm-4 control-label"><?php _e('Add clients to this group:','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <select class="form-control" name="clients_auto_group" id="clients_auto_group" required>
            <option value="0"><?php _e('None (does not enable this feature)','cftp_admin'); ?></option>
            <?php
                /** Fill the groups array that will be used on the form */
                $groups = get_groups([]);

                foreach ( $groups as $group ) {
            ?>
                    <option value="<?php echo filter_var($group["id"], FILTER_VALIDATE_INT); ?>"
                        <?php
                            if (get_option('clients_auto_group') == $group["id"]) {
                                echo 'selected="selected"';
                            }
                        ?>
                        ><?php echo html_output($group["name"]); ?>
                    </option>
            <?php
                }
            ?>
        </select>
        <p class="field_note"><?php _e('New clients will automatically be assigned to the group you have selected.','cftp_admin'); ?></p>
    </div>
</div>

<div class="form-group">
    <label for="clients_can_select_group" class="col-sm-4 control-label"><?php _e('Groups for which clients can request membership to:','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <select class="form-control" name="clients_can_select_group" id="clients_can_select_group" required>
            <?php
                $pub_groups_options = array(
                                            'none'		=> __("None",'cftp_admin'),
                                            'public'	=> __("Public groups",'cftp_admin'),
                                            'all'		=> __("All groups",'cftp_admin'),
                                        );
                foreach ( $pub_groups_options as $value => $label ) {
            ?>
                    <option value="<?php echo $value; ?>" <?php if (get_option('clients_can_select_group') == $value) { echo 'selected="selected"'; } ?>><?php echo $label; ?></option>
            <?php
                }
            ?>
        </select>
        <p class="field_note"><?php _e('When a client registers a new account, an option will be presented to request becoming a member of a particular group.','cftp_admin'); ?></p>
    </div>
</div>

<div class="options_divide"></div>

<h3><?php _e('Files','cftp_admin'); ?></h3>

<div class="form-group">
    <div class="col-sm-8 col-sm-offset-4">
        <label for="clients_can_upload">
            <input type="checkbox" value="1" name="clients_can_upload" id="clients_can_upload" class="checkbox_options" <?php echo (get_option('clients_can_upload') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Clients can upload files','cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="form-group">
    <div class="col-sm-8 col-sm-offset-4">
        <label for="clients_can_delete_own_files">
            <input type="checkbox" value="1" name="clients_can_delete_own_files" id="clients_can_delete_own_files" class="checkbox_options" <?php echo (get_option('clients_can_delete_own_files') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Clients can delete their own uploaded files','cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="form-group">
    <div class="col-sm-8 col-sm-offset-4">
        <label for="clients_can_set_expiration_date">
            <input type="checkbox" value="1" name="clients_can_set_expiration_date" id="clients_can_set_expiration_date" class="checkbox_options" <?php echo (get_option('clients_can_set_expiration_date') == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Clients can set expiration Date','cftp_admin'); ?>
        </label>
    </div>
</div>

<div class="form-group">
    <label for="expired_files_hide" class="col-sm-4 control-label"><?php _e('When a file expires:','cftp_admin'); ?></label>
    <div class="col-sm-8">
        <select class="form-control" name="expired_files_hide" id="expired_files_hide" required>
            <option value="1" <?php echo (get_option('expired_files_hide') == '1') ? 'selected="selected"' : ''; ?>><?php _e("Don't show it on the files list",'cftp_admin'); ?></option>
            <option value="0" <?php echo (get_option('expired_files_hide') == '0') ? 'selected="selected"' : ''; ?>><?php _e("Show it anyway, but prevent download.",'cftp_admin'); ?></option>
        </select>
        <p class="field_note"><?php _e('This only affects clients. On the admin side, you can still get the files.','cftp_admin'); ?></p>
    </div>
</div>
