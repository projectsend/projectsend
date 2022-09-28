<?php
/**
 * Contains the form that is used when adding or editing users.
 */
$disable_user = true;
$require_pass = true;
$form_action = 'users-add.php';
$extra_fields = false;
$limit_field_class = 'none';

switch ($user_form_type) {
	case 'new_user':
		$submit_value = __('Add user','cftp_admin');
		$form_action = 'users-add.php';
		$disable_user = false;
		$extra_fields = true;
		break;
	case 'edit_user':
		$submit_value = __('Save user','cftp_admin');
		$form_action = 'users-edit.php?id='.$user_id;
		$require_pass = false;
		$extra_fields = true;
        if ($user_arguments['role'] == '7') $limit_field_class = '';
		break;
	case 'edit_user_self':
		$submit_value = __('Update account','cftp_admin');
		$form_action = 'users-edit.php?id='.$user_id;
		$require_pass = false;
		break;
}
?>
<form action="<?php echo html_output($form_action); ?>" name="user_form" id="user_form" method="post" class="form-horizontal" data-form-type="<?php echo $user_form_type; ?>">
    <?php addCsrf(); ?>

	<div class="form-group">
		<label for="name" class="col-sm-4 control-label"><?php _e('Name','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" name="name" id="name" class="form-control required" value="<?php echo (isset($user_arguments['name'])) ? format_form_value($user_arguments['name']) : ''; ?>" required />
		</div>
	</div>

	<div class="form-group">
		<label for="username" class="col-sm-4 control-label"><?php _e('Log in username','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" name="username" id="username" class="form-control <?php if (!$disable_user) { echo 'required'; } ?>" maxlength="<?php echo MAX_USER_CHARS; ?>" value="<?php echo (isset($user_arguments['username'])) ? format_form_value($user_arguments['username']) : ''; ?>" <?php if ($disable_user) { echo 'readonly'; } ?> placeholder="<?php _e("Must be alphanumeric",'cftp_admin'); ?>" required />
		</div>
	</div>

	<div class="form-group">
		<label for="password" class="col-sm-4 control-label"><?php _e('Password','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<div class="input-group">
				<input type="password" name="password" id="password" class="form-control <?php if ($require_pass) { echo 'required'; } ?> password_toggle" maxlength="<?php echo MAX_PASS_CHARS; ?>" />
				<div class="input-group-btn password_toggler">
					<button type="button" class="btn pass_toggler_show"><i class="glyphicon glyphicon-eye-open"></i></button>
				</div>
			</div>
			<button type="button" name="generate_password" id="generate_password" class="btn btn-default btn-sm btn_generate_password" data-ref="password" data-min="<?php echo MAX_GENERATE_PASS_CHARS; ?>" data-max="<?php echo MAX_GENERATE_PASS_CHARS; ?>"><?php _e('Generate','cftp_admin'); ?></button>
			<?php echo password_notes(); ?>
		</div>
	</div>

	<div class="form-group">
		<label for="email" class="col-sm-4 control-label"><?php _e('E-mail','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" name="email" id="email" class="form-control required" value="<?php echo (isset($user_arguments['email'])) ? format_form_value($user_arguments['email']) : ''; ?>" placeholder="<?php _e("Must be valid and unique",'cftp_admin'); ?>" required />
		</div>
	</div>

		<?php
			if ($extra_fields == true) {
		?>
			<div class="form-group">
				<label for="level" class="col-sm-4 control-label"><?php _e('Role','cftp_admin'); ?></label>
				<div class="col-sm-8">
					<select name="level" id="level" class="form-control" required>
                        <?php
                            $roles = [
                                '9' => USER_ROLE_LVL_9,
                                '8' => USER_ROLE_LVL_8,
                                '7' => USER_ROLE_LVL_7,
                            ];
                            foreach ( $roles as $role_level => $role_name ) {
                        ?>
						        <option value="<?php echo $role_level; ?>" <?php echo (isset($user_arguments['role']) && $user_arguments['role'] == $role_level) ? 'selected="selected"' : ''; ?>><?php echo $role_name; ?></option>
                        <?php
                            }
                        ?>
					</select>
				</div>
			</div>

			<div class="form-group">
				<label for="max_file_size" class="col-sm-4 control-label"><?php _e('Max. upload filesize','cftp_admin'); ?></label>
				<div class="col-sm-8">
					<div class="input-group">
						<input type="text" name="max_file_size" id="max_file_size" class="form-control" value="<?php echo (isset($user_arguments['max_file_size'])) ? format_form_value($user_arguments['max_file_size']) : '0'; ?>" />
						<span class="input-group-addon">MB</span>
					</div>
					<p class="field_note"><?php _e("Set to 0 to use the default system limit",'cftp_admin'); ?> (<?php echo MAX_FILESIZE; ?> MB)</p>
				</div>
			</div>

            <div class="form-group <?php echo $limit_field_class; ?>" id="limit_upload_to_container">
                <label for="limit_upload_to" class="col-sm-4 control-label"><?php _e('Limit account to this clients only','cftp_admin'); ?></label>
                <div class="col-sm-8">
                    <select multiple="multiple" id="limit_upload_to" class="form-control chosen-select none" name="limit_upload_to[]" data-placeholder="<?php _e('Select one or more options. Type to search.', 'cftp_admin');?>">
                        <?php
                            $sql = $dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE level = '0' ORDER BY name ASC");
                            $sql->execute();
                            $sql->setFetchMode(PDO::FETCH_ASSOC);
                            while ( $row = $sql->fetch() ) {
                        ?>
                                <option value="<?php echo $row["id"]; ?>"
                                    <?php
                                        if ($user_form_type == 'edit_user') {
                                            if (!empty($user_arguments['limit_upload_to'])) {
                                                if (in_array($row["id"], $user_arguments['limit_upload_to'])) {
                                                    echo ' selected="selected"';
                                                }
                                            }
                                        }
                                    ?>
                                    ><?php echo sprintf('%d - %s - %s', html_output($row["id"]), html_output($row["name"]), html_output($row["email"])); ?>
                                </option>
                        <?php
                            }
                        ?>
                    </select>
                    <p class="field_note"><?php _e('Leave empty to allow access to all clients','cftp_admin'); ?></p>
                    <p class="field_note"><?php _e('Important: at the moment limiting to specific users also limits uploading to groups that these clients are members of.','cftp_admin'); ?></p>
                </div>
            </div>

			<div class="form-group">
				<div class="col-sm-8 col-sm-offset-4">
					<label for="active">
						<input type="checkbox" name="active" id="active" <?php echo (isset($user_arguments['active']) && $user_arguments['active'] == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Active (user can log in)','cftp_admin'); ?>
					</label>
				</div>
			</div>

			<?php
				if ( $user_form_type == 'new_user' ) {
			?>

					<div class="form-group">
						<div class="col-sm-8 col-sm-offset-4">
							<label for="notify_account">
								<input type="checkbox" name="notify_account" id="notify_account" <?php echo (isset($user_arguments['notify_account']) && $user_arguments['notify_account'] == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Send welcome email','cftp_admin'); ?>
							</label>
						</div>
					</div>

                    <div class="form-group">
                        <div class="col-sm-8 col-sm-offset-4">
                            <label for="require_password_change">
                                <input type="checkbox" name="require_password_change" id="require_password_change" <?php echo (isset($user_arguments['require_password_change']) && $user_arguments['require_password_change'] == 1) ? 'checked="checked"' : ''; ?>> <?php _e('Require password change after first login','cftp_admin'); ?>
                            </label>
                        </div>
                    </div>
			<?php
				}
			}
		?>

	<div class="inside_form_buttons">
		<button type="submit" class="btn btn-wide btn-primary"><?php echo $submit_value; ?></button>
	</div>

	<?php
		if ($user_form_type == 'new_user') {
			$msg = __('This account information will be e-mailed to the address supplied above','cftp_admin');
			echo system_message('info',$msg);
		}
	?>
</form>