<?php
/**
 * Contains the form that is used when adding or editing users.
 *
 * @package		ProjectSend
 * @subpackage	Users
 *
 */
?>

<script type="text/javascript">
	$(document).ready(function() {
		$("form").submit(function() {
			clean_form(this);

			is_complete(this.name,'<?php echo $json_strings['validation']['no_name']; ?>');
			is_complete(this.username,'<?php echo $json_strings['validation']['no_user']; ?>');
			is_complete(this.email,'<?php echo $json_strings['validation']['no_email']; ?>');
			is_complete(this.level,'<?php echo $json_strings['validation']['no_role']; ?>');
			is_length(this.username,<?php echo MIN_USER_CHARS; ?>,<?php echo MAX_USER_CHARS; ?>,'<?php echo $json_strings['validation']['length_user']; ?>');
			is_email(this.email,'<?php echo $json_strings['validation']['invalid_email']; ?>');
			is_alpha_or_dot(this.username,'<?php echo $json_strings['validation']['alpha_user']; ?>');
			is_number(this.max_file_size,'<?php echo $json_strings['validation']['file_size']; ?>');
			
			<?php
				/**
				 * Password validation is optional only when editing a user.
				 */
				if ($user_form_type == 'edit_user' || $user_form_type == 'edit_user_self') {
			?>
					// Only check password if any of the 2 fields is completed
					var password_1 = $("#password").val();
					//var password_2 = $("#password_repeat").val();
					if ($.trim(password_1).length > 0/* || $.trim(password_2).length > 0*/) {
			<?php
				}
			?>

						is_complete(this.password,'<?php echo $json_strings['validation']['no_pass']; ?>');
						//is_complete(this.password_repeat,'<?php echo $json_strings['validation']['no_pass2']; ?>');
						is_length(this.password,<?php echo MIN_PASS_CHARS; ?>,<?php echo MAX_PASS_CHARS; ?>,'<?php echo $json_strings['validation']['length_pass']; ?>');
						is_password(this.password,'<?php echo $json_strings['validation']['valid_pass'] . " " . addslashes($json_strings['validation']['valid_chars']); ?>');
						//is_match(this.password,this.password_repeat,'<?php echo $json_strings['validation']['match_pass']; ?>');

			<?php
				/** Close the jquery IF statement. */
				if ($user_form_type == 'edit_user' || $user_form_type == 'edit_user_self') {
			?>
					}
			<?php
				}
			?>

			// show the errors or continue if everything is ok
			if (show_form_errors() == false) { return false; }
		});
	});
</script>

<?php
switch ($user_form_type) {
	case 'new_user':
		$submit_value = __('Add user','cftp_admin');
		$disable_user = false;
		$require_pass = true;
		$form_action = 'users-add.php';
		$extra_fields = true;
		break;
	case 'edit_user':
		$submit_value = __('Save user','cftp_admin');
		$disable_user = true;
		$require_pass = false;
		$form_action = 'users-edit.php?id='.$user_id;
		$extra_fields = true;
		break;
	case 'edit_user_self':
		$submit_value = __('Update account','cftp_admin');
		$disable_user = true;
		$require_pass = false;
		$form_action = 'users-edit.php?id='.$user_id;
		$extra_fields = false;
		break;
}
?>
<form action="<?php echo html_output($form_action); ?>" name="adduser" method="post" class="form-horizontal">
	<div class="form-group">
		<label for="name" class="col-sm-4 control-label"><?php _e('Name','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" name="name" id="name" class="form-control required" value="<?php echo (isset($user_arguments['name'])) ? format_form_value($user_arguments['name']) : ''; ?>" />
		</div>
	</div>

	<div class="form-group">
		<label for="username" class="col-sm-4 control-label"><?php _e('Log in username','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" name="username" id="username" class="form-control <?php if (!$disable_user) { echo 'required'; } ?>" maxlength="<?php echo MAX_USER_CHARS; ?>" value="<?php echo (isset($user_arguments['username'])) ? format_form_value($user_arguments['username']) : ''; ?>" <?php if ($disable_user) { echo 'readonly'; } ?> placeholder="<?php _e("Must be alphanumeric",'cftp_admin'); ?>" />
		</div>
	</div>

	<div class="form-group">
		<label for="password" class="col-sm-4 control-label"><?php _e('Password','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<div class="input-group">
				<input name="password" id="password" class="form-control <?php if ($require_pass) { echo 'required'; } ?> password_toggle" type="password" maxlength="<?php echo MAX_PASS_CHARS; ?>" />
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
			<input type="text" name="email" id="email" class="form-control required" value="<?php echo (isset($user_arguments['email'])) ? format_form_value($user_arguments['email']) : ''; ?>" placeholder="<?php _e("Must be valid and unique",'cftp_admin'); ?>" />
		</div>
	</div>

		<?php
			if ($extra_fields == true) {
		?>
			<div class="form-group">
				<label for="level" class="col-sm-4 control-label"><?php _e('Role','cftp_admin'); ?></label>
				<div class="col-sm-8">
					<select name="level" id="level" class="form-control">
                        <?php
                            $roles = [
                                '9' => USER_ROLE_LVL_9,
                                '8' => USER_ROLE_LVL_8,
                                '7' => USER_ROLE_LVL_7,
                            ];
                            foreach ( $roles as $role_level => $role_name ) {
                        ?>
						        <option value="<?php echo $role_level; ?>" <?php echo (isset($user_arguments['level']) && $user_arguments['level'] == $role_level) ? 'selected="selected"' : ''; ?>><?php echo $role_name; ?></option>
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
						<span class="input-group-addon">mb</span>
					</div>
					<p class="field_note"><?php _e("Set to 0 to use the default system limit",'cftp_admin'); ?> (<?php echo MAX_FILESIZE; ?> mb)</p>
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