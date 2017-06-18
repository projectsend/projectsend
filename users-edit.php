<?php
/**
 * Show the form to edit a system user.
 *
 * @package		ProjectSend
 * @subpackage	Users
 *
 */
$allowed_levels = array(9,8,7);
require_once('sys.includes.php');

$active_nav = 'users';

/** Create the object */
$edit_user = new UserActions();

/** Check if the id parameter is on the URI. */
if (isset($_GET['id'])) {
	$user_id = $_GET['id'];
	$page_status = (user_exists_id($user_id)) ? 1 : 2;
}
else {
	/**
	 * Return 0 if the id is not set.
	 */
	$page_status = 0;
}

/**
 * Get the user information from the database to use on the form.
 */
if ($page_status === 1) {
	$editing = $dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE id=:id");
	$editing->bindParam(':id', $user_id, PDO::PARAM_INT);
	$editing->execute();
	$editing->setFetchMode(PDO::FETCH_ASSOC);

	while ( $data = $editing->fetch() ) {
		$add_user_data_name = $data['name'];
		$add_user_data_user = $data['user'];
		$add_user_data_email = $data['email'];
		$add_user_data_level = $data['level'];
		$add_user_data_maxfilesize	= $data['max_file_size'];
		if ($data['active'] == 1) { $add_user_data_active = 1; } else { $add_user_data_active = 0; }
	}
}

/**
 * Compare the client editing this account to the on the db.
 */
if ($global_level != 9) {
	if ($global_user != $add_user_data_user) {
		$page_status = 3;
	}
}

if ($_POST) {
	/**
	 * If the user is not an admin, check if the id of the user
	 * that's being edited is the same as the current logged in one.
	 */
	if ($global_level != 9) {
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
	$add_user_data_name			= $_POST['add_user_form_name'];
	$add_user_data_email		= $_POST['add_user_form_email'];
	$add_user_data_maxfilesize	= (isset($_POST["add_user_form_maxfilesize"])) ? $_POST["add_user_form_maxfilesize"] : '';

	/**
	 * Edit level only when user is not Uploader (level 7) or when
	 * editing other's account (not own).
	 */	
	$edit_level_active = true;
	if ($global_level == 7) {
		$edit_level_active = false;
	}
	else {
		if ($global_user == $add_user_data_user) {
			$edit_level_active = false;
		}
	}
	if ($edit_level_active === true) {
		/** Default level to 7 just in case */
		$add_user_data_level = (isset($_POST["add_user_form_level"])) ? $_POST['add_user_form_level'] : '7';
		$add_user_data_active = (isset($_POST["add_user_form_active"])) ? 1 : 0;
	}

	/** Arguments used on validation and user creation. */
	$edit_arguments = array(
							'id'			=> $user_id,
							'name'			=> $add_user_data_name,
							'email'			=> $add_user_data_email,
							'role'			=> $add_user_data_level,
							'active'		=> $add_user_data_active,
							'max_file_size'	=> $add_user_data_maxfilesize,
							'type'			=> 'edit_user'
						);

	/**
	 * If the password field, or the verification are not completed,
	 * send an empty value to prevent notices.
	 */
	$edit_arguments['password'] = (isset($_POST['add_user_form_pass'])) ? $_POST['add_user_form_pass'] : '';
	//$edit_arguments['password_repeat'] = (isset($_POST['add_user_form_pass2'])) ? $_POST['add_user_form_pass2'] : '';

	/** Validate the information from the posted form. */
	$edit_validate = $edit_user->validate_user($edit_arguments);
	
	/** Create the user if validation is correct. */
	if ($edit_validate == 1) {
		$edit_response = $edit_user->edit_user($edit_arguments);
	}

	$location = BASE_URI . 'users-edit.php?id=' . $user_id . '&status=' . $edit_response['query'];
	header("Location: $location");
	die();
}

$page_title = __('Edit system user','cftp_admin');
if ($global_user == $add_user_data_user) {
	$page_title = __('My account','cftp_admin');
}

include('header.php');

?>

<div id="main">
	<h2><?php echo $page_title; ?></h2>

	<?php
		if (isset($_GET['status'])) {
			switch ($_GET['status']) {
				case 1:
					$msg = __('User edited correctly.','cftp_admin');
					echo system_message('ok',$msg);

					$saved_user = get_user_by_id($user_id);
					/** Record the action log */
					$new_log_action = new LogActions();
					$log_action_args = array(
											'action' => 13,
											'owner_id' => CURRENT_USER_ID,
											'affected_account' => $user_id,
											'affected_account_name' => $saved_user['username'],
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
	?>
	
	<div class="container">
		<div class="row">
			<div class="col-xs-12 col-xs-offset-0 col-sm-8 col-sm-offset-2 col-md-6 col-md-offset-3 white-box">
				<div class="white-box-interior">
		
					<?php
						/**
						 * If the form was submited with errors, show them here.
						 */
						$valid_me->list_errors();
					?>
					
					<?php
						$direct_access_error = __('This page is not intended to be accessed directly.','cftp_admin');
						if ($page_status === 0) {
							$msg = __('No user was selected.','cftp_admin');
							echo system_message('error',$msg);
							echo '<p>'.$direct_access_error.'</p>';
						}
						else if ($page_status === 2) {
							$msg = __('There is no user with that ID number.','cftp_admin');
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
							if ($global_level == 7) {
								$user_form_type = 'edit_user_self';
							}
							else {
								if ($global_user == $add_user_data_user) {
									$user_form_type = 'edit_user_self';
								}
								else {
									$user_form_type = 'edit_user';
								}
							}
							include('users-form.php');
						}
					?>

				</div>
			</div>
		</div>		
	</div>
</div>

<?php
	include('footer.php');
?>