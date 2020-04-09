<style type="text/css">
	.zoom {
	    transition: transform .2s;
	    height: 80px;
	}


	.zoom:hover {
	    transform: scale(1.5);
	}
</style>
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
$targetsignature_dir = UPLOADED_FILES_FOLDER.'../../img/avatars/signature/'.$client_id.'/';

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
<div id="content">
  <div class="container-fluid">
    <div class="row"> 
    <div class="col-md-12">
      <!------------------------------------------------------------------------------------>
      <h1 class="page-title txt-color-blueDark"><i class="fa-fw fa fa-home"></i>My Account</h1>
      <div class="widget cc-widget-area">
      <div class="col-sm-12 col-md-offset-3 col-md-6 col-lg-6 cc-profile-wrap">
      
          <div class="row">
            <div class="col-sm-12">
				<a <?php if($add_client_data_name !=''){?> href="client-organizations.php?id=<?php echo $client_id; ?>"  <?php } if($add_client_data_name ==''){ echo"disabled"; } ?> class="btn btn-sm btn-primary right-btn"><?php if($global_level == 0) { echo "My organizations"; } else { echo 'Manage Organization';} ?></a>
				<div class="air air-bottom-right padding-10"> <a href="clients-edit.php?id=<?php echo $client_id; ?>"  class="btn txt-color-white bg-color-teal btn-sm"><i class="fa fa-pencil-square-o"></i> Edit</a></div>
              <div class="cc-user-cover"></div>
            </div>
            <div class="col-sm-12">
              <div class="row">
			
                <div class="col-sm-3 profile-pic"> 
				<?php

				if (empty($_GET['id'])){
					$pic_id = $client_id ;
				} else {
					$pic_id = $_GET['id'] ; 
				}
				$profile_pic = $dbh->prepare("SELECT * FROM " . TABLE_USER_EXTRA_PROFILE . " WHERE user_id=:user_id AND name = :name");
				$profile_pic->bindParam(':user_id', $pic_id, PDO::PARAM_INT);
				$profile_pic->bindValue(':name', 'profile_pic');
				$profile_pic->execute();
				$profile_pic->setFetchMode(PDO::FETCH_ASSOC);
				$profile_pic_email_array = array();
				while ( $data = $profile_pic->fetch() ) {
						$profile_pic_img = $data['value'];
				}

					if(!empty($profile_pic_img)){?>

								<img src="<?php echo "img/avatars/".$profile_pic_img;?>?<?php echo rand();?>" alt="demo user" class="zoom">
					<?php }else{
				?>
						<img src="img/avatars/no-image.png" alt="demo user">

				<?php }?>
				<?php
					$signature_pic = $dbh->prepare("SELECT * FROM " . TABLE_USER_EXTRA_PROFILE . " WHERE user_id=:user_id AND name = :name");
					$signature_pic->bindParam(':user_id', $pic_id, PDO::PARAM_INT);
					$signature_pic->bindValue(':name', 'signature_pic');
					$signature_pic->execute();
					$signature_pic->setFetchMode(PDO::FETCH_ASSOC);
					while ( $data = $signature_pic->fetch() ) {
							$signature_pic_img = $data['value'];
							$signature_type = $data['sig_type'];
					}

						if(!empty($signature_pic_img)){
							if($signature_type==1){
								if(file_exists("img/avatars/signature/".$pic_id."/temp/".$signature_pic_img)){?>
									<img src="<?php echo "img/avatars/signature/".$pic_id."/temp/".$signature_pic_img;?>?<?php echo rand();?>" alt="demo user" style="top: -5px;" class="zoom">
								<?php }else{ 
									echo '<img src="img/avatars/no-image.png" alt="demo user" style="top: -5px;" >';
								}
							}else{
								if(file_exists("img/avatars/tempsignature/".$pic_id."/temp/".$signature_pic_img)){?>
									<img src="<?php echo "img/avatars/tempsignature/".$pic_id."/temp/".$signature_pic_img;?>?<?php echo rand();?>" alt="demo user" style="top: -5px;" class="zoom">
								<?php }else{ 
									echo '<img src="img/avatars/no-image.png" alt="demo user" style="top: -5px;">';
								}
							}

						}else{
							echo '<img src="img/avatars/no-image.png" alt="demo user" style="top: -5px;">';
						}?>
                </div>
                <div class="col-sm-6">
                  <h1><?php echo (isset($add_client_data_user)) ? html_output(stripslashes($add_client_data_user)) : ''; ?> <br>
                    <small> </small></h1>
                  <ul class="list-unstyled">
                    <li>
                      <p class="text-muted"> <i class="fa fa-envelope"></i>&nbsp;&nbsp;<a href="mailto:<?php echo (isset($add_client_data_email)) ? html_output(stripslashes($add_client_data_email)) : ''; ?>"><?php echo (isset($add_client_data_email)) ? html_output(stripslashes($add_client_data_email)) : ''; ?></a> </p>
                    </li>
                  </ul>
                  <br>
                </div>
              </div>
            </div>
          </div>
          
          <!-- end row --> 
          
      </div>
      
      <!------------------------------------------------------------------------------------>
          <?php
	          		if(isset($_GET['fid'])){
						$msg = __('Client edited correctly.','cftp_admin');
						echo system_message('ok',$msg);
					}
						if (isset($edit_response)) {
							/**
							 * Get the process state and show the corresponding ok or error message.
							 */
							switch ($edit_response['query']) {
								case 1:
									$msg = __('Client edited correctly.','cftp_admin');
									echo system_message('ok',$msg);
			
									$saved_user = get_user_by_id($user_id_mic);
									/** Record the action log */
									$new_log_action = new LogActions();
									$log_action_args = array(
															'action' => 13,
															'owner_id' => $global_id,
															'affected_account' => $user_id_mic,
															'affected_account_name' => $saved_user['username'],
															'get_user_real_name' => true
														);
									$new_record_action = $new_log_action->log_action_save($log_action_args);
									/* Insert alternative emails to table prefix-user_extra_profile */
									$alternate_emails = $_POST['add_user_form_email_alternate'];
									if(!empty($alternate_emails)){
										$statement = $dbh->query("DELETE FROM " . TABLE_USER_EXTRA_PROFILE . " WHERE user_id =".$user_id_mic." AND name='alternate_email'");

										foreach($alternate_emails as $a_email){
												if(!empty($a_email)){
														$alternate_email_save = $dbh->query( "INSERT INTO " . TABLE_USER_EXTRA_PROFILE . " (user_id, name, value) VALUES (".$user_id_mic.",'alternate_email','".$a_email."' ) ");
												}
										}
									}

									/*For avatar upload start */
								if($_FILES){
									if($_FILES["userfiles"]["error"] == 0) {
									// echo 'updated';die();
										
										if (!file_exists($target_dir)) {
												mkdir($target_dir, 0777, true);
										}
										$target_file = $target_dir;
										$uploadOk = 1;
										$target_file = $target_dir . "/".basename($_FILES["userfiles"]["name"]);
										$imageFileType = pathinfo($target_file,PATHINFO_EXTENSION);
										$fl_name = $user_id_mic.".".$imageFileType;
										$target_file = $target_dir.$fl_name;
										$uploadOk = 1;
										// Check if image file is a actual image or fake image
										$check = getimagesize($_FILES["userfiles"]["tmp_name"]);
										if($check !== false) {
											//echo "File is an image - " . $check["mime"] . ".";
											$uploadOk = 1;
										} else {
										//	echo "File is not an image.";
											$uploadOk = 0;
										}
										// Allow certain file formats
										if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
										&& $imageFileType != "gif" ) {
											//	echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
												$uploadOk = 0;
										}

										// echo("<br>Upload Ok = ".$uploadOk);
										// Check if $uploadOk is set to 0 by an error
										if ($uploadOk == 0) {
												echo "Sorry, your file was not uploaded.";
										// if everything is ok, try to upload file
										} else {
											if (file_exists($target_file)) {
													unlink($target_file);
													// echo("<br>Unlinked Oldfile");
											}
											if (move_uploaded_file($_FILES["userfiles"]["tmp_name"], $target_file)) {
												// echo("<br>Moved Uploaded file");
												// echo("<br> Fl name : ".$fl_name);
												if(!empty($fl_name)){
													$statement = $dbh->prepare("DELETE FROM " . TABLE_USER_EXTRA_PROFILE . " WHERE user_id =".$user_id_mic." AND name='profile_pic'");
											    $statement->execute();
													// echo("DONE");

													$alternate_email_save = $dbh->prepare( "INSERT INTO " . TABLE_USER_EXTRA_PROFILE . " (user_id, name, value) VALUES (".$user_id_mic.",'profile_pic','".$fl_name."' ) ");
													$prochange=$alternate_email_save->execute();
													if($prochange==true){
														// header("Location:".SITE_URI."users-edit.php?id=".$edit_arguments['id']."&fid=1");
														header("Location:".SITE_URI."usersedit.php?id=".$edit_arguments['id']);
													}
													// echo("DONE");
												}

											} else {
												echo "Sorry, there was an error uploading your file.";
											}
										}
									}
									// var_dump($_FILES);die();
									if($_FILES["usersignature"]["error"] == 0) {
									// echo 'updated';die();
										
										if (!file_exists($targetsignature_dir)) {
												mkdir($targetsignature_dir, 0777, true);
										}
										if (!file_exists($targetsignature_dir.'temp/')) {
											mkdir($targetsignature_dir.'temp/', 0777, true);
										}
										$target_file = $targetsignature_dir;
										$uploadOk = 1;
										$target_file = $targetsignature_dir . "/".basename($_FILES["usersignature"]["name"]);
										$imageFileType = pathinfo($target_file,PATHINFO_EXTENSION);
										$fl_name = $user_id_mic.".".$imageFileType;
										$target_file = $targetsignature_dir.$fl_name;
										$uploadOk = 1;
										// Check if image file is a actual image or fake image
										$check = getimagesize($_FILES["usersignature"]["tmp_name"]);
										if($check !== false) {
											//echo "File is an image - " . $check["mime"] . ".";
											$uploadOk = 1;
										} else {
										//	echo "File is not an image.";
											$uploadOk = 0;
										}
										// Allow certain file formats
										if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
										&& $imageFileType != "gif" ) {
											//	echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
												$uploadOk = 0;
										}

										// echo("<br>Upload Ok = ".$uploadOk);
										// Check if $uploadOk is set to 0 by an error
										if ($uploadOk == 0) {
												echo "Sorry, your file was not uploaded.";
										// if everything is ok, try to upload file
										} else {
											if (file_exists($target_file)) {
													unlink($target_file);
													// echo("<br>Unlinked Oldfile");
											}
											if (move_uploaded_file($_FILES["usersignature"]["tmp_name"], $target_file)) {
												$aes = new AESENCRYPT ();					
												$result  = $aes->encryptFile($fl_name,'upload',$user_id_mic);
								// WORKING DECRYPTION CODE START
												// if($result){
													$result1  = $aes->decryptFile($fl_name,'upload',$user_id_mic);
												// echo "<pre>"; print_r($result1); echo "</pre>"; exit;
												// }
								// WORKING DECRYPTION CODE END
												
												if(!empty($fl_name)){
													$statement = $dbh->prepare("DELETE FROM " . TABLE_USER_EXTRA_PROFILE . " WHERE user_id =".$user_id_mic." AND name='signature_pic'");
											    	$statement->execute();
													// echo("DONE");

													$alternate_email_save = $dbh->prepare( "INSERT INTO " . TABLE_USER_EXTRA_PROFILE . " (user_id, name, value,sig_type) VALUES (".$user_id_mic.",'signature_pic','".$fl_name."',1 ) ");
													$prochange=$alternate_email_save->execute();
													if($prochange==true){
														// header("Location:".SITE_URI."users-edit.php?id=".$edit_arguments['id']."&fid=1");
														header("Location:".SITE_URI."users-edit.php?id=".$edit_arguments['id']);
													}
													// echo("DONE");
												}

											} else {
												echo "Sorry, there was an error uploading your file.";
											}
										}
									}

								}
										//	exit;
										/*For avatar upload end */
								break;
								case 0:
									$msg = __('There was an error. Please try again.','cftp_admin');
									echo system_message('error',$msg);
								break;
							}

						}
					?>
        </div>
      </div>
    </div>
  </div>
  </div>
</div>

	
<?php
	include('footer.php');
?>

