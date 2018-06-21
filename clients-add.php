<?php
/**
 * Show the form to add a new client.
 *
 * @package		ProjectSend
 * @subpackage	Clients
 *
 */
$allowed_levels = array(9,8);
require_once('sys.includes.php');

$active_nav = 'clients';

$page_title = __('Add client','cftp_admin');

include_once ADMIN_TEMPLATES_DIR . DS . 'header.php';

/**
 * Set checkboxes as 1 to default them to checked when first entering
 * the form
 */
$add_client_data_notify_upload = 1;
$add_client_data_active = 1;
$add_client_data_notify_account = 1;

if ($_POST) {
	$new_client = new ProjectSend\ClientActions();

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
	$add_client_data_maxfilesize = (isset($_POST["add_client_form_maxfilesize"])) ? encode_html($_POST["add_client_form_maxfilesize"]) : '';
	$add_client_data_notify_upload = (isset($_POST["add_client_form_notify_upload"])) ? 1 : 0;
	$add_client_data_notify_account = (isset($_POST["add_client_form_notify_account"])) ? 1 : 0;
	$add_client_data_active = (isset($_POST["add_client_form_active"])) ? 1 : 0;

	/** Arguments used on validation and client creation. */
	$new_arguments = array(
							'id'			=> '',
							'username'		=> $add_client_data_user,
							'password'		=> $_POST['add_client_form_pass'],
							//'password_repeat' => $_POST['add_client_form_pass2'],
							'name'			=> $add_client_data_name,
							'email'			=> $add_client_data_email,
							'address'		=> $add_client_data_addr,
							'phone'			=> $add_client_data_phone,
							'contact'		=> $add_client_data_intcont,
							'notify_upload' 	=> $add_client_data_notify_upload,
							'notify_account' 	=> $add_client_data_notify_account,
							'active'		=> $add_client_data_active,
							'max_file_size'	=> $add_client_data_maxfilesize,
							'type'			=> 'new_client',
						);

	/** Validate the information from the posted form. */
	$new_validate = $new_client->validate_client($new_arguments);
	
	/** Create the client if validation is correct. */
	if ($new_validate == 1) {
		$new_response = $new_client->create_client($new_arguments);
		
		$add_to_groups = (!empty( $_POST['add_client_group_request'] ) ) ? $_POST['add_client_group_request'] : '';
		if ( !empty( $add_to_groups ) ) {
			array_map('encode_html', $add_to_groups);
			$memberships	= new ProjectSend\MembersActions;
			$arguments		= array(
									'client_id'	=> $new_response['new_id'],
									'group_ids'	=> $add_to_groups,
									'added_by'	=> CURRENT_USER_USERNAME,
								);
	
			$memberships->client_add_to_groups($arguments);
		}
	}
	
}
?>

<div class="col-xs-12 col-sm-12 col-lg-6">
	<div class="white-box">
		<div class="white-box-interior">
			<?php
				/**
				 * If the form was submited with errors, show them here.
				 */
				$validation->list_errors();
			?>
			
			<?php
				if (isset($new_response)) {
					/**
					 * Get the process state and show the corresponding ok or error messages.
					 */
					switch ($new_response['actions']) {
						case 1:
							$msg = __('Client added correctly.','cftp_admin');
							echo system_message('ok',$msg);
	
							/** Record the action log */
							$new_log_action = new ProjectSend\LogActions();
							$log_action_args = array(
													'action' => 3,
													'owner_id' => CURRENT_USER_ID,
													'affected_account' => $new_response['new_id'],
													'affected_account_name' => $add_client_data_name
												);
							$new_record_action = $new_log_action->log_action_save($log_action_args);
						break;
						case 0:
							$msg = __('There was an error. Please try again.','cftp_admin');
							echo system_message('error',$msg);
						break;
					}
					/**
					 * Show the ok or error message for the email notification.
					 */
					switch ($new_response['email']) {
						case 2:
							$msg = __('A welcome message was not sent to your client.','cftp_admin');
							echo system_message('ok',$msg);
						break;
						case 1:
							$msg = __('A welcome message with login information was sent to your client.','cftp_admin');
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
					$clients_form_type = 'new_client';
					include_once FORMS_DIR . DS . 'clients.php';
				}
			?>
		</div>
	</div>
</div>

<?php
	include_once ADMIN_TEMPLATES_DIR . DS . 'footer.php';
