<?php
/**
 * Show the form to register a new account for yourself.
 *
 * @package		ProjectSend
 * @subpackage	Clients
 *
 */
$allowed_levels = array(9,8,7,0);
require_once('sys.includes.php');

$page_title = __('Register new account','cftp_admin');

include('header-unlogged.php');

	/** The form was submitted */
	if ($_POST) {

		if ( defined('RECAPTCHA_AVAILABLE') ) {
			$recaptcha_user_ip		= $_SERVER["REMOTE_ADDR"];
			$recaptcha_response		= $_POST['g-recaptcha-response'];
			$recaptcha_secret_key	= RECAPTCHA_SECRET_KEY;
			$recaptcha_request		= file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_secret_key}&response={$recaptcha_response}&remoteip={$recaptcha_user_ip}");
		}

		$new_client = new ClientActions();

		/**
		 * Clean the posted form values to be used on the clients actions,
		 * and again on the form if validation failed.
		 */
		$add_client_data_name = encode_html($_POST['add_client_form_name']);
		$add_client_data_user = encode_html($_POST['add_client_form_user']);
		$add_client_data_email = encode_html($_POST['add_client_form_email']);
		/** Optional fields: Address, Phone, Internal Contact, Notify */
		$add_client_data_addr = (isset($_POST["add_client_form_address"])) ? encode_html($_POST["add_client_form_address"]) : '';
		$add_client_data_addr2 = (isset($_POST["add_client_form_address_line2"])) ? encode_html($_POST["add_client_form_address_line2"]) : '';
		$add_client_data_city = (isset($_POST["add_client_city"])) ? encode_html($_POST["add_client_city"]) : '';
		$add_client_data_state = (isset($_POST["add_client_form_state"])) ? encode_html($_POST["add_client_form_state"]) : '';
		$add_client_data_zip 	= (isset($_POST["add_client_form_zip"])) ? encode_html($_POST["add_client_form_zip"]) : '';
		$add_client_data_phone = (isset($_POST["add_client_form_phone"])) ? encode_html($_POST["add_client_form_phone"]) : '';
		$add_client_data_level = (isset($_POST["select_user"])) ? encode_html($_POST["select_user"]) : '';
		$add_client_data_groups = (isset($_POST["add_group_form_members"])) ? encode_html($_POST["add_group_form_members"]) : '';
		$add_client_data_intcont = (isset($_POST["add_client_form_intcont"])) ? encode_html($_POST["add_client_form_intcont"]) : '';
		$add_client_data_notity = (isset($_POST["add_client_form_notify"])) ? 1 : 0;

		/** Arguments used on validation and client creation. */
		$new_arguments = array(
								'id'		=> '',
								'username'	=> $add_client_data_user,
								'password'	=> $_POST['add_client_form_pass'],
								//'password_repeat' => $_POST['add_client_form_pass2'],
								'name'		=> $add_client_data_name,
								'email'		=> $add_client_data_email,
								'address'	=> $add_client_data_addr,
								'address2'	=> $add_client_data_addr2,
								'city'		=> $add_client_data_city,
								'state'		=> $add_client_data_state,
								'zipcode'	=> $add_client_data_zip,
								'phone'		=> $add_client_data_phone,
								'level'		=> $add_client_data_level,
								'groupid'		=> $add_client_data_groups,
								'contact'	=> $add_client_data_intcont,
								'notify'	=> $add_client_data_notity,
								'type'		=> 'new_client',
							);


		$new_arguments['active']	= (CLIENTS_AUTO_APPROVE == 0) ? 0 : 1;
		$new_arguments['recaptcha']	= ( defined('RECAPTCHA_AVAILABLE') ) ? $recaptcha_request : null;

		/** Validate the information from the posted form. */
		$new_validate = $new_client->validate_client($new_arguments);
		//var_dump($new_validate);exit;
		/** Create the client if validation is correct. */
		if ($new_validate == 1) {
			$new_response = $new_client->create_client($new_arguments);

			/**
			 * Check if the option to auto-add to a group
			 * is active.
			 */
			if (CLIENTS_AUTO_GROUP != '0') {
				$admin_name = 'SELFREGISTERED';
				$client_id = $new_response['new_id'];
				$group_id = CLIENTS_AUTO_GROUP;

				$add_to_group = $dbh->prepare("INSERT INTO " . TABLE_MEMBERS . " (added_by,client_id,group_id)"
													." VALUES (:admin, :id, :group)");
				$add_to_group->bindParam(':admin', $admin_name);
				$add_to_group->bindParam(':id', $client_id, PDO::PARAM_INT);
				$add_to_group->bindParam(':group', $group_id);
				$add_to_group->execute();
			}

			$notify_admin = new PSend_Email();
			$email_arguments = array(
											'type' => 'new_client_self',
											'address' => ADMIN_EMAIL_ADDRESS,
											'username' => $add_client_data_user,
											'name' => $add_client_data_name
										);
			$notify_admin_status = $notify_admin->psend_send_email($email_arguments);
		}
	}
	?>

		<!--<h2 class="hidden"><?php //echo $page_title; ?></h2>-->
        <!--------------------------------------------------------------------------------------------------->
        <div id="content" class="container">

				<div class="row">
                <div class="col-md-12">
                <?php
							if (CLIENTS_CAN_REGISTER == '0') {
								$msg = __('Client self registration is not allowed. If you need an account, please contact a system administrator.','cftp_admin');
								echo system_message('error',$msg);
							}
							else {
								/**
								 * If the form was submited with errors, show them here.
								 */
								$valid_me->list_errors();

								if (isset($new_response)) {
									/**
									 * Get the process state and show the corresponding ok or error messages.
									 */

									$error_msg = '</p><br /><p>';
									$error_msg .= __('Please contact a system administrator.','cftp_admin');
									//var_dump($new_response);exit;
									switch ($new_response['actions']) {
										case 1:
											$msg = __('Account added correctly.','cftp_admin');
											echo system_message('ok',$msg);

											if (CLIENTS_AUTO_APPROVE == 0) {
												$msg = __('Please remember that an administrator needs to approve your account before you can log in.','cftp_admin');
											}
											else {
												$msg = __('You may now log in with your new credentials.','cftp_admin');
											}
											echo system_message('info',$msg);

											/** Record the action log */
											$new_log_action = new LogActions();
											$log_action_args = array(
																	'action' => 4,
																	'owner_id' => $new_response['new_id'],
																	'affected_account' => $new_response['new_id'],
																	'affected_account_name' => $add_client_data_name
																);
											$new_record_action = $new_log_action->log_action_save($log_action_args);
										break;
										case 0:
											$msg = __('There was an error. Please try again.','cftp_admin');
											$msg .= $error_msg;
											echo system_message('error',$msg);
										break;
										case 2:
											$msg = __('A folder for this account could not be created. Probably because of a server configuration.','cftp_admin');
											$msg .= $error_msg;
											echo system_message('error',$msg);
										break;
										case 3:
											$msg = __('The account could not be created. A folder with this name already exists.','cftp_admin');
											$msg .= $error_msg;
											echo system_message('error',$msg);
										break;
									}
									/**
									 * Show the ok or error message for the email notification.
									 */
									switch ($new_response['email']) {
										case 1:
											$msg = __('An e-mail notification with login information was sent to the specified address.','cftp_admin');
											echo system_message('ok',$msg);
										break;
										case 0:
											$msg = __("E-mail notification couldn't be sent.",'cftp_admin');
											echo system_message('error',$msg);
										break;
									}
								}
								else {
									/**
									 * If not $new_response is set, it means we are just entering for the first time.
									 * Include the form.
									 */
									$clients_form_type = 'new_client_self';
									//include('clients-form.php'); ?>
                                    <!---------------------------------------------------------------------------->
                                    <div class="col-xs-12 col-sm-12 col-md-7 col-lg-7 hidden-xs hidden-sm">
						<h1 class="txt-color-red login-header-big">SmartAdmin</h1>
						<div class="hero">

							<div class="pull-left login-desc-box-l">
								<h4 class="paragraph-header">It's Okay to be Smart. Experience the simplicity of SmartAdmin, everywhere you go!</h4>
								<div class="login-app-icons">
									<a href="index.php" class="btn btn-danger btn-sm">Already on MicroHealth Send? login here ! </a>
								</div>
							</div>

							<img src="img/demo/iphoneview.png" alt="" class="pull-right display-image" style="width:210px">

						</div>

						<div class="row">
							<div class="col-xs-12 col-sm-12 col-md-6 col-lg-6">
								<h5 class="about-heading">About SmartAdmin - Are you up to date?</h5>
								<p>
									Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa.
								</p>
							</div>
							<div class="col-xs-12 col-sm-12 col-md-6 col-lg-6">
								<h5 class="about-heading">Not just your average template!</h5>
								<p>
									Et harum quidem rerum facilis est et expedita distinctio. Nam libero tempore, cum soluta nobis est eligendi voluptatem accusantium!
								</p>
							</div>
						</div>

					</div>
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
	/** User is creating a new client */
	case 'new_client':
		$submit_value = __('Add client','cftp_admin');
		$disable_user = false;
		$require_pass = true;
		$form_action = 'clients-add.php';
		$info_box = true;
		$extra_fields = true;
		break;
	/** User is editing an existing client */
	case 'edit_client':
		$submit_value = __('Save client','cftp_admin');
		$disable_user = false;
		$require_pass = false;
		$form_action = 'clients-edit.php?id='.$client_id;
		$info_box = false;
		$extra_fields = true;
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
		break;
	/** A client is editing his profile */
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
<div class="col-xs-12 col-sm-12 col-md-5 col-lg-5">
	<div class="well no-padding">

		<form action="<?php echo $form_action; ?>" name="addclient" id="smart-form-register" method="post" class="smart-form client-form">
     <header>Registration is FREE*</header>
     <fieldset>
     <section>

	<?php
		if ($info_box == true) {
			$msg = __('This account information will be e-mailed to the address supplied above','cftp_admin');
			echo system_message('info',$msg);
		}
	?>
     </section>
     <section>
     	Name
		<label class="input">
        	<i class="icon-append fa fa-user"></i>
            <input type="text" name="add_client_form_name" id="add_client_form_name" class="form-control required" value="<?php echo (isset($add_client_data_name)) ? html_output(stripslashes($add_client_data_name)) : ''; ?>" placeholder="<?php echo $name_placeholder; ?>" />
			<b class="tooltip tooltip-bottom-right">Needed to enter the website</b>
        </label>
	</section>
     <section>Log in username
     	<label class="input">
	 		<i class="icon-append fa fa-user-circle"></i>
     		<input type="text" name="add_client_form_user" id="add_client_form_user" class="form-control <?php if (!$disable_user) { echo 'required'; } ?>" minlength="4" maxlength="<?php echo MAX_USER_CHARS; ?>" value="<?php echo (isset($add_client_data_user)) ? html_output(stripslashes($add_client_data_user)) : ''; ?>" <?php if ($disable_user) { echo 'readonly'; }?> placeholder="<?php _e("Must be alphanumeric",'cftp_admin'); ?>" />
	 		<b class="tooltip tooltip-bottom-right">Needed to enter the website</b>
     	</label>
	 </section>

     <section><?php _e('Password','cftp_admin'); ?>
     <label class="input">

	 <i class="icon-append fa fa-user"></i>
     <div class="col-md-10">
     <input name="add_client_form_pass" id="add_client_form_pass" class="form-control password_toggle <?php if ($require_pass) { echo 'required'; } ?>" type="password" maxlength="<?php echo MAX_PASS_CHARS; ?>" />
     </div>
     <div class="col-md-2">
     <button type="button" name="generate_password" id="generate_password" class="btn btn-default btn-sm btn_generate_password" data-ref="add_client_form_pass" data-min="<?php echo MAX_GENERATE_PASS_CHARS; ?>" data-max="<?php echo MAX_GENERATE_PASS_CHARS; ?>"><?php _e('Generate','cftp_admin'); ?></button>
     </div>
     </label>

			<?php echo password_notes(); ?>
	 </section>

     <section><?php _e('E-mail','cftp_admin'); ?>
     <label class="input">

	 <i class="icon-append fa fa-envelope"></i>
     <input type="text" name="add_client_form_email" id="add_client_form_email" class="form-control required" value="<?php echo (isset($add_client_data_email)) ? html_output(stripslashes($add_client_data_email)) : ''; ?>" placeholder="<?php _e("Must be valid and unique",'cftp_admin'); ?>" />
     </label>
	 </section>

     <section><?php _e('Address Line 1','cftp_admin'); ?>
     <label class="input">

	 <i class="icon-append fa fa-address-card-o"></i>
     <input type="text" name="add_client_form_address" id="add_client_form_address" class="form-control" value="<?php echo (isset($add_client_data_addr)) ? html_output(stripslashes($add_client_data_addr)) : ''; ?>" />
     </label>
	 </section>
     <section><?php _e('Address Line 2','cftp_admin'); ?>
     <label class="input">

	 <i class="icon-append fa fa-address-card-o"></i>
     <input type="text" name="add_client_form_address_line2" id="add_client_form_address_line2" class="form-control" value="<?php echo (isset($add_client_data_addr2)) ? html_output(stripslashes($add_client_data_addr2)) : ''; ?>" />
     </label>
	 </section>
     <!-- city -->
     <section><?php _e('City','cftp_admin'); ?>
     <label class="input">

	 <i class="icon-append fa fa-building-o"></i>
     <input type="text" name="add_client_city" id="add_client_city" class="form-control" value="<?php echo (isset($add_client_data_city)) ? html_output(stripslashes($add_client_data_city)) : ''; ?>" />
     </label>
	 </section>
     <!-- State -->
     <section><?php _e('State','cftp_admin'); ?>
     <label class="input">

	 <i class="icon-append fa fa-building"></i>
     <input type="text" name="add_client_form_state" id="add_client_form_state" class="form-control" value="<?php echo (isset($add_client_data_state)) ? html_output(stripslashes($add_client_data_state)) : ''; ?>" />
     </label>
	 </section>
     <!-- zicode -->
     <section><?php _e('Zip','cftp_admin'); ?>
     <label class="input">

	 <i class="icon-append fa fa-location-arrow"></i>
     <input type="text" name="add_client_form_zip" id="add_client_form_zip" class="form-control" value="<?php echo (isset($add_client_data_zip)) ? html_output(stripslashes($add_client_data_zip)) : ''; ?>" />
     </label>
	 </section>
     <!-- Telephone -->
     <section><?php _e('Telephone','cftp_admin'); ?>
     <label class="input">

	 <i class="icon-append fa fa-phone"></i>
     <input type="text" name="add_client_form_phone" id="add_client_form_phone" class="form-control" value="<?php echo (isset($add_client_data_phone)) ? html_output(stripslashes($add_client_data_phone)) : ''; ?>" />
     </label>
	 </section>
     <!-- User/client -->
     <section><?php _e('User','cftp_admin'); ?>
         <label class="input"><i class="icon-append fa fa-user-o"></i>
            <select name="select_user" id="select_user" class="form-control">
            			<option value="">--Select--</option>
                        <option value="0">Client</option>
                        <option  value="1">User</option>
            </select>
         </label>
	 </section>
     <!-- Organisation choose -->
     <?php _e('User','cftp_admin'); ?>
         <label for="add_group_form_members" class="col-sm-4 control-label"><?php _e('Members','cftp_admin'); ?></label>
            <select multiple="multiple" id="members-select" class="form-control chosen-select" name="add_group_form_members[]" data-placeholder="<?php _e('Select one or more options. Type to search.', 'cftp_admin');?>">

			</select>
         </label>


     <?php
			if ($extra_fields == true) {
	 ?>
     <section><?php _e('Internal contact name','cftp_admin'); ?>
     <label class="input">

	 <i class="icon-append fa fa-user"></i>
     <input type="text" name="add_client_form_intcont" id="add_client_form_intcont" class="form-control" value="<?php echo (isset($add_client_data_intcont)) ? html_output(stripslashes($add_client_data_intcont)) : ''; ?>" />
     </label>
	 </section>

     <section><?php _e('Internal contact name','cftp_admin'); ?>
     <label class="input">
	 <i class="icon-append fa fa-user"></i>
     <input type="checkbox" name="add_client_form_active" id="add_client_form_active" <?php echo (isset($add_client_data_active) && $add_client_data_active == 1) ? 'checked="checked"' : ''; ?>> <?php _e('Active (client can log in)','cftp_admin'); ?>

     </label>
	 </section>

       	<?php
			}
		?>






	<section>
			<label for="add_client_form_notify">
				<input type="checkbox" name="add_client_form_notify" id="add_client_form_notify" <?php echo (isset($add_client_data_notity) && $add_client_data_notity == 1) ? 'checked="checked"' : ''; ?>> <?php _e('Notify new uploads by e-mail','cftp_admin'); ?>
			</label>
	</section>

	<?php
		if ( $clients_form_type == 'new_client_self' ) {
			if ( defined('RECAPTCHA_AVAILABLE') ) {
	?>
				<section><?php _e('Verification','cftp_admin'); ?>
						<div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
				</section>
	<?php
			}
		}
	?>
	<section>
	<div class="inside_form_buttons">
		<button type="submit" name="submit" class="btn cc-btn-reg btn-wide btn-primary"><?php echo html_output($submit_value); ?></button>
	</div>
    </section>
</fieldset>
</form>
	</div>
    <p class="note text-center">*FREE Registration ends on October 2015.</p>
						<h5 class="text-center">- Or sign up using -</h5>
						<ul class="list-inline text-center">
                        <li>
      <?php if(GOOGLE_SIGNIN_ENABLED == '1'): ?>
		<a href="<?php echo $auth_url; ?>" name="Sign in with Google" class="btn btn-default btn-circle"><i class="fa fa-google"></i></a></a>
		<?php endif; ?>
        </li>
        <?php if(FACEBOOK_SIGNIN_ENABLED == '1'): ?>
        <li> <a href="sociallogin/login-with.php?provider=Facebook" name="Sign in with Facebook" class="btn btn-primary btn-circle"><i class="fa fa-facebook"></i></a> </li>
        <?php endif; ?>
        <?php if(TWITTER_SIGNIN_ENABLED == '1'): ?>
        <li> <a href="sociallogin/login-with.php?provider=Twitter" name="Sign in with Twitter" class="btn btn-info btn-circle"><i class="fa fa-twitter"></i></a> </li>
        <?php endif; ?>
        <?php if(YAHOO_SIGNIN_ENABLED == '1'): ?>
        <li> <a href="sociallogin/login-with.php?provider=yahoo" name="Sign in with yahoo" class="btn btn-danger btn-circle"><i class="fa fa-yahoo" aria-hidden="true"></i></a> </li>
        <?php endif; ?>
        <?php if(LINKEDIN_SIGNIN_ENABLED == '1'): ?>
        <li> <a href="sociallogin/login-with.php?provider=LinkedIn" name="Sign in with linkedin" class="btn btn-warning btn-circle"><i class="fa fa-linkedin"></i></a> </li>
        <li> <a href="sociallogin/ldap-login.php" name="Sign in with LDAP" class="btn btn-success btn-circle" title="LDAP">
        <i class="fa fa-universal-access"></i></a> </li>
        <?php endif; ?>
      </ul>
</div>
 <!---------------------------------------------------------------------------->
                                    <?php
								}
							}
						?>
                </div>
				</div>
			</div>
        <!--------------------------------------------------------------------------------------------------->

	</div> <!-- main -->
<div class="cc-footer">
	<?php
		default_footer_info();

		load_js_files();
	?>
</div>

</body>
</html>

<?php
	ob_end_flush();
?>

<script type="text/javascript">
$('#select_user').change(function(){

	$.ajax({
		method: "POST",
		url:'ajax.php',
		data: {selectedid:$(this).val(),action:'get_organization'},
		success: function (data) {
			console.log(data);
			$('#members-select').html(data);
			//$('.trailer_price').val(data);
		}
	});
  });
</script>
