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
        if (isset($user_arguments['role_id'])) {
            // Check if this is an Uploader role (historically level 7)
            $user_role = new \ProjectSend\Classes\Roles($user_arguments['role_id']);
            if ($user_role->exists() && $user_role->name === 'Uploader') {
                $limit_field_class = '';
            }
        }
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

	<div class="form-group row">
		<label for="name" class="col-sm-4 control-label"><?php _e('Name','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" name="name" id="name" class="form-control required" value="<?php echo (isset($user_arguments['name'])) ? format_form_value($user_arguments['name']) : ''; ?>" required />
		</div>
	</div>

	<div class="form-group row">
		<label for="username" class="col-sm-4 control-label"><?php _e('Log in username','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" name="username" id="username" class="form-control <?php if (!$disable_user) { echo 'required'; } ?>" maxlength="<?php echo MAX_USER_CHARS; ?>" value="<?php echo (isset($user_arguments['username'])) ? format_form_value($user_arguments['username']) : ''; ?>" <?php if ($disable_user) { echo 'readonly'; } ?> placeholder="<?php _e("Must be alphanumeric",'cftp_admin'); ?>" required />
		</div>
	</div>

	<div class="form-group row">
		<label for="password" class="col-sm-4 control-label"><?php _e('Password','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<div class="input-group">
				<input type="password" name="password" id="password" class="form-control <?php if ($require_pass) { echo 'required'; } ?> attach_password_toggler" maxlength="<?php echo MAX_PASS_CHARS; ?>" />
			</div>
			<button type="button" name="generate_password" id="generate_password" class="btn btn-light btn-sm btn_generate_password" data-ref="password" data-min="<?php echo MAX_GENERATE_PASS_CHARS; ?>" data-max="<?php echo MAX_GENERATE_PASS_CHARS; ?>"><?php _e('Generate','cftp_admin'); ?></button>
			<?php echo password_notes(); ?>
		</div>
	</div>

	<div class="form-group row">
		<label for="email" class="col-sm-4 control-label"><?php _e('E-mail','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" name="email" id="email" class="form-control required" value="<?php echo (isset($user_arguments['email'])) ? format_form_value($user_arguments['email']) : ''; ?>" placeholder="<?php _e("Must be valid and unique",'cftp_admin'); ?>" required />
		</div>
	</div>

		<?php
			if ($extra_fields == true) {
		?>
			<div class="form-group row">
				<label for="role_id" class="col-sm-4 control-label"><?php _e('Role','cftp_admin'); ?></label>
				<div class="col-sm-8">
					<select class="form-select" name="role_id" id="role_id" required>
                        <?php
                            // Get available roles from database for system users (exclude client role)
                            $roles_query = "SELECT id, name, description, is_system_role
                                          FROM " . TABLE_ROLES . "
                                          WHERE active = 1 AND name != 'Client'
                                          ORDER BY id ASC";
                            $roles_stmt = $dbh->prepare($roles_query);
                            $roles_stmt->execute();

                            while ($role = $roles_stmt->fetch(PDO::FETCH_ASSOC)) {
                        ?>
						        <option value="<?php echo $role['id']; ?>"
                                        <?php echo (isset($user_arguments['role_id']) && $user_arguments['role_id'] == $role['id']) ? 'selected="selected"' : ''; ?>>
                                    <?php echo html_output($role['name']); ?>
                                    <?php if ($role['is_system_role']): ?>
                                        <span class="text-muted">(<?php _e('System Role', 'cftp_admin'); ?>)</span>
                                    <?php endif; ?>
                                </option>
                        <?php
                            }
                        ?>
					</select>
                    <small class="form-text text-muted">
                        <?php _e('Select the role that determines what this user can do in the system', 'cftp_admin'); ?>
                    </small>
				</div>
			</div>

			<div class="form-group row">
				<label for="max_file_size" class="col-sm-4 control-label"><?php _e('Max. upload filesize','cftp_admin'); ?></label>
				<div class="col-sm-8">
					<div class="input-group">
						<input type="text" name="max_file_size" id="max_file_size" class="form-control" value="<?php echo (isset($user_arguments['max_file_size'])) ? format_form_value($user_arguments['max_file_size']) : '0'; ?>" />
						<span class="input-group-text">MB</span>
					</div>
					<p class="field_note form-text"><?php _e("Set to 0 to use the default system limit",'cftp_admin'); ?> (<?php echo MAX_FILESIZE; ?> MB)</p>
				</div>
			</div>

			<div class="form-group row">
				<label for="max_disk_quota" class="col-sm-4 control-label"><?php _e('Max. disk quota','cftp_admin'); ?></label>
				<div class="col-sm-8">
					<div class="input-group">
						<input type="text" name="max_disk_quota" id="max_disk_quota" class="form-control" value="<?php echo (isset($user_arguments['max_disk_quota'])) ? format_form_value($user_arguments['max_disk_quota']) : '0'; ?>" />
						<span class="input-group-text">MB</span>
					</div>
					<p class="field_note form-text"><?php _e("Set to 0 for unlimited disk space",'cftp_admin'); ?></p>
				</div>
			</div>

            <div class="form-group row <?php echo $limit_field_class; ?>" id="limit_upload_to_container">
                <label for="limit_upload_to" class="col-sm-4 control-label"><?php _e('Limit account to this clients only','cftp_admin'); ?></label>
                <div class="col-sm-8">
                    <select class="form-select select2 none" multiple="multiple" id="limit_upload_to" name="limit_upload_to[]" data-placeholder="<?php _e('Select one or more options. Type to search.', 'cftp_admin');?>">
                        <?php
                            $sql = $dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE role_id = (SELECT id FROM " . TABLE_ROLES . " WHERE name = 'Client') ORDER BY name ASC");
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
                    <p class="field_note form-text"><?php _e('Leave empty to allow access to all clients','cftp_admin'); ?></p>
                    <p class="field_note form-text"><?php _e('Important: at the moment limiting to specific users also limits uploading to groups that these clients are members of.','cftp_admin'); ?></p>
                </div>
            </div>

			<div class="form-group row">
				<div class="col-sm-8 offset-sm-4">
					<label for="active">
						<input type="checkbox" name="active" id="active" <?php echo (isset($user_arguments['active']) && $user_arguments['active'] == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Active (user can log in)','cftp_admin'); ?>
					</label>
				</div>
			</div>

			<?php
				if ( $user_form_type == 'new_user' ) {
			?>
					<div class="form-group row">
						<div class="col-sm-8 offset-sm-4">
							<label for="notify_account">
								<input type="checkbox" name="notify_account" id="notify_account" <?php echo (isset($user_arguments['notify_account']) && $user_arguments['notify_account'] == 1) ? 'checked="checked"' : ''; ?> /> <?php _e('Send welcome email','cftp_admin'); ?>
							</label>
						</div>
					</div>

                    <div class="form-group row">
                        <div class="col-sm-8 offset-sm-4">
                            <label for="require_password_change">
                                <input type="checkbox" name="require_password_change" id="require_password_change" <?php echo (isset($user_arguments['require_password_change']) && $user_arguments['require_password_change'] == 1) ? 'checked="checked"' : ''; ?>> <?php _e('Require password change after first login','cftp_admin'); ?>
                            </label>
                        </div>
                    </div>
			<?php
				}
			}
		?>

    <?php
    // Render custom fields for users
    $custom_field_type = 'user';
    $custom_form_type = ($user_form_type == 'edit_user_self') ? 'self' : 'full';

    // Get user ID for existing values
    $custom_user_id = null;
    if (isset($user_id)) {
        $custom_user_id = $user_id;
    }

    echo render_custom_fields($custom_field_type, $custom_user_id, $custom_form_type);
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