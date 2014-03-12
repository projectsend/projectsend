<?php
/**
 * Show the form to reset the password.
 *
 * @package		ProjectSend
 *
 */
$allowed_levels = array(9,8,7,0);
require_once('sys.includes.php');

$page_title = __('Lost password','cftp_admin');

include('header-unlogged.php');
	$show_form = 'enter_email';

	if (!empty($_GET['token']) && !empty($_GET['user'])) {
		$got_token	= mysql_real_escape_string($_GET['token']);
		$got_user	= mysql_real_escape_string($_GET['user']);

		/**
		 * Get the user's id
		 */
		$query_user_id	= $database->query("SELECT id, user FROM tbl_users WHERE user = '$got_user'");
		$result_user_id	= mysql_fetch_array($query_user_id);
		$got_user_id	= $result_user_id['id'];

		$sql_request = $database->query("SELECT * FROM tbl_password_reset WHERE BINARY token = '$got_token' AND user_id = '$got_user_id'");
		$count_request = mysql_num_rows($sql_request);
		if ($count_request > 0){
			$token_info	= mysql_fetch_array($sql_request);
			$request_id = $token_info['id'];

			/** Check if the token has been used already */
			if ($token_info['used'] == '1') {
				/** Clean the ID to fix security holes */
				$got_user_id = '';
				$errorstate = 'token_used';
			}

			/** Check if the token has expired. */
			elseif (time() - strtotime($token_info['timestamp']) > 60*60*24) {
			$got_user_id = '';
				$errorstate = 'token_expired';
			}

			else {
				$show_form = 'enter_new_password';
			}
		}
		else {
			$got_user_id = '';
			$errorstate = 'token_invalid';
			$show_form = 'none';
		}
	}

	/** The form was submitted */
	if ($_POST) {
		/**
		 * Clean the posted form values.
		 */
		$form_type = encode_html($_POST['form_type']);
		
		switch ($form_type) {

			/**
			 * The form submited contains a new token request
			 */
			case 'new_request':

				$reset_password_email = encode_html($_POST['reset_password_email']);

				$sql_user = $database->query("SELECT id, user, email FROM tbl_users WHERE email='$reset_password_email'");
				$count_user = mysql_num_rows($sql_user);
				if ($count_user > 0){
					/** Email exists on the database */
					$row		= mysql_fetch_array($sql_user);
					$id			= $row['id'];
					$username	= $row['user'];
					$email		= $row['email'];
					$token		= generateRandomString(32);
					
					/**
					 * Count how many request were made by this user today.
					 * No more than 3 unused should exist at a time.
					 */
					$sql_amount		= $database->query("SELECT * FROM tbl_password_reset WHERE user_id = '$id' AND used = '0' AND timestamp > NOW() - INTERVAL 1 DAY");
					$count_requests	= mysql_num_rows($sql_amount);
					if ($count_requests >= 3){
						$errorstate = 'too_many_today';
					}
					else {
						$sql_pass = $database->query("INSERT INTO tbl_password_reset (user_id, token)"
														."VALUES ('$id', '$token' )");
			
						/** Send email */
						$notify_user = new PSend_Email();
						$email_arguments = array(
														'type' => 'password_reset',
														'address' => $email,
														'username' => $username,
														'token' => $token
													);
						$notify_send = $notify_user->psend_send_email($email_arguments);
			
						if ($notify_send == 1){
							$state['email'] = 1;
						}
						else {
							$state['email'] = 0;
						}
					}
					
					$show_form = 'none';
				}
				else {
					$errorstate = 'email_not_found';
				}
			break;

			/**
			 * The form submited contains the new password
			 */
			case 'new_password':
				if (!empty($got_user_id)) {
					$reset_password_new = mysql_real_escape_string($_POST['reset_password_new']);
	
					/** Password checks */
					$valid_me->validate('completed',$reset_password_new,$validation_no_pass);
					$valid_me->validate('password',$reset_password_new,$validation_valid_pass.' '.$validation_valid_chars);
					$valid_me->validate('pass_rules',$reset_password_new,$validation_rules_pass);
					$valid_me->validate('length',$reset_password_new,$validation_length_pass,MIN_PASS_CHARS,MAX_PASS_CHARS);
			
					if ($valid_me->return_val) {
	
						$enc_password = $hasher->HashPassword($reset_password_new);
				
						if (strlen($enc_password) >= 20) {
				
							$state['hash'] = 1;
				
							/** SQL queries */
							$edit_pass_query = "UPDATE tbl_users SET 
												password = '$enc_password' 
												WHERE id = $got_user_id";
					
							$sql_query = $database->query($edit_pass_query);
							
							
					
							if ($sql_query) {
								$state['reset'] = 1;
		
								$set_used_query = "UPDATE tbl_password_reset SET 
													used = '1' 
													WHERE id = $request_id";
						
								$sql_query = $database->query($set_used_query);
			
								$show_form = 'none';
							}
							else {
								$state['reset'] = 0;
							}
						}
						else {
							$state['hash'] = 0;
						}
					}
				}
				
			break;
		}
	}
	?>

		<h2><?php echo $page_title; ?></h2>

		<div class="container">
			<div class="row">
				<div class="span4 offset4 white-box">
					<div class="white-box-interior box-reset-password">
						<?php
							/**
							 * If the form was submited with errors, show them here.
							 */
							$valid_me->list_errors();
						?>
				
						<?php
							/**
							 * Show status message
							 */
							if (isset($errorstate)) {
								switch ($errorstate) {
									case 'email_not_found':
										$login_err_message = __("The supplied email address does not correspond to any user.",'cftp_admin');
										break;
									case 'token_invalid':
										$login_err_message = __("The request is not valid.",'cftp_admin');
										break;
									case 'token_expired':
										$login_err_message = __("This request has expired. Please make a new one.",'cftp_admin');
										break;
									case 'token_used':
										$login_err_message = __("This request has already been completed. Please make a new one.",'cftp_admin');
										break;
									case 'too_many_today':
										$login_err_message = __("There are 3 unused requests done in less than 24 hs. Please wait until one expires (1 day since made) to make a new one.",'cftp_admin');
										break;
								}
				
								echo system_message('error',$login_err_message,'login_error');
							}

							/**
							 * Show the ok or error message for the email.
							 */
							if (isset($state['email'])) {
								switch ($state['email']) {
									case 1:
										$msg = __('An e-mail with further instructions has been sent. Please check your inbox to proceed.','cftp_admin');
										echo system_message('ok',$msg);
									break;
									case 0:
										$msg = __("E-mail couldn't be sent.",'cftp_admin');
										$msg .= ' ' . __("If the problem persists, please contact an administrator.",'cftp_admin');
										echo system_message('error',$msg);
									break;
								}
							}

							/**
							 * Show the ok or error message for the password reset.
							 */
							if (isset($state['reset'])) {
								switch ($state['reset']) {
									case 1:
										$msg = __('Your new password has been set. You can now log in using it.','cftp_admin');
										echo system_message('ok',$msg);
									break;
									case 0:
										$msg = __("Your new password couldn't be set.",'cftp_admin');
										$msg .= ' ' . __("If the problem persists, please contact an administrator.",'cftp_admin');
										echo system_message('error',$msg);
									break;
								}
							}
							 
							switch ($show_form) {
								case 'enter_email':
								default:
						?>
									<script type="text/javascript">
										$(document).ready(function() {
											$("form").submit(function() {
												clean_form(this);
									
												is_complete(this.reset_password_email,'<?php echo $validation_no_email; ?>');
												is_email(this.reset_password_email,'<?php echo $validation_invalid_mail; ?>');
									
												// show the errors or continue if everything is ok
												if (show_form_errors() == false) { return false; }
											});
										});
									</script>
									
									<form action="reset-password.php" name="resetpassword" method="post" role="form">
										<fieldset>
											<input type="hidden" name="form_type" id="form_type" value="new_request" />

											<label class="control-label" for="reset_password_email"><?php _e('E-mail','cftp_admin'); ?></label>
											<input type="text" name="reset_password_email" id="reset_password_email" class="span3" />

											<p><?php _e("Please enter your account's e-mail address. You will receive a link to continue the process.",'cftp_admin'); ?></p>

											<div class="inside_form_buttons">
												<button type="submit" name="submit" class="btn btn-wide btn-primary"><?php _e('Continue','cftp_admin'); ?></button>
											</div>
										</fieldset>
									</form>
						<?php
								break;
								case 'enter_new_password':
						?>
									<script type="text/javascript">
										$(document).ready(function() {
											$("form").submit(function() {
												clean_form(this);
									
												is_complete(this.reset_password_new,'<?php echo $validation_no_pass; ?>');
												is_length(this.reset_password_new,<?php echo MIN_PASS_CHARS; ?>,<?php echo MAX_PASS_CHARS; ?>,'<?php echo $validation_length_pass; ?>');
												is_password(this.reset_password_new,'<?php $chars = addslashes($validation_valid_chars); echo $validation_valid_pass." ".$chars; ?>');
									
												// show the errors or continue if everything is ok
												if (show_form_errors() == false) { return false; }
											});
										});
									</script>
									
									<form action="reset-password.php?token=<?php echo $got_token; ?>&user=<?php echo $got_user; ?>" name="newpassword" method="post" role="form">
										<fieldset>
											<input type="hidden" name="form_type" id="form_type" value="new_password" />

											<label class="control-label" for="reset_password_new"><?php _e('New password','cftp_admin'); ?></label>

											<input type="password" name="reset_password_new" id="reset_password_new" class="span3 password_toggle" />
											<button type="button" class="btn password_toggler pass_toggler_show"><i class="icon-eye-open"></i></button>
											<?php password_notes(); ?>
											
											<p><?php _e("Please enter your desired new password. After that, you will be able to log in normally.",'cftp_admin'); ?></p>

											<div class="inside_form_buttons">
												<button type="submit" name="submit" class="btn btn-wide btn-primary"><?php _e('Continue','cftp_admin'); ?></button>
											</div>
										</fieldset>
									</form>
						<?php
								break;
								case 'none':
						?>
						<?php
								break;
							 }
						?>

						<div class="login_form_links">
							<p><a href="<?php echo BASE_URI; ?>" target="_self"><?php _e('Go back to the homepage.','cftp_admin'); ?></a></p>
						</div>

					</div>
				</div>
			</div>
		</div> <!-- container -->
	</div> <!-- main (from header) -->

	<?php default_footer_info(false); ?>

</body>
</html>
<?php
	$database->Close();
	ob_end_flush();
?>