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
				is_number(this.add_client_form_maxfilesize,'<?php echo $validation_file_size; ?>');
			
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
$current_level = get_current_user_level();

$name_placeholder = __("Will be visible on the client's file list",'cftp_admin');

switch ($clients_form_type) {
	/** User is creating a new client */
	case 'new_client':
		$submit_value = __('Add client','cftp_admin');
		$disable_user = false;
		$require_pass = true;
		$form_action = 'clients-add.php';
		$info_box = true;
		$extra_fields = true;
		$group_field = true;
		$group_label = __('Groups','cftp_admin');
		$ignore_size = false;
		break;
	/** User is editing an existing client */
	case 'edit_client':
		$submit_value = __('Save client','cftp_admin');
		$disable_user = true;
		$require_pass = false;
		$form_action = 'clients-edit.php?id='.$client_id;
		$info_box = false;
		$extra_fields = true;
		$group_field = true;
		$group_label = __('Groups','cftp_admin');
		$ignore_size = false;
		break;
	/** A client is creating a new account for himself */
	case 'new_client_self':
		$submit_value = __('Register account','cftp_admin');
		$disable_user = false;
		$require_pass = true;
		$form_action = 'register.php';
		$info_box = true;
		$extra_fields = false;
		$name_placeholder = __("Your full name",'cftp_admin');
		$group_field = false;
		if ( CLIENTS_CAN_SELECT_GROUP == 'public' || CLIENTS_CAN_SELECT_GROUP == 'all' ) {
			$group_field = true;
			$group_label = __('Request access to groups','cftp_admin');
		}
		break;
	/** A client is editing his profile */
	case 'edit_client_self':
		$submit_value = __('Update account','cftp_admin');
		$disable_user = true;
		$require_pass = false;
		$form_action = 'clients-edit.php?id='.$client_id;
		$info_box = false;
		$extra_fields = false;
		$group_field = false;
		$ignore_size = true;
		break;
}
?>

<form action="<?php echo $form_action; ?>" name="addclient" method="post" class="form-horizontal">
	<div class="form-group">
		<label for="add_client_form_name" class="col-sm-4 control-label"><?php _e('Name','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" name="add_client_form_name" id="add_client_form_name" class="form-control required" value="<?php echo (isset($add_client_data_name)) ? html_output(stripslashes($add_client_data_name)) : ''; ?>" placeholder="<?php echo $name_placeholder; ?>" />
		</div>
	</div>

	<div class="form-group">
		<label for="add_client_form_user" class="col-sm-4 control-label"><?php _e('Log in username','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" name="add_client_form_user" id="add_client_form_user" class="form-control <?php if (!$disable_user) { echo 'required'; } ?>" maxlength="<?php echo MAX_USER_CHARS; ?>" value="<?php echo (isset($add_client_data_user)) ? html_output(stripslashes($add_client_data_user)) : ''; ?>" <?php if ($disable_user) { echo 'readonly'; }?> placeholder="<?php _e("Must be alphanumeric",'cftp_admin'); ?>" />
		</div>
	</div>

	<div class="form-group">
		<label for="add_client_form_pass" class="col-sm-4 control-label"><?php _e('Password','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<div class="input-group">
				<input name="add_client_form_pass" id="add_client_form_pass" class="form-control password_toggle <?php if ($require_pass) { echo 'required'; } ?>" type="password" maxlength="<?php echo MAX_PASS_CHARS; ?>" />
				<div class="input-group-btn password_toggler">
					<button type="button" class="btn pass_toggler_show"><i class="glyphicon glyphicon-eye-open"></i></button>
				</div>
			</div>
			<button type="button" name="generate_password" id="generate_password" class="btn btn-default btn-sm btn_generate_password" data-ref="add_client_form_pass" data-min="<?php echo MAX_GENERATE_PASS_CHARS; ?>" data-max="<?php echo MAX_GENERATE_PASS_CHARS; ?>"><?php _e('Generate','cftp_admin'); ?></button>
			<?php echo password_notes(); ?>
		</div>		
	</div>

	<div class="form-group">
		<label for="add_client_form_email" class="col-sm-4 control-label"><?php _e('E-mail','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" name="add_client_form_email" id="add_client_form_email" class="form-control required" value="<?php echo (isset($add_client_data_email)) ? html_output(stripslashes($add_client_data_email)) : ''; ?>" placeholder="<?php _e("Must be valid and unique",'cftp_admin'); ?>" />
		</div>
	</div>

	<div class="form-group">
		<label for="add_client_form_address" class="col-sm-4 control-label"><?php _e('Address','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" name="add_client_form_address" id="add_client_form_address" class="form-control" value="<?php echo (isset($add_client_data_addr)) ? html_output(stripslashes($add_client_data_addr)) : ''; ?>" />
		</div>
	</div>

	<div class="form-group">
		<label for="add_client_form_phone" class="col-sm-4 control-label"><?php _e('Telephone','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" name="add_client_form_phone" id="add_client_form_phone" class="form-control" value="<?php echo (isset($add_client_data_phone)) ? html_output(stripslashes($add_client_data_phone)) : ''; ?>" />
		</div>
	</div>

	<?php
		if ($extra_fields == true) {
	?>
			<div class="form-group">
				<label for="add_client_form_intcont" class="col-sm-4 control-label"><?php _e('Internal contact name','cftp_admin'); ?></label>
				<div class="col-sm-8">
					<input type="text" name="add_client_form_intcont" id="add_client_form_intcont" class="form-control" value="<?php echo (isset($add_client_data_intcont)) ? html_output(stripslashes($add_client_data_intcont)) : ''; ?>" />
				</div>
			</div>

			<div class="form-group">
				<label for="add_client_form_maxfilesize" class="col-sm-4 control-label"><?php _e('Max. upload filesize','cftp_admin'); ?></label>
				<div class="col-sm-8">
					<div class="input-group">
						<input type="text" name="add_client_form_maxfilesize" id="add_client_form_maxfilesize" class="form-control" value="<?php echo (isset($add_client_data_maxfilesize)) ? html_output(stripslashes($add_client_data_maxfilesize)) : ''; ?>" />
						<span class="input-group-addon">mb</span>
					</div>
					<p class="field_note"><?php _e("Set to 0 to use the default system limit",'cftp_admin'); ?> (<?php echo MAX_FILESIZE; ?> mb)</p>
				</div>
			</div>
	<?php
		}
	?>

	<?php
		if ( $group_field == true ) {
			/**
			 * Make a list of public groups in case clients can only request
			 * membership to those
			 */
			$memberships	= new GroupActions;
			$arguments		= array();

			/** Groups to search on based on the current user level */
			if ( $current_level == 9 || $current_level == 8 ) {
				/** An admin or client manager is creating a client account */
			}
			else {
				/** Someone is registering an account for himself */
				if ( CLIENTS_CAN_SELECT_GROUP == 'public' ) {
					$arguments['public'] = true;
				}
			}

			$sql_groups = $memberships->get_groups($arguments);

			if ( count( $sql_groups ) > 0) {
	?>
				<div class="form-group assigns">
					<label for="add_client_group_request" class="col-sm-4 control-label"><?php echo $group_label; ?></label>
					<div class="col-sm-8">
						<select multiple="multiple" name="add_client_group_request[]" id="groups-select" class="form-control chosen-select" data-placeholder="<?php _e('Select one or more options. Type to search.', 'cftp_admin');?>">
							<?php
								foreach ( $sql_groups as $group ) {
							?>
									<option value="<?php echo $group['id']; ?>"
							<?php
										if ( !empty( $found_groups ) && in_array( $group['id'], $found_groups ) ) {
											echo ' selected="selected"';
										}
							?>
									><?php echo $group['name']; ?></option>
							<?php
								}
							?>
						</select>
						<?php
							if ( $current_level == 9 || $current_level == 8 ) {
						?>
								<div class="list_mass_members">
									<a href="#" class="btn btn-default add-all"><?php _e('Add all','cftp_admin'); ?></a>
									<a href="#" class="btn btn-default remove-all"><?php _e('Remove all','cftp_admin'); ?></a>
								</div>
						<?php
							}
						?>
					</div>
				</div>
	<?php
			}
		}
	?>

	<?php
		if ($extra_fields == true) {
	?>
			<div class="form-group">
				<div class="col-sm-8 col-sm-offset-4">
					<label for="add_client_form_active">
						<input type="checkbox" name="add_client_form_active" id="add_client_form_active" <?php echo (isset($add_client_data_active) && $add_client_data_active == 1) ? 'checked="checked"' : ''; ?>> <?php _e('Active (client can log in)','cftp_admin'); ?>
					</label>
				</div>
			</div>
	<?php
		}
	?>

	<div class="form-group">
		<div class="col-sm-8 col-sm-offset-4">
			<label for="add_client_form_notify">
				<input type="checkbox" name="add_client_form_notify" id="add_client_form_notify" <?php echo (isset($add_client_data_notity) && $add_client_data_notity == 1) ? 'checked="checked"' : ''; ?>> <?php _e('Notify new uploads by e-mail','cftp_admin'); ?>
			</label>
		</div>
	</div>
	
	<?php
		if ( $clients_form_type == 'new_client_self' ) {
			if ( defined('RECAPTCHA_AVAILABLE') ) {
	?>
				<div class="form-group">
					<label class="col-sm-4 control-label"><?php _e('Verification','cftp_admin'); ?></label>
					<div class="col-sm-8">
						<div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
					</div>
				</div>
	<?php
			}
			
		}
	?>

	<div class="inside_form_buttons">
		<button type="submit" name="submit" class="btn btn-wide btn-primary"><?php echo html_output($submit_value); ?></button>
	</div>

	<?php
		if ($info_box == true) {
			$msg = __('This account information will be e-mailed to the address supplied above','cftp_admin');
			echo system_message('info',$msg);
		}
	?>
</form>

<script type="text/javascript">
	$(document).ready(function() {
		$('.chosen-select').chosen({
			no_results_text	: "<?php _e('No results where found.','cftp_admin'); ?>",
			search_contains	: true
		});

		$('.add-all').click(function(){
			var selector = $(this).closest('.assigns').find('select');
			$(selector).find('option').each(function(){
				$(this).prop('selected', true);
			});
			$('select').trigger('chosen:updated');
			return false;
		});

		$('.remove-all').click(function(){
			var selector = $(this).closest('.assigns').find('select');
			$(selector).find('option').each(function(){
				$(this).prop('selected', false);
			});
			$('select').trigger('chosen:updated');
			return false;
		});
	});
</script>
