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

            is_complete(this.name,'<?php echo $json_strings['validation']['no_name']; ?>');
            is_complete(this.username,'<?php echo $json_strings['validation']['no_user']; ?>');
            is_complete(this.email,'<?php echo $json_strings['validation']['no_email']; ?>');
            is_length(this.username,<?php echo MIN_USER_CHARS; ?>,<?php echo MAX_USER_CHARS; ?>,'<?php echo $json_strings['validation']['length_user']; ?>');
            is_email(this.email,'<?php echo $json_strings['validation']['invalid_email']; ?>');
            is_alpha_or_dot(this.username,'<?php echo $json_strings['validation']['alpha_user']; ?>');
            is_number(this.max_file_size,'<?php echo $json_strings['validation']['file_size']; ?>');
			
			<?php
				/**
				 * Password validation is optional only when editing a client.
				 */
				if ($clients_form_type == 'edit_client' || $clients_form_type == 'edit_client_self') {
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
		if ( CLIENTS_CAN_SELECT_GROUP == 'public' || CLIENTS_CAN_SELECT_GROUP == 'all' ) {
			$group_field			= true;
			$group_label			= __('Request access to groups','cftp_admin');
			$override_groups_list	= $found_requests[$client_id]['group_ids'];
		}
		$ignore_size = true;
		break;
}
?>

<form action="<?php echo $form_action; ?>" name="addclient" method="post" class="form-horizontal">
	<div class="form-group">
		<label for="name" class="col-sm-4 control-label"><?php _e('Name','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" name="name" id="name" class="form-control required" value="<?php echo (isset($client_arguments['name'])) ? format_form_value($client_arguments['name']) : ''; ?>" placeholder="<?php echo $name_placeholder; ?>" />
		</div>
	</div>

	<div class="form-group">
		<label for="username" class="col-sm-4 control-label"><?php _e('Log in username','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" name="username" id="username" class="form-control <?php if (!$disable_user) { echo 'required'; } ?>" maxlength="<?php echo MAX_USER_CHARS; ?>" value="<?php echo (isset($client_arguments['username'])) ? format_form_value($client_arguments['username']) : ''; ?>" <?php if ($disable_user) { echo 'readonly'; }?> placeholder="<?php _e("Must be alphanumeric",'cftp_admin'); ?>" />
		</div>
	</div>

	<div class="form-group">
		<label for="password" class="col-sm-4 control-label"><?php _e('Password','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<div class="input-group">
				<input name="password" id="password" class="form-control password_toggle <?php if ($require_pass) { echo 'required'; } ?>" type="password" maxlength="<?php echo MAX_PASS_CHARS; ?>" />
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
			<input type="text" name="email" id="email" class="form-control required" value="<?php echo (isset($client_arguments['email'])) ? format_form_value($client_arguments['email']) : ''; ?>" placeholder="<?php _e("Must be valid and unique",'cftp_admin'); ?>" />
		</div>
	</div>

	<div class="form-group">
		<label for="address" class="col-sm-4 control-label"><?php _e('Address','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" name="address" id="address" class="form-control" value="<?php echo (isset($client_arguments['address'])) ? format_form_value($client_arguments['address']) : ''; ?>" />
		</div>
	</div>

	<div class="form-group">
		<label for="phone" class="col-sm-4 control-label"><?php _e('Telephone','cftp_admin'); ?></label>
		<div class="col-sm-8">
			<input type="text" name="phone" id="phone" class="form-control" value="<?php echo (isset($client_arguments['phone'])) ? format_form_value($client_arguments['phone']) : ''; ?>" />
		</div>
	</div>

	<?php
		if ($extra_fields == true) {
	?>
			<div class="form-group">
				<label for="contact" class="col-sm-4 control-label"><?php _e('Internal contact name','cftp_admin'); ?></label>
				<div class="col-sm-8">
					<input type="text" name="contact" id="contact" class="form-control" value="<?php echo (isset($client_arguments['contact'])) ? format_form_value($client_arguments['contact']) : ''; ?>" />
				</div>
			</div>

			<div class="form-group">
				<label for="max_file_size" class="col-sm-4 control-label"><?php _e('Max. upload filesize','cftp_admin'); ?></label>
				<div class="col-sm-8">
					<div class="input-group">
						<input type="text" name="max_file_size" id="max_file_size" class="form-control" value="<?php echo (isset($client_arguments['max_file_size'])) ? format_form_value($client_arguments['max_file_size']) : ''; ?>" />
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
			$memberships	= new \ProjectSend\GroupActions;
			$arguments		= array();

			/** Groups to search on based on the current user level */
			if ( CURRENT_USER_LEVEL == 9 || CURRENT_USER_LEVEL == 8 ) {
				/** An admin or client manager is creating a client account */
			}
			else {
				/** Someone is registering an account for himself */
				if ( CLIENTS_CAN_SELECT_GROUP == 'public' ) {
					$arguments['public'] = true;
				}
			}

			$sql_groups = $memberships->get_groups($arguments);
			
			$selected_groups	= ( !empty( $found_groups ) ) ? $found_groups : '';
			$my_current_groups	= array();
			/** Dirty and awful quick test, mark as selected the current groups which have requests for a client that's editing his own account */
			if ( isset( $override_groups_list ) ) {
				$selected_groups = $override_groups_list;
				if ( !empty( $found_groups ) ) {
					foreach ( $sql_groups as $array_key => $sql_group ) {
						if ( in_array( $sql_group['id'], $found_groups ) ) {
							$my_current_groups[] = $sql_group;
							unset($sql_groups[$array_key]);
						}
					}
				}
			}

			if ( count( $sql_groups ) > 0) {
	?>
				<div class="form-group assigns">
					<label for="groups_request" class="col-sm-4 control-label"><?php echo $group_label; ?></label>
					<div class="col-sm-8">
						<select multiple="multiple" name="groups_request[]" id="groups-select" class="form-control chosen-select" data-placeholder="<?php _e('Select one or more options. Type to search.', 'cftp_admin');?>">
							<?php
								foreach ( $sql_groups as $group ) {
							?>
									<option value="<?php echo $group['id']; ?>"
							<?php
										if ( !empty( $selected_groups ) && in_array( $group['id'], $selected_groups ) ) {
											echo ' selected="selected"';
										}
							?>
									><?php echo $group['name']; ?></option>
							<?php
								}
							?>
						</select>
						<?php
							if ( CURRENT_USER_LEVEL == 9 || CURRENT_USER_LEVEL == 8 ) {
						?>
								<div class="list_mass_members">
									<a href="#" class="btn btn-default add-all" data-type="assigns"><?php _e('Add all','cftp_admin'); ?></a>
									<a href="#" class="btn btn-default remove-all" data-type="assigns"><?php _e('Remove all','cftp_admin'); ?></a>
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
					<label for="active">
						<input type="checkbox" name="active" id="active" <?php echo (isset($client_arguments['active']) && $client_arguments['active'] == 1) ? 'checked="checked"' : ''; ?>> <?php _e('Active (client can log in)','cftp_admin'); ?>
					</label>
				</div>
			</div>
	<?php
		}
	?>

	<div class="form-group">
		<div class="col-sm-8 col-sm-offset-4">
			<label for="notify">
				<input type="checkbox" name="notify_upload" id="notify_upload" <?php echo (isset($client_arguments['notify_upload']) && $client_arguments['notify_upload'] == 1) ? 'checked="checked"' : ''; ?>> <?php _e('Notify new uploads by e-mail','cftp_admin'); ?>
			</label>
		</div>
	</div>

	<?php
		if ( $clients_form_type == 'new_client' ) {
	?>
			<div class="form-group">
				<div class="col-sm-8 col-sm-offset-4">
					<label for="notify_account">
						<input type="checkbox" name="notify_account" id="notify_account" <?php echo (isset($client_arguments['notify_account']) && $client_arguments['notify_account'] == 1) ? 'checked="checked"' : ''; ?>> <?php _e('Send welcome email','cftp_admin'); ?>
					</label>
				</div>
			</div>
	<?php
		}
	?>
	
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
		<button type="submit" class="btn btn-wide btn-primary"><?php echo html_output($submit_value); ?></button>
	</div>

	<?php
		if ($info_box == true) {
			$msg = __('This account information will be e-mailed to the address supplied above','cftp_admin');
			echo system_message('info',$msg);
		}
	?>
</form>
