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
		$add_client_data_phone = (isset($_POST["add_client_form_phone"])) ? encode_html($_POST["add_client_form_phone"]) : '';
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
								'phone'		=> $add_client_data_phone,
								'contact'	=> $add_client_data_intcont,
								'notify'	=> $add_client_data_notity,
								'type'		=> 'new_client',
							);

		$new_arguments['active']	= (CLIENTS_AUTO_APPROVE == 0) ? 0 : 1;
		$new_arguments['recaptcha']	= ( defined('RECAPTCHA_AVAILABLE') ) ? $recaptcha_request : null;
	
		/** Validate the information from the posted form. */
		$new_validate = $new_client->validate_client($new_arguments);
		
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

		<h2><?php echo $page_title; ?></h2>

		<div class="whiteform whitebox">
		
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
					include('clients-form.php');
				}
			}
		?>

			<div class="login_form_links">
				<p><a href="<?php echo BASE_URI; ?>" target="_self"><?php _e('Go back to the homepage.','cftp_admin'); ?></a></p>
			</div>
		</div>
	
	</div> <!-- main -->

	<?php
		default_footer_info();

		load_js_files();
	?>

</body>
</html>
<?php
	ob_end_flush();
?>