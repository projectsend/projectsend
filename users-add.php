<?php
/**
 * Show the form to add a new system user.
 *
 * @package		ProjectSend
 * @subpackage	Users
 *
 */
$allowed_levels = array(9);
require_once('bootstrap.php');

if ( !check_for_admin() ) {
    return;
}

$active_nav = 'users';

$page_title = __('Add system user','cftp_admin');

include_once ADMIN_TEMPLATES_DIR . DS . 'header.php';

/**
 * Set checkboxes as 1 to defaul them to checked when first entering
 * the form
 */
$user_arguments = array(
    'active'            => 1,
    'notify_account'    => 1,
);

if ($_POST) {
	$new_user = new \ProjectSend\UserActions();

	/**
	 * Clean the posted form values to be used on the user actions,
	 * and again on the form if validation failed.
	 */
    $user_arguments = array(
        'id'	    		=> '',
        'username'	    	=> encode_html($_POST['username']),
        'password'		    => $_POST['password'],
        //'password_repeat' => $_POST['password_repeat'],
        'name'	    		=> encode_html($_POST['name']),
        'email'		    	=> encode_html($_POST['email']),
        'level'		        => encode_html($_POST['level']),
        'max_file_size'	    => (isset($_POST["max_file_size"])) ? encode_html($_POST['max_file_size']) : '',
        'notify_account' 	=> (isset($_POST["notify_account"])) ? 1 : 0,
        'active'	    	=> (isset($_POST["active"])) ? 1 : 0,
        'type'		    	=> 'new_user',
    );

	/** Validate the information from the posted form. */
	$new_validate = $new_user->validate_user($user_arguments);
	
	/** Create the user if validation is correct. */
	if ($new_validate == 1) {
		$new_response = $new_user->create_user($user_arguments);
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

				if (isset($new_response)) {
					/**
					 * Get the process state and show the corresponding ok or error message.
					 */
					switch ($new_response['query']) {
						case 1:
							$msg = __('User added correctly.','cftp_admin');
							echo system_message('success',$msg);
	
							/** Record the action log */
							global $logger;
							$log_action_args = array(
													'action' => 2,
													'owner_id' => CURRENT_USER_ID,
													'affected_account' => $new_response['new_id'],
													'affected_account_name' => $user_arguments['name']
												);
							$new_record_action = $logger->add_entry($log_action_args);
	
						break;
						case 0:
							$msg = __('There was an error. Please try again.','cftp_admin');
							echo system_message('danger',$msg);
						break;
					}
					/**
					 * Show the ok or error message for the email notification.
					 */
					switch ($new_response['email']) {
						case 2:
							$msg = __('A welcome message was not sent to the new user.','cftp_admin');
							echo system_message('success',$msg);
						break;
						case 1:
							$msg = __('A welcome message with login information was sent to the new user.','cftp_admin');
							echo system_message('success',$msg);
						break;
						case 0:
							$msg = __("E-mail notification couldn't be sent.",'cftp_admin');
							echo system_message('danger',$msg);
						break;
					}
				}
				else {
					/**
					 * If not $new_response is set, it means we are just entering for the first time.
					 * Include the form.
					 */
					$user_form_type = 'new_user';
					include_once FORMS_DIR . DS . 'users.php';
				}
			?>

		</div>
	</div>
</div>

<?php
	include_once ADMIN_TEMPLATES_DIR . DS . 'footer.php';
