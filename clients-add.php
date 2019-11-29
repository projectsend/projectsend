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
$cc_active_page = 'client_add';

$page_title = __('Add Client','cftp_admin');
$target_dir = UPLOADED_FILES_FOLDER.'../../img/avatars/';

include('header.php');

/**
 * Set checkboxes as 1 to defaul them to checked when first entering
 * the form
 */
$add_client_data_notity = 1;
$add_client_data_active = 1;

if ($_POST) {
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
	$add_client_data_level = (isset($_POST["select_user"])) ? 1 : 0;
	$add_client_data_intcont = (isset($_POST["add_client_form_intcont"])) ? encode_html($_POST["add_client_form_intcont"]) : '';
	$add_client_data_notity = (isset($_POST["add_client_form_notify"])) ? 1 : 0;
	$add_client_data_active = (isset($_POST["add_client_form_active"])) ? 1 : 0;

	/** Arguments used on validation and client creation. */
	$new_arguments = array(
							'id' => '',
							'username' => $add_client_data_user,
							'password' => $_POST['add_client_form_pass'],
							//'password_repeat' => $_POST['add_client_form_pass2'],
							'name' => $add_client_data_name,
							'email' => $add_client_data_email,
							'address' => $add_client_data_addr,
							'address2' => $add_client_data_addr2,
							'city'		=> $add_client_data_city,
							'state'		=> $add_client_data_state,
							'zipcode'	=> $add_client_data_zip,
							'phone' => $add_client_data_phone,
							'level'		=> $add_client_data_level,
							'contact' => $add_client_data_intcont,
							'notify' => $add_client_data_notity,
							'active' => $add_client_data_active,
							'type' => 'new_client'
						);

	/** Validate the information from the posted form. */
	$new_validate = $new_client->validate_client($new_arguments);
	/** Create the client if validation is correct. */
	if ($new_validate == 1) {
		$new_response = $new_client->create_client($new_arguments);
	}
	
}
?>

<div id="main">
  <div id="content"> 
    
    <!-- Added by B) -------------------->
    <div class="container-fluid">
      <div class="row">
        <div class="col-md-12">
          <h1 class="page-title txt-color-blueDark"><i class="fa-fw fa fa-user"></i>&nbsp;<?php echo $page_title; ?></h1>
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
						if (isset($new_response)) {
							/**
							 * Get the process state and show the corresponding ok or error messages.
							 */
							switch ($new_response['actions']) {
								case 1:
									$msg = __('Client added correctly.','cftp_admin');
									echo system_message('ok',$msg);
			
									/** Record the action log */
									$new_log_action = new LogActions();
									$log_action_args = array(
															'action' => 3,
															'owner_id' => $global_id,
															'affected_account' => $new_response['new_id'],
															'affected_account_name' => $add_client_data_name
														);
									$new_record_action = $new_log_action->log_action_save($log_action_args);
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
										if($_FILES["userfiles"]["name"]!=''){
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
												} /*else {
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
												}*/
										}
									}

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
								case 1:
									$msg = __('An e-mail notification with login information was sent to your client.','cftp_admin');
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
							include('clients-form.php');
						}
					?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
	include('footer.php');
?>
