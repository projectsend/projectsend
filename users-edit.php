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
$target_dir = UPLOADED_FILES_FOLDER.'../../img/avatars/';
/** Check if the id parameter is on the URI. */

if (isset($_GET['id'])) {
	$user_id = $_GET['id'];
	$user_id_mic = $_GET['id'];
	$page_status = (user_exists_id($user_id)) ? 1 : 2;
}
else {
	/**
	 * Return 0 if the id is not set.
	 */
	$page_status = 0;
}

$targetsignature_dir = UPLOADED_FILES_FOLDER.'../../img/avatars/signature/'.$user_id_mic.'/';
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
		if ($data['active'] == 1) { $add_user_data_active = 1; } else { $add_user_data_active = 0; }
		if ($data['notify'] == 1) { $add_user_data_notity = 1; } else { $add_user_data_notity = 0; }
	}

	$alternate = $dbh->prepare("SELECT * FROM " . TABLE_USER_EXTRA_PROFILE . " WHERE user_id=:user_id AND name = :name");
	$alternate->bindParam(':user_id', $user_id, PDO::PARAM_INT);
	$alternate->bindValue(':name', 'alternate_email');
	$alternate->execute();
	$alternate->setFetchMode(PDO::FETCH_ASSOC);
	$alternate_email_array = array();
	while ( $data = $alternate->fetch() ) {
			$alternate_email_array[] = $data['value'];
	}
}
$profile_pic = $dbh->prepare("SELECT * FROM " . TABLE_USER_EXTRA_PROFILE . " WHERE user_id=:user_id AND name = :name");
$profile_pic->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$profile_pic->bindValue(':name', 'profile_pic');
$profile_pic->execute();
$profile_pic->setFetchMode(PDO::FETCH_ASSOC);
$profile_pic_email_array = array();
while ( $data = $profile_pic->fetch() ) {
		$profile_pic_img = $data['value'];
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
	$add_user_data_name = $_POST['add_user_form_name'];
	$add_user_data_email = $_POST['add_user_form_email'];

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

	$add_user_data_notity		= (isset($_POST["add_user_form_notify"])) ? 1 : 0;
	/** Arguments used on validation and user creation. */
	$edit_arguments = array(
							'id'		=> $user_id,
							'name'		=> $add_user_data_name,
							'username'=> $add_user_data_user,
							'email'		=> $add_user_data_email,
							'role'		=> $add_user_data_level,
							'active'	=> $add_user_data_active,
							'type'		=> 'edit_user',
							'notify'	=> $add_user_data_notity,
						);

	/**
	 * If the password field, or the verification are not completed,
	 * send an empty value to prevent notices.
	 */
	$edit_arguments['password'] = (isset($_POST['add_user_form_pass'])) ? $_POST['add_user_form_pass'] : '';
	$edit_arguments['username'] = (isset($_POST['add_user_form_user'])) ? $_POST['add_user_form_user'] : $add_user_data_user;
	//$edit_arguments['password_repeat'] = (isset($_POST['add_user_form_pass2'])) ? $_POST['add_user_form_pass2'] : '';

	/** Validate the information from the posted form. */
	$edit_validate = $edit_user->validate_user($edit_arguments);
	
	/** Create the user if validation is correct. */
	if ($edit_validate == 1) {
		$edit_response = $edit_user->edit_user($edit_arguments);
		
	}

}

$page_title = __('Edit System User','cftp_admin');
if ($global_user == $add_user_data_user) {
	$page_title = __('My Account','cftp_admin');
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
				<?php if(CURRENT_USER_LEVEL == 9 ) { ?>
<a href="user-organizations.php?id=<?php echo $user_id_mic; ?>" class="btn btn-sm btn-primary right-btn"><?php if($global_level == 0) { echo "My organizations"; } else { echo 'Manage Organization';} ?></a>

	<?php } ?>
	<div class="air air-bottom-right padding-10"> <a data-toggle="modal" data-target="#cc-edit-info" class="btn txt-color-white bg-color-teal btn-sm"><i class="fa fa-pencil-square-o"></i> Edit</a></div>
              <div class="cc-user-cover"></div>
            </div>
            <div class="col-sm-12">
              <div class="row">
			
                <div class="col-sm-3 profile-pic"> 
				<?php
				if (empty($_GET['id'])){
					$pic_id = $user_id ;
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

						}?>
                </div>
                <div class="col-sm-6">
                  <h1><?php echo (isset($add_user_data_name)) ? html_output(stripslashes($add_user_data_name)) : ''; ?> <br>
                    <small> </small></h1>
                  <ul class="list-unstyled">
                    <li>
                      <p class="text-muted"> <i class="fa fa-envelope"></i>&nbsp;&nbsp;<a href="mailto:<?php echo (isset($add_user_data_email)) ? html_output(stripslashes($add_user_data_email)) : ''; ?>"><?php echo (isset($add_user_data_email)) ? html_output(stripslashes($add_user_data_email)) : ''; ?></a> </p>
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
						$msg = __('User edited correctly.','cftp_admin');
						echo system_message('ok',$msg);
					}
						if (isset($edit_response)) {
							/**
							 * Get the process state and show the corresponding ok or error message.
							 */
							switch ($edit_response['query']) {
								case 1:
									$msg = __('User edited correctly.','cftp_admin');
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
														header("Location:".SITE_URI."users-edit.php?id=".$edit_arguments['id']."&fid=1");
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
														header("Location:".SITE_URI."users-edit.php?id=".$edit_arguments['id']."&fid=1");
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

							if($edit_response['username_edited']=='1'){

								if($edit_arguments['id'] == $global_id ){
									$msg = __('Your account will be logged out in 10seconds','cftp_admin');
									echo system_message('ok',$msg);
									session_destroy() ;
									header("Location:".SITE_URI."index.php");
								} else{
									$msg = __('User edited Successfully','cftp_admin');
									echo system_message('ok',$msg);
									header("Location:".SITE_URI."users-edit.php?id=".$edit_arguments['id']);
								}
							}
							if (($global_level == 7) || ($global_user == $add_user_data_user)) {
								$user_form_type = 'edit_user_self';
							}
								else {
									$user_form_type = 'edit_user';
							}
						}
						else {
						/**
						 * If not $edit_response is set, it means we are just entering for the first time.
						 */
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
								if (($global_level == 7) || ($global_user == $add_user_data_user)) {
									$user_form_type = 'edit_user_self';
								}
									else {
										$user_form_type = 'edit_user';
								}

							}
						}
					?>
        </div>
      </div>
    </div>
  </div>
  </div>
</div>
<div id="cc-edit-info" class="modal fade" role="dialog">
	<div class="modal-dialog">

		<!-- Modal content-->
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title"><?php echo $page_title; ?></h4>
			</div>
			<div class="modal-body">
				<?php
					/**
					 * If the form was submited with errors, show them here.
					 */
					$valid_me->list_errors();
					?>
				<?php
				include('users-form.php');
				?>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
			</div>
		</div>

	</div>
</div>
	<div id="sig" class="modal fade" role="dialog">
		<div class="modal-dialog">

		<!-- Modal content-->
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" onclick="signaturemodalclose()">&times;</button>
					<h4 class="modal-title">Draw New Signature </h4>
				</div>
				<div class="modal-body">
					<input type="hidden" id="uid" value="<?php echo $user_id_mic;?>">
					<?php
						include('signature.php');
					?>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" onclick="signaturemodalclose()">Close</button>
				</div>
			</div>

		</div>
	</div>
<?php
	include('footer.php');
?>

<script type="text/javascript">
if($('#cc-edit-info').find('div.alert').length !== 0){
	 console.log("Error in edit found");
	 $('#cc-edit-info').modal('show');
 }else{
	 console.log("No errors found");
 }


 function signaturefun(argument) {
	if(argument==1){
		$('#signaturechen').removeClass('disnone');
	}else{
		$('#signaturechen').removeClass('disnone').addClass('disnone');
		$('#sig').modal('show');
	}
}
 function signaturemodalclose() {
 	$('#sig').modal('toggle');
 	$('.sig1').prop("checked", true).trigger('change');
 	signaturefun(1);
 }
</script>
