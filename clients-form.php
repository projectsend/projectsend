<?php
/**
 * Contains the form that is used when adding or editing clients.
 *
 * @package		ProjectSend
 * @subpackage	Clients
 *
 */
?>

<script type="text/javascript">
	$(document).ready(function() {
		$("form").submit(function() {
			clean_form(this);

				is_complete(this.add_client_form_name,'<?php echo $validation_no_name; ?>');
				is_complete(this.add_client_form_user,'<?php echo $validation_no_user; ?>');
				is_complete(this.add_client_form_email,'<?php echo $validation_no_email; ?>');
				is_length(this.add_client_form_user,<?php echo MIN_USER_CHARS; ?>,<?php echo MAX_USER_CHARS; ?>,'<?php echo $validation_length_user; ?>');
				is_email(this.add_client_form_email,'<?php echo $validation_invalid_mail; ?>');
				is_alpha_or_dot(this.add_client_form_user,'<?php echo $validation_alpha_user; ?>');
			
			<?php
				/**
				 * Password validation is optional only when editing a client.
				 */
				if ($clients_form_type == 'edit_client' || $clients_form_type == 'edit_client_self') {
			?>
					// Only check password if any of the 2 fields is completed
					var password_1 = $("#add_client_form_pass").val();
					//var password_2 = $("#add_client_form_pass2").val();
					if ($.trim(password_1).length > 0/* || $.trim(password_2).length > 0*/) {
			<?php
				}
			?>

						is_complete(this.add_client_form_pass,'<?php echo $validation_no_pass; ?>');
						//is_complete(this.add_client_form_pass2,'<?php echo $validation_no_pass2; ?>');
						is_length(this.add_client_form_pass,<?php echo MIN_PASS_CHARS; ?>,<?php echo MAX_PASS_CHARS; ?>,'<?php echo $validation_length_pass; ?>');
						is_password(this.add_client_form_pass,'<?php $chars = addslashes($validation_valid_chars); echo $validation_valid_pass." ".$chars; ?>');
						//is_match(this.add_client_form_pass,this.add_client_form_pass2,'<?php echo $validation_match_pass; ?>');

			<?php
				/** Close the jquery IF statement. */
				if ($clients_form_type == 'edit_client' || $clients_form_type == 'edit_client_self') {
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
$name_placeholder = __("Will be visible on the client's file list",'cftp_admin');

switch ($clients_form_type) {
	case 'new_client':
		$submit_value = __('Add client','cftp_admin');
		$disable_user = false;
		$require_pass = true;
		$form_action = 'clients-add.php';
		$info_box = true;
		$extra_fields = true;
		break;
	case 'edit_client':
		$submit_value = __('Save client','cftp_admin');
		$disable_user = true;
		$require_pass = false;
		$form_action = 'clients-edit.php?id='.$client_id;
		$info_box = false;
		$extra_fields = true;
		break;
	case 'new_client_self':
		$submit_value = __('Register account','cftp_admin');
		$disable_user = false;
		$require_pass = true;
		$form_action = 'register.php';
		$info_box = true;
		$extra_fields = false;
		$name_placeholder = __("Your full name",'cftp_admin');
		break;
	case 'edit_client_self':
		$submit_value = __('Update account','cftp_admin');
		$disable_user = true;
		$require_pass = false;
		$form_action = 'clients-edit.php?id='.$client_id;
		$info_box = false;
		$extra_fields = false;
		break;
}
?>

<form action="<?php echo $form_action; ?>" name="addclient" method="post">
	<ul class="form_fields">
		<li>
			<label for="add_client_form_name"><?php _e('Name','cftp_admin'); ?></label>
			<input type="text" name="add_client_form_name" id="add_client_form_name" class="required" value="<?php echo (isset($add_client_data_name)) ? stripslashes($add_client_data_name) : ''; ?>" placeholder="<?php echo $name_placeholder; ?>" />
		</li>
		<li>
			<label for="add_client_form_user"><?php _e('Log in username','cftp_admin'); ?></label>
			<input type="text" name="add_client_form_user" id="add_client_form_user" class="<?php if (!$disable_user) { echo 'required'; } ?>" maxlength="<?php echo MAX_USER_CHARS; ?>" value="<?php echo (isset($add_client_data_user)) ? stripslashes($add_client_data_user) : ''; ?>" <?php if ($disable_user) { echo 'readonly'; }?> placeholder="<?php _e("Must be alphanumeric",'cftp_admin'); ?>" />
		</li>
		<li>
			<button type="button" class="btn password_toggler pass_toggler_show"><i class="icon-eye-open"></i></button>
			<label for="add_client_form_pass"><?php _e('Password','cftp_admin'); ?></label>
			<input name="add_client_form_pass" id="add_client_form_pass" class="<?php if ($require_pass) { echo 'required'; } ?> password_toggle" type="password" maxlength="<?php echo MAX_PASS_CHARS; ?>" />
			<?php password_notes(); ?>
		</li>
		<li>
			<label for="add_client_form_email"><?php _e('E-mail','cftp_admin'); ?></label>
			<input type="text" name="add_client_form_email" id="add_client_form_email" class="required" value="<?php echo (isset($add_client_data_email)) ? stripslashes($add_client_data_email) : ''; ?>" placeholder="<?php _e("Must be valid and unique",'cftp_admin'); ?>" />
		</li>
		<li>
			<label for="add_client_form_address"><?php _e('Address','cftp_admin'); ?></label>
			<input type="text" name="add_client_form_address" id="add_client_form_address" value="<?php echo (isset($add_client_data_addr)) ? stripslashes($add_client_data_addr) : ''; ?>" />
		</li>
		<li>
			<label for="add_client_form_phone"><?php _e('Telephone','cftp_admin'); ?></label>
			<input type="text" name="add_client_form_phone" id="add_client_form_phone" value="<?php echo (isset($add_client_data_phone)) ? stripslashes($add_client_data_phone) : ''; ?>" />
		</li>
		<?php
			if ($extra_fields == true) {
		?>
				<li>
					<label for="add_client_form_intcont"><?php _e('Internal contact name','cftp_admin'); ?></label>
					<input type="text" name="add_client_form_intcont" id="add_client_form_intcont" value="<?php echo (isset($add_client_data_intcont)) ? stripslashes($add_client_data_intcont) : ''; ?>" />
				</li>
				<li>
					<label for="add_client_form_active"><?php _e('Active (client can log in)','cftp_admin'); ?></label>
					<input type="checkbox" name="add_client_form_active" id="add_client_form_active" <?php echo (isset($add_client_data_active) && $add_client_data_active == 1) ? 'checked="checked"' : ''; ?> />
				</li>
		<?php
			}
		?>
		<li>
			<label for="add_client_form_notify"><?php _e('Notify new uploads by e-mail','cftp_admin'); ?></label>
			<input type="checkbox" name="add_client_form_notify" id="add_client_form_notify" <?php echo (isset($add_client_data_notity) && $add_client_data_notity == 1) ? 'checked="checked"' : ''; ?> />
		</li>
	</ul>

	<div class="inside_form_buttons">
		<button type="submit" name="submit" class="btn btn-wide btn-primary"><?php echo $submit_value; ?></button>
	</div>

	<?php
		if ($info_box == true) {
			$msg = __('This account information will be e-mailed to the address supplied above','cftp_admin');
			echo system_message('info',$msg);
		}
	?>
</form>