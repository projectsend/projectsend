<?php
/**
 * Show the form to edit an existing client.
 *
 * @package		ProjectSend
 * @subpackage	Clients
 *
 */
$allowed_levels = array(9,8,0);
require_once('sys.includes.php');

$active_nav = 'clients';

$database->MySQLDB();

/** Create the object */
$edit_client = new ClientActions();

/** Check if the id parameter is on the URI. */
if (isset($_GET['id'])) {
	$client_id = mysql_real_escape_string($_GET['id']);
	/**
	 * Check if the id corresponds to a real client.
	 * Return 1 if true, 2 if false.
	 **/
	$page_status = (client_exists_id($client_id)) ? 1 : 2;
}
else {
	/**
	 * Return 0 if the id is not set.
	 */
	$page_status = 0;
}

/**
 * Get the clients information from the database to use on the form.
 */
if ($page_status === 1) {
	$editing = $database->query("SELECT * FROM tbl_users WHERE id=$client_id");
	while($data = mysql_fetch_array($editing)) {
		$add_client_data_name = $data['name'];
		$add_client_data_user = $data['user'];
		$add_client_data_email = $data['email'];
		$add_client_data_addr = $data['address'];
		$add_client_data_phone = $data['phone'];
		$add_client_data_intcont = $data['contact'];
		if ($data['notify'] == 1) { $add_client_data_notity = 1; } else { $add_client_data_notity = 0; }
		if ($data['active'] == 1) { $add_client_data_active = 1; } else { $add_client_data_active = 0; }
	}
}

/**
 * Compare the client editing this account to the on the db.
 */
if ($global_level == 0) {
	if (isset($add_client_data_user) && $global_user != $add_client_data_user) {
		$page_status = 3;
	}
}

if ($_POST) {
	/**
	 * If the user is not an admin, check if the id of the client
	 * that's being edited is the same as the current logged in one.
	 */
	if ($global_level == 0 || $global_level == 7) {
		if ($user_id != CURRENT_USER_ID) {
			die();
		}
	}

	/**
	 * Clean the posted form values to be used on the user actions,
	 * and again on the form if validation failed.
	 * Also, overwrites the values gotten from the database so if
	 * validation failed, the new unsaved values are shown to avoid
	 * having to type them again.
	 */
	$add_client_data_name = mysql_real_escape_string($_POST['add_client_form_name']);
	$add_client_data_user = mysql_real_escape_string($_POST['add_client_form_user']);
	$add_client_data_email = mysql_real_escape_string($_POST['add_client_form_email']);
	/** Optional fields: Address, Phone, Internal Contact, Notify */
	$add_client_data_addr = (isset($_POST["add_client_form_address"])) ? mysql_real_escape_string($_POST["add_client_form_address"]) : '';
	$add_client_data_phone = (isset($_POST["add_client_form_phone"])) ? mysql_real_escape_string($_POST["add_client_form_phone"]) : '';
	$add_client_data_intcont = (isset($_POST["add_client_form_intcont"])) ? mysql_real_escape_string($_POST["add_client_form_intcont"]) : '';
	$add_client_data_notity = (isset($_POST["add_client_form_notify"])) ? 1 : 0;

	if ($global_level != 0) {
		$add_client_data_active = (isset($_POST["add_client_form_active"])) ? 1 : 0;
	}

	/** Arguments used on validation and client creation. */
	$edit_arguments = array(
							'id' => $client_id,
							'username' => $add_client_data_user,
							'name' => $add_client_data_name,
							'email' => $add_client_data_email,
							'address' => $add_client_data_addr,
							'phone' => $add_client_data_phone,
							'contact' => $add_client_data_intcont,
							'notify' => $add_client_data_notity,
							'active' => $add_client_data_active,
							'type' => 'edit_client'
						);

	/**
	 * If the password field, or the verification are not completed,
	 * send an empty value to prevent notices.
	 */
	$edit_arguments['password'] = (isset($_POST['add_client_form_pass'])) ? $_POST['add_client_form_pass'] : '';
	//$edit_arguments['password_repeat'] = (isset($_POST['add_client_form_pass2'])) ? $_POST['add_client_form_pass2'] : '';

	/** Validate the information from the posted form. */
	$edit_validate = $edit_client->validate_client($edit_arguments);
	
	/** Create the client if validation is correct. */
	if ($edit_validate == 1) {
		$edit_response = $edit_client->edit_client($edit_arguments);
	}
}

$page_title = __('Edit client','cftp_admin');
if (isset($add_client_data_user) && $global_user == $add_client_data_user) {
	$page_title = __('My account','cftp_admin');
}

include('header.php');

?>

<div id="main">
	<h2><?php echo $page_title; ?></h2>
	
	<div class="whiteform whitebox">
		
		<?php
			/**
			 * If the form was submited with errors, show them here.
			 */
			$valid_me->list_errors();
		?>
		
		<?php
			if (isset($edit_response)) {
				/**
				 * Get the process state and show the corresponding ok or error message.
				 */
				switch ($edit_response['query']) {
					case 1:
						$msg = __('Client edited correctly.','cftp_admin');
						echo system_message('ok',$msg);

						$saved_client = get_client_by_id($client_id);
						/** Record the action log */
						$new_log_action = new LogActions();
						$log_action_args = array(
												'action' => 14,
												'owner_id' => $global_id,
												'affected_account' => $client_id,
												'affected_account_name' => $saved_client['username'],
												'get_user_real_name' => true
											);
						$new_record_action = $new_log_action->log_action_save($log_action_args);
					break;
					case 0:
						$msg = __('There was an error. Please try again.','cftp_admin');
						echo system_message('error',$msg);
					break;
				}
			}
			else {
			/**
			 * If not $edit_response is set, it means we are just entering for the first time.
			 */
			 	$direct_access_error = __('This page is not intended to be accessed directly.','cftp_admin');
			 	if ($page_status === 0) {
					$msg = __('No client was selected.','cftp_admin');
					echo system_message('error',$msg);
					echo '<p>'.$direct_access_error.'</p>';
				}
				else if ($page_status === 2) {
					$msg = __('There is no client with that ID number.','cftp_admin');
					echo system_message('error',$msg);
					echo '<p>'.$direct_access_error.'</p>';
				}
				else if ($page_status === 3) {
					$msg = __("Your account type doesn't allow you to access this feature.",'cftp_admin');
					echo system_message('error',$msg);
				}
				else {
					/**
					 * Include the form.
					 */
					if ($global_level != 0) {
						$clients_form_type = 'edit_client';
					}
					else {
						$clients_form_type = 'edit_client_self';
					}
					include('clients-form.php');
				}
			}
		?>

	</div>
</div>

<?php
	$database->Close();
	include('footer.php');
?>