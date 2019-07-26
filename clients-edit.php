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

/** Create the object */
$edit_client = new ClientActions();
$target_dir = UPLOADED_FILES_FOLDER.'../../img/avatars/';
/** Check if the id parameter is on the URI. */
if (isset($_GET['id'])) {
	$client_id = $_GET['id'];
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

	$editing = $dbh->prepare("SELECT * FROM " . TABLE_USERS . " WHERE id=:id");
	$editing->bindParam(':id', $client_id, PDO::PARAM_INT);
	$editing->execute();
	$editing->setFetchMode(PDO::FETCH_ASSOC);

	while ( $data = $editing->fetch() ) {
		$add_client_data_name		= $data['name'];
		$add_client_data_user		= $data['user'];
		$add_client_data_email		= $data['email'];
		$add_client_data_addr		= $data['address'];
		$add_client_data_addr2		= $data['address2'];
		$add_client_data_city		= $data['city'];
		$add_client_data_state		= $data['state'];
		$add_client_data_zip		= $data['zipcode'];
		$add_client_data_phone		= $data['phone'];
		$add_client_data_intcont	= $data['contact'];
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
		if ($client_id != CURRENT_USER_ID) {
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
	$add_client_data_name		= $_POST['add_client_form_name'];
	$add_client_data_user		= $_POST['add_client_form_user'];
	$add_client_data_email		= $_POST['add_client_form_email'];
	/** Optional fields: Address, Phone, Internal Contact, Notify */
	$add_client_data_addr		= (isset($_POST["add_client_form_address"])) ? $_POST["add_client_form_address"] : '';
	$add_client_data_addr2		= (isset($_POST["add_client_form_address_line2"])) ? $_POST["add_client_form_address_line2"] : '';
	$add_client_data_city		= (isset($_POST["add_client_city"])) ? $_POST["add_client_city"] : '';
	$add_client_data_state		= (isset($_POST["add_client_form_state"])) ? $_POST["add_client_form_state"] : '';
	$add_client_data_zip		= (isset($_POST["add_client_form_zip"])) ? $_POST["add_client_form_zip"] : '';
	$add_client_data_phone		= (isset($_POST["add_client_form_phone"])) ? $_POST["add_client_form_phone"] : '';
	$add_client_data_intcont	= (isset($_POST["add_client_form_intcont"])) ? $_POST["add_client_form_intcont"] : '';
	$add_client_data_notity		= (isset($_POST["add_client_form_notify"])) ? 1 : 0;

	if ($global_level != 0) {
		$add_client_data_active	= (isset($_POST["add_client_form_active"])) ? 1 : 0;
	}

	/** Arguments used on validation and client creation. */
	$edit_arguments = array(
							'id'		=> $client_id,
							'username'	=> $add_client_data_user,
							'name'		=> $add_client_data_name,
							'email'		=> $add_client_data_email,
							'address'	=> $add_client_data_addr,
							'address2'	=> $add_client_data_addr2,
							'city'		=> $add_client_data_city,
							'state'		=> $add_client_data_state,
							'phone'		=> $add_client_data_phone,
							'zipcode'	=> $add_client_data_zip,
							'contact'	=> $add_client_data_intcont,
							'notify'	=> $add_client_data_notity,
							'active'	=> $add_client_data_active,
							'type'		=> 'edit_client'
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
	<div class="container">
		<div class="row">
        <h2><?php echo $page_title; ?></h2>
		<div class="col-xs-12 col-xs-offset-0 col-sm-8 col-sm-offset-2 col-md-6 ">
			<a <?php if($add_client_data_name !=''){?> href="client-organizations.php?id=<?php echo $client_id; ?>"  <?php } if($add_client_data_name ==''){ echo"disabled"; } ?> class="btn btn-sm btn-primary right-btn"><?php if($global_level == 0) { echo "My organizations"; } else { echo 'Manage Organization';} ?></a>
		</div>
			<!--div class="col-xs-12 col-xs-offset-0 col-sm-8 col-sm-offset-2 col-md-6 col-md-offset-3 white-box"-->
			<div class="col-xs-12 col-xs-offset-0 col-sm-8 col-sm-offset-2 col-md-6 white-box">

				<div class="white-box-interior">


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
									if($global_level == 0){
										unset($_SESSION['logout']);
										header("location:".BASE_URI.'process.php?do=logout');
									}
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

								/*For avatar upload start */
						if($_FILES){


								if (!file_exists($target_dir)) {
										mkdir($target_dir, 0777, true);
								}
								$target_file = $target_dir;
								$uploadOk = 1;
								$target_file = $target_dir . "/".basename($_FILES["userfiles"]["name"]);
								$imageFileType = pathinfo($target_file,PATHINFO_EXTENSION);
								$fl_name = $client_id.".".$imageFileType;
								$target_file = $target_dir.$fl_name;
								$uploadOk = 1;
								// Check if image file is a actual image or fake image
								$check = getimagesize($_FILES["userfiles"]["tmp_name"]);
								if($check !== false) {
							//		echo "File is an image - " . $check["mime"] . ".";
									$uploadOk = 1;
								} else {
									echo "File is not an image.";
									$uploadOk = 0;
								}
								// Allow certain file formats
								if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
								&& $imageFileType != "gif" ) {
										echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
										$uploadOk = 0;
								}
								// Check if $uploadOk is set to 0 by an error
								if ($uploadOk == 0) {
										echo "Sorry, your file was not uploaded.";
								// if everything is ok, try to upload file
								} else {
									if (file_exists($target_file)) {
											unlink($target_file);
									}
									if (move_uploaded_file($_FILES["userfiles"]["tmp_name"], $target_file)) {
										if(!empty($fl_name)){
											$statement = $dbh->query("DELETE FROM " . TABLE_USER_EXTRA_PROFILE . " WHERE user_id =".$client_id." AND name='profile_pic'");

											$alternate_email_save = $dbh->query( "INSERT INTO " . TABLE_USER_EXTRA_PROFILE . " (user_id, name, value) VALUES (".$client_id.",'profile_pic','".$fl_name."' ) ");
										}

									} else {
								echo "Sorry, there was an error uploading your file.";
									}
								}

								}
										/*For avatar upload end */

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
		</div>
	</div>
</div>

<?php
	include('footer.php');
?>
