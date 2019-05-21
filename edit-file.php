
<?php
/**
 * Edit a file name or description.
 * Files can only be edited by the uploader and level 9 or 8 users.
 *
 * @package ProjectSend
 */
 

$load_scripts	= array(
						'datepicker',
						'chosen',
					); 

$allowed_levels = array(9,8,7,0);
require_once('sys.includes.php');

//Add a session check here
if(!check_for_session()) {
    header("location:" . BASE_URI . "index.php");
}

$active_nav = 'files';

$page_title = __('Edit File','cftp_admin');
include('header.php');

define('CAN_INCLUDE_FILES', true);

/**
 * The file's id is passed on the URI.
 */
if (!empty($_GET['file_id'])) {
	$this_file_id = $_GET['file_id'];
}
$get_prev_id = isset($_GET['page_id']) ? $_GET['page_id'] : '';
//1 for send.php
//2 for 

switch($get_prev_id) {
	case 1:
	$get_prev_url = BASE_URI.'sent.php';
	break;
	case 2:
	$get_prev_url = BASE_URI.'inbox.php';
	break;
	case 3:
	$get_prev_url = BASE_URI.'outbox.php';
	break;
	case 4:
	$get_prev_url = BASE_URI.'draft.php';
	break;
	case 5:
	$get_prev_url = BASE_URI.'expired.php';
	break;
	case 6:
	$get_prev_url = BASE_URI.'public-files.php';
	break;
	case 7:
	$get_prev_url = BASE_URI.'manage-files.php';
	break;
	default:
	$get_prev_id = '#';
}
//var_dump($get_prev_url);


/** Fill the users array that will be used on the notifications process */
$users = array();
$statement = $dbh->prepare("SELECT id, name, email, level FROM " . TABLE_USERS . " ORDER BY name ASC");
$statement->execute();
$statement->setFetchMode(PDO::FETCH_ASSOC);
while( $row = $statement->fetch() ) {
	$users[$row["id"]] = $row["name"];
	//if ($row["level"] == '0') {
		//$clients[$row["id"]] = $row["name"];
	//}
	$clients[$row["id"]] = $row["name"]." : ".$row["email"];
  $cliEmail[$row["id"]] =$row["email"];
}

/** Fill the groups array that will be used on the form */
$groups = array();
$statement = $dbh->prepare("SELECT id, name FROM " . TABLE_GROUPS . " ORDER BY name ASC");
$statement->execute();
$statement->setFetchMode(PDO::FETCH_ASSOC);
while( $row = $statement->fetch() ) {
	$groups[$row["id"]] = $row["name"];
}

/** Fill the categories array that will be used on the form */
$categories = array();
$get_categories = get_categories();


/**
 * Get the user level to determine if the uploader is a
 * system user or a client.
 */
$current_level = get_current_user_level();
?>

<div id="main">
  <div id="content"> 
    
    <!-- Added by B) -------------------->
    <div class="container-fluid">
      <div class="row">
        <div class="col-md-12">
          <h1 class="page-title txt-color-blueDark"><i class="fa-fw fa fa-home"></i><?php echo $page_title; ?></h1>
          <?php
function randomPassword() {
	$alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
	$pass = array(); //remember to declare $pass as an array
	$alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
	for ($i = 0; $i < 8; $i++) {
		$n = rand(0, $alphaLength);
		$pass[] = $alphabet[$n];
	}
	return implode($pass); //turn the array into a string
}
		/**
		 * Show an error message if no ID value is passed on the URI.
		 */
		if(empty($this_file_id)) {
			$no_results_error = 'no_id_passed';
		}
		else {
			$sql = $dbh->prepare("SELECT * FROM " . TABLE_FILES . " WHERE id = :id");
			$sql->bindParam(':id', $this_file_id, PDO::PARAM_INT);
			$sql->execute();

			/**
			 * Count the files assigned to this client. If there is none, show
			 * an error message.
			 */
			$count = $sql->rowCount();
			if ( $count == 0 ) {
				$no_results_error = 'id_not_exists';
			}
	
			/**
			 * Continue if client exists and has files under his account.
			 */
			$sql->setFetchMode(PDO::FETCH_ASSOC);
			while( $row = $sql->fetch() ) {
				$edit_file_info['url'] = $row['url'];
				$edit_file_info['id'] = $row['id'];
				$edit_file_info['expiry_date'] = $row['expiry_date'];

				$edit_file_allowed = array(7,0);
				if (in_session_or_cookies($edit_file_allowed)) {
					if ($row['uploader'] != $global_user) {
						$no_results_error = 'not_uploader';
					}
				}
			}
		}

		/** Show the error if it is defined */
		if (isset($no_results_error)) {
			switch ($no_results_error) {
				case 'no_id_passed':
					$no_results_message = __('Please go to the clients or groups administration page, select "Manage files" from any client and then click on "Edit" on any file to return here.','cftp_admin');;
					break;
				case 'id_not_exists':
					$no_results_message = __('There is not file with that ID number.','cftp_admin');;
					break;
				case 'not_uploader':
					$no_results_message = __("You don't have permission to edit this file.",'cftp_admin');;
					break;
			}
	?>
	<style media="screen">
			.whitebox_text {
			background-color: #c26565;
			color: #fff;
			border-radius: 8px;
			}
	</style>
          <div class="whiteform whitebox whitebox_text"> <?php echo $no_results_message; ?> </div>
          <?php
		}
		else {

			/**
			 * See what clients or groups already have this file assigned.
			 */
			$file_on_clients = array();
			$file_on_groups = array();

			if ( isset($_POST['submit'] ) ) {
				
				
				$assignments = $dbh->prepare("SELECT file_id, client_id, group_id FROM " . TABLE_FILES_RELATIONS . " WHERE file_id = :id");
				$assignments->bindParam(':id', $this_file_id, PDO::PARAM_INT);
				$assignments->execute(); 
				//echo $assignments->rowCount();
				
				if ($assignments->rowCount() > 0) {
					while ( $assignment_row = $assignments->fetch() ) {
						if (!empty($assignment_row['client_id'])) {
							$file_on_clients[] = $assignment_row['client_id'];
						}
						elseif (!empty($assignment_row['group_id'])) {
							$file_on_groups[] = $assignment_row['group_id'];
						}
					}
				}

				$n = 0;
				foreach ($_POST['file'] as $file) { 
					$n++;
					if(!empty($file['name'])) {
						/**
						* If the uploader is a client, set the "client" var to the current
						* uploader username, since the "client" field is not posted.
						*/


						$this_upload = new PSend_Upload_File();
						/**
						 * Unassigned files are kept as orphans and can be related
						 * to clients or groups later.
						 */
					
						/** Add to the database for each client / group selected */
						$add_arguments = array(
												'file' => $edit_file_info['url'],
												'name' => $file['name'],
												'description' => $file['description'],
												'uploader' => $global_user,
												'uploader_id' => $global_id,
												'expiry_date' => $file['expiry_date']
											);
											
					
						/** Set notifications to YES by default */
						$send_notifications = true;
						if (!empty($file['notify'])) {
							$add_arguments['notify'] = true;
						}else{
							$add_arguments['notify'] = false;
						}
						
						if (!empty($file['number_downloads'])) {
							$add_arguments['number_downloads'] = $file['number_downloads'];
						}else{
							$add_arguments['number_downloads'] = 0;
						}
						if (!empty($file['future_send_date'])) {
							$add_arguments['future_send_date'] = $file['future_send_date'];
						}

						//1 == 1 for all all users added by B)
						if ($current_level != 0 || 1 == 1) {

							if (!empty($file['expires'])) {
								$add_arguments['expires'] = '1';
							}

							if (!empty($file['public'])) {
								$add_arguments['public'] = '1';
							}
							//echo "----------------------".$file['assignments']."--------------------------------------";
							if (!empty($file['assignments']) || !empty($_POST['new_client']) ) {
								
								/**
								 * Remove already assigned clients/groups from the list.
								 * Only adds assignments to the NEWLY selected ones.
								 */
//------------------------------------------------------------

				$nuser_list = $_POST['new_client'];
				if(!empty($file['assignments'])){
					$full_list = $file['assignments'];
				}else{
					$full_list = array();
				}
				if(!empty($nuser_list)) {
					foreach($nuser_list as $nuser) {
						$euser_query = $dbh->prepare("SELECT id FROM `".TABLE_USERS."` WHERE `email` = '".$nuser."'");
						//echo "SELECT id FROM `".TABLE_USERS."` WHERE `email` = '".$nuser."'";exit();
						$euser_query->execute(); 
						$euser = $euser_query->fetch();
						$nuser_id = $euser['id'];
						if( $euser_query->rowCount() == 0 ) {
							//echo "Here";exit();
							$npw = randomPassword();
							$cc_enpass = $hasher->HashPassword($npw);
							$nuser_query = $dbh->prepare("INSERT INTO `".TABLE_USERS."` (`user`, `password`, `name`, `email`, `level`, `timestamp`, `address`, `phone`, `notify`, `contact`, `created_by`, `active`) VALUES ('".$nuser."', '".$cc_enpass."', '', '".$nuser."', '0', CURRENT_TIMESTAMP, NULL, NULL, '0', NULL, NULL, '1')");
							$nuser_query->execute(); 
							$nuser_id = $dbh->lastInsertId();
// --------------------------------- email notification start here!
$to_email_request = $nuser; // note the comma


// Message
$message = '
<html>
<head>
  <title>Invitation to Download</title>
</head>
<body>
  <p>Please find the login details</p>
  <table>
    <tr>
      <td>User Name : </td><td>'.$nuser.'</td>
    </tr>
    <tr>
      <td>Password</td><td>'.$npw.'</td>
    </tr>
	<tr>
      <td>Site :</td><td>'.'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'].'</td>
    </tr>
  </table>
</body>
</html>
';
//--------------------------------------------------------------------------------------------
/**
		 * phpMailer
		 */
		require_once(ROOT_DIR.'/includes/phpmailer/class.phpmailer.php');
		if (!spl_autoload_functions() OR (!in_array('PHPMailerAutoload', spl_autoload_functions()))) {
			require_once(ROOT_DIR.'/includes/phpmailer/PHPMailerAutoload.php');
		}

		$send_mail = new PHPMailer();
		switch (MAIL_SYSTEM) {
			case 'smtp':
					$send_mail->IsSMTP();
					$send_mail->SMTPAuth = true;
					$send_mail->Host = SMTP_HOST;
					$send_mail->Port = SMTP_PORT;
					$send_mail->Username = SMTP_USER;
					$send_mail->Password = SMTP_PASS;
					
					if ( defined('SMTP_AUTH') && SMTP_AUTH != 'none' ) {
						$send_mail->SMTPSecure = SMTP_AUTH;
					}
				break;
			case 'gmail':
					$send_mail->IsSMTP();
					$send_mail->SMTPAuth = true;
					$send_mail->SMTPSecure = "tls";
					$send_mail->Host = 'smtp.gmail.com';
					$send_mail->Port = 587;
					$send_mail->Username = SMTP_USER;
					$send_mail->Password = SMTP_PASS;
				break;
			case 'sendmail':
					$send_mail->IsSendmail();
				break;
		}
		
		$send_mail->CharSet = EMAIL_ENCODING;
//
		$send_mail->Subject = "Invitation to Download";
//
		$send_mail->MsgHTML($message);
		$send_mail->AltBody = __('This email contains HTML formatting and cannot be displayed right now. Please use an HTML compatible reader.','cftp_admin');

		$send_mail->SetFrom(ADMIN_EMAIL_ADDRESS, MAIL_FROM_NAME);
		$send_mail->AddReplyTo(ADMIN_EMAIL_ADDRESS, MAIL_FROM_NAME);
//
		$send_mail->AddAddress($to_email_request);
		
		/**
		 * Check if BCC is enabled and get the list of
		 * addresses to add, based on the email type.
		 */
		if (COPY_MAIL_ON_CLIENT_UPLOADS == '1') {
					$try_bcc = true;
				}
		if ($try_bcc === true) {
			$add_bcc_to = array();
			if (COPY_MAIL_MAIN_USER == '1') {
				$add_bcc_to[] = ADMIN_EMAIL_ADDRESS;
			}
			$more_addresses = COPY_MAIL_ADDRESSES;
			if (!empty($more_addresses)) {
				$more_addresses = explode(',',$more_addresses);
				foreach ($more_addresses as $add_bcc) {
					$add_bcc_to[] = $add_bcc;
				}
			}


			/**
			 * Add the BCCs with the compiled array.
			 * First, clean the array to make sure the admin
			 * address is not written twice.
			 */

			if (!empty($add_bcc_to)) {
				$add_bcc_to = array_unique($add_bcc_to);
				foreach ($add_bcc_to as $set_bcc) {
					$send_mail->AddBCC($set_bcc);
				}
			}
			 
		}


	
		/**
		 * Finally, send the e-mail.
		 */
		if($send_mail->Send()) {
			$cc_status = "<div class=\"alert alert-success cc-success\"><strong>Success!</strong>Your Request has been submitted successfully.</div>";
		}
		else {
			$cc_status = "<div class=\"alert alert-danger cc-failed\"><strong>Oops! </strong>Something went wrong! please try after sometime.</div>";
		}

		
			echo "<script>$(document).ready(function(){ $('#cc-mail-status').modal('toggle');});</script>";

//--------------------------------------------------------------------------------------------
// email notification End here! B)
						}
						array_push($full_list,'c'.$nuser_id);
					}
				}
				$full_assi_user = $full_list;
				$add_arguments['assign_to'] = $full_assi_user;
				$assignations_count	= count($full_assi_user);
//------------------------------------------------------------
                if($get_prev_id != 3){

                  foreach ($file_on_clients as $this_client) { $compare_clients[] = 'c'.$this_client; }
    							foreach ($file_on_groups as $this_group) { $compare_groups[] = 'g'.$this_group; }

                }
                else {
                  $compare_clients[]=array();
                  $compare_groups[]=array();
                }

								if (!empty($compare_clients)) {
									$full_list = array_diff($full_list,$compare_clients);
								}
								if (!empty($compare_groups)) {
									$full_list = array_diff($full_list,$compare_groups);
								}
								$add_arguments['assign_to'] = $full_list;
								$today = date("d-m-Y");
								if($file['expires'] && ($file['expiry_date'] == $today) ){
									$add_arguments['assign_to']= $full_assi_user;
								}

								/**
								 * On cleaning the DB, only remove the clients/groups
								 * That just have been deselected.
								 */
								$clean_who = $full_assi_user;
							}
							else {
								$clean_who = 'All';
								$assignations_count='0';
							}
							
							/** CLEAN deletes the removed users/groups from the assignments table */
							if ($clean_who == 'All') {
								$clean_all_arguments = array(
																'owner_id' => $global_id, /** For the log */
																'file_id' => $this_file_id,
																'file_name' => $file['name']
															);
								$clean_assignments = $this_upload->clean_all_assignments($clean_all_arguments);
							}
							else {						
								$clean_arguments = array (
														'owner_id' => $global_id, /** For the log */
														'file_id' => $this_file_id,
														'file_name' => $file['name'],
														'assign_to' => $clean_who,
														'current_clients' => $file_on_clients,
														'current_groups' => $file_on_groups
													);
								$clean_assignments = $this_upload->clean_assignments($clean_arguments);
							}

							$categories_arguments = array(
														'file_id'		=> $this_file_id,
														'categories'	=> !empty( $file['categories'] ) ? $file['categories'] : '',
													);
							$this_upload->upload_save_categories( $categories_arguments );
						}

						if ($assignations_count == '0'){
							$add_arguments['prev_assign'] ='2';
						}
							$add_arguments['uploader_type'] = 'user';
							$action_log_number = 32;

						/**
						 * 1- Add the file to the database
						 */
						$process_file = $this_upload->upload_add_to_database($add_arguments);
						if($process_file['database'] == true) {
							$add_arguments['new_file_id'] = $process_file['new_file_id'];
							$add_arguments['all_users'] = $users;
							$add_arguments['all_groups'] = $groups;
							$add_arguments['from_id'] = $global_id;
							
							if ($current_level != 0 || 1 == 1) {
								/**
								 * 2- Add the assignments to the database
								 */
								$process_assignment = $this_upload->upload_add_assignment($add_arguments);

								/**
								 * 3- Hide for everyone if checked
								 */
								if (!empty($file['hideall'])) {
									$this_file = new FilesActions();
									$hide_file = $this_file->manage_hide($this_file_id);
								}
								
								
								/**
								 * 4- Add the notifications to the database
								 */
								 
								 
							 $today = date("d-m-Y");

							if (($send_notifications == true && $file['future_send_date'] == $today)
									||($send_notifications == true && $file['expiry_date'] == $today && $file['expires'] == '1' ))
									{

									$process_notifications = $this_upload->upload_add_notifications($add_arguments);
									
								}
								
							}

							$new_log_action = new LogActions();
							$log_action_args = array(
													'action' => $action_log_number,
													'owner_id' => $global_id,
													'owner_user' => $global_user,
													'affected_file' => $process_file['new_file_id'],
													'affected_file_name' => $file['name']
												);
							$new_record_action = $new_log_action->log_action_save($log_action_args);

							$msg = __('The file has been edited successfully.','cftp_admin');
							echo system_message('ok',$msg);
							
							include(ROOT_DIR.'/upload-send-notifications.php');
						}
					}
				}

			}
			/** Validations OK, show the editor */
	?>
          <form action="edit-file.php?file_id=<?php echo filter_var($this_file_id,FILTER_VALIDATE_INT); ?>&page_id=<?php echo $get_prev_id; ?>" method="post" name="edit_file" id="edit_file">
            <div class="container-fluid">
              <?php
						/** Reconstruct the current assignments arrays */
						$file_on_clients = array();
						$file_on_groups = array();
						$assignments = $dbh->prepare("SELECT file_id, client_id, group_id FROM " . TABLE_FILES_RELATIONS . " WHERE file_id = :id");
						$assignments->bindParam(':id', $this_file_id, PDO::PARAM_INT);
						$assignments->execute();
						if ($assignments->rowCount() > 0) {
							while ( $assignment_row = $assignments->fetch() ) {
								if (!empty($assignment_row['client_id'])) {
									$file_on_clients[] = $assignment_row['client_id'];
								}
								elseif (!empty($assignment_row['group_id'])) {
									$file_on_groups[] = $assignment_row['group_id'];
								}
							}
						}

						/** Get the current assigned categories */
						$current_categories = array();
						$current_statemente = $dbh->prepare("SELECT cat_id FROM " . TABLE_CATEGORIES_RELATIONS . " WHERE file_id = :id");
						$current_statemente->bindParam(':id', $this_file_id, PDO::PARAM_INT);
						$current_statemente->execute();
						if ($current_statemente->rowCount() > 0) {
							while ( $assignment_row = $current_statemente->fetch() ) {
								$current_categories[] = $assignment_row['cat_id'];
							}
						}
	
	
						$i = 1;
						$statement = $dbh->prepare("SELECT * FROM " . TABLE_FILES . " WHERE id = :id");
						$statement->bindParam(':id', $this_file_id, PDO::PARAM_INT);
						$statement->execute();
						while( $row = $statement->fetch() ) {
					?>
              <div class="file_editor <?php if ($i%2) { echo 'f_e_odd'; } ?>">
                <div class="row">
                  <div class="col-sm-12">
                    <div class="file_number">
                      <p><span class="glyphicon glyphicon-saved" aria-hidden="true"></span><?php echo html_output($row['url']); ?></p>
                    </div>
                  </div>
                </div>
                <div class="row edit_files">
                  <div class="col-sm-12">
                    <div class="row edit_files_blocks">
                      <div class="col-sm-6 col-xl-3 column_even column">
                        <div class="file_data">
                          <div class="row">
                            <div class="col-sm-12">
                              <h3>
                                <?php _e('File information', 'cftp_admin');?>
                              </h3>
                              <div class="form-group">
                                <label>
                                  <?php _e('Title', 'cftp_admin');?>
                                </label>
                                <input type="text" name="file[<?php echo $i; ?>][name]" value="<?php echo html_output($row['filename']); ?>" class="form-control file_title" placeholder="<?php _e('Enter here the required file title.', 'cftp_admin');?>" />
                              </div>
                              <div class="form-group">
                                <label>
                                  <?php _e('Description', 'cftp_admin');?>
                                </label>
                                <textarea name="file[<?php echo $i; ?>][description]" class="form-control" placeholder="<?php _e('Optionally, enter here a description for the file.', 'cftp_admin');?>"><?php echo (!empty($row['description'])) ? html_output($row['description']) : ''; ?></textarea>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                      <?php
												/** The following options are available to users only */
												// 1 == 1 for all users
												if ($global_level != 0 || 1 == 1) {
											?>
                      <div class="col-sm-6 col-xl-3 column_even column">
                        <div class="file_data">
                          <?php
																/**
																* Only show the EXPIRY options if the current
																* uploader is a system user, and not a client.
																*/
																
																if (!empty($row['future_send_date'])) {
																	$future_send_date = date('d-m-Y', strtotime($row['future_send_date']));
																}
															?>
                          <h3>
                            <?php _e('Expiration date', 'cftp_admin');?>
                          </h3>
                          <div class="form-group">
                            <label for="file[<?php echo $i; ?>][expires_date]">
                              <?php _e('Select a date', 'cftp_admin');?>
                            </label>
                            <div class="input-group date-container">
								<?php
                $date = date('d-m-Y');
                if(($get_prev_id == 6) || ($get_prev_id == 2)) {
                    $expiry_date = date('d-m-Y', strtotime($edit_file_info['expiry_date'])) ;
                  }

								 else if($row['expires']==1) {
									$expiry_date = date('d-m-Y', strtotime($date. ' + 14 days'));
								}
								else {
									$expiry_date ='';
								}
								?>
                              <input type="text" class="date-field form-control datapick-field" readonly id="file[<?php echo $i; ?>][expiry_date]" name="file[<?php echo $i; ?>][expiry_date]" value="<?php echo (!empty($expiry_date)) ? $expiry_date : date('d-m-Y'); ?>" />
                              <div class="input-group-addon"> <i class="glyphicon glyphicon-time"></i> </div>
                            </div>
                          </div>
                          <div class="checkbox">
                            <label for="exp_checkbox">
                              <input type="checkbox" id="exp_checkbox" name="file[<?php echo $i; ?>][expires]" value="1" <?php if ($row['expires']) { ?>checked="checked"<?php } ?> />
                              <?php _e('File expires', 'cftp_admin');?>
                            </label>
                          </div>
                          <div class="checkbox">
                            <label for="notify_checkbox">
                              <input type="checkbox" id="notify_checkbox" name="file[<?php echo $i; ?>][notify]" value="1" <?php if ($row['notify']) { ?>checked="checked"<?php } ?> />
                              <?php _e('Don\'t Notify Me', 'cftp_admin');?>
                            </label>
                          </div>
                          <div class="divider"></div>
			<?php if($current_level != 0){ ?>
                          <h3> 
                            <?php _e('Public downloading', 'cftp_admin');?>
                          </h3>
                          <div class="checkbox">
                            <label for="pub_checkbox">
                              <input type="checkbox" id="pub_checkbox" name="file[<?php echo $i; ?>][public]" value="1" <?php if ($row['public_allow']) { ?>checked="checked"<?php } ?> />
                              <?php _e('Allow public downloading of this file.', 'cftp_admin');?>
                            </label>
                          </div>
			<?php } ?>
                          <div class="form-group">
                            <label>
                              <?php _e('Number of Downloads Allowed', 'cftp_admin');?>
                            </label>
							<?php 
								if($row['number_downloads']>0)
								{  
									$number_downloads = $row['number_downloads'];
								}
								else 
								{
									$number_downloads = 0;
								/*	if(DOWNLOAD_MAX_TRIES>0)
									{
										$number_downloads = DOWNLOAD_MAX_TRIES;
									}
									else
									{
										$number_downloads = '';
									}
								*/	
								}
								?>
							
                            <input type="text" name="file[<?php echo $i; ?>][number_downloads]" value="<?php echo html_output($number_downloads); ?>" size="1" class="form-control1 file_title" placeholder="<?php _e('Enter number of downloads.', 'cftp_admin');?>" />
                          </div>
                        </div>
                      </div>
                      <div class="col-sm-6 col-xl-3 assigns column">
                        <div class="file_data">
                          <?php
																/**
																* Only show the CLIENTS select field if the current
																* uploader is a system user, and not a client.
																*/
															?>
                          <h3>
							<?php 
							if($row['public_allow']){ 
							?>
								<script> 
								$(document).ready(function() {
									$('.chosen-select_pub').prop('disabled', true).val('').trigger('chosen:updated').chosen();
								});
								</script>
							<?php 
							}
							?>
						  
						  </script>
                            <?php _e('Send To', 'cftp_admin');?>
                          </h3>
                          <select multiple="multiple" name="new_client[]" class="form-control new_client" data-placeholder="<?php _e('Select one or more options. Type to search.', 'cftp_admin');?>" style="display:none">
</select>
                          <label>
                            <?php _e('Assign this file to', 'cftp_admin');?>
                            :</label>
                          <select multiple="multiple" name="file[<?php echo $i; ?>][assignments][]" class="form-control chosen-select chosen-select_pub" data-placeholder="<?php _e('Select one or more options. Type to search.', 'cftp_admin');?>">
                            <optgroup label="<?php _e('Clients', 'cftp_admin');?>">
                            <?php
																		/**
																		 * The clients list is generated early on the file so the
																		 * array doesn't need to be made once on every file.
																		 */
																		foreach($clients as $client => $client_name) {
																		?>
                            <option value="<?php echo 'c'.$client; ?>"<?php if (in_array($client,$file_on_clients)) { echo ' selected="selected"'; } ?>> <?php echo $client_name; ?> </option>
                            <?php
																		}
																	?>
                            </optgroup>
                            <optgroup label="<?php _e('Groups', 'cftp_admin');?>">
                            <?php
																		/**
																		 * The groups list is generated early on the file so the
																		 * array doesn't need to be made once on every file.
																		 */
																		foreach($groups as $group => $group_name) {
																		?>
                            <option value="<?php echo 'g'.$group; ?>"<?php if (in_array($group,$file_on_groups)) { echo ' selected="selected"'; } ?>> <?php echo $group_name; ?> </option>
                            <?php
																		}
																	?>
                            </optgroup>
                          </select>
                          <div class="list_mass_members"> <a href="#" class="btn btn-xs btn-primary add-all" data-type="assigns">
                            <?php _e('Add all','cftp_admin'); ?>
                            </a> <a href="#" class="btn btn-xs btn-primary remove-all" data-type="assigns">
                            <?php _e('Remove all','cftp_admin'); ?>
                            </a> </div>
                          <div class="divider"></div>
                        <?php if ($current_level != 0) { ?>			      
                          <div class="checkbox">
                            <label for="hid_checkbox">
                              <input type="checkbox" id="hid_checkbox" name="file[<?php echo $i; ?>][hidden]" value="1" />
                              <?php _e('Upload hidden (will not send notifications)', 'cftp_admin');?>
                            </label>
                          </div>
			<?php } ?>
                        <?php if ($current_level != 0) { if ($get_prev_id != 4) { ?>
                          <div class="checkbox">
                            <label for="hid_existing_checkbox">
                              <input type="checkbox" id="hid_existing_checkbox" name="file[<?php echo $i; ?>][hideall]" value="1" />
                              <?php _e('Hide from every already assigned clients and groups.', 'cftp_admin');?>
                            </label>
                          </div>
                       <?php } } ?>
                        </div>
                      </div>
                      <div class="col-sm-6 col-xl-3 categories column">
                        <div class="file_data">
                          <h3>
                            <?php _e('Categories', 'cftp_admin');?>
                          </h3>
                          <label>
                            <?php _e('Add to', 'cftp_admin');?>
                            :</label>
                          <select multiple="multiple" name="file[<?php echo $i; ?>][categories][]" class="form-control chosen-select" data-placeholder="<?php _e('Select one or more options. Type to search.', 'cftp_admin');?>">
                            <?php
																	/**
																	 * The categories list is generated early on the file so the
																	 * array doesn't need to be made once on every file.
																	 */
																	echo generate_categories_options( $get_categories['arranged'], 0, $current_categories );
																?>
                          </select>
                          <div class="list_mass_members"> <a href="#" class="btn btn-xs btn-primary add-all" data-type="categories">
                            <?php _e('Add all','cftp_admin'); ?>
                            </a> <a href="#" class="btn btn-xs btn-primary remove-all" data-type="categories">
                            <?php _e('Remove all','cftp_admin'); ?>
                            </a> </div>
                        </div>
                        <h3>
                          <?php _e('Future Send Date', 'cftp_admin');?>
                        </h3>
                        <div class="form-group">
                          <label for="file[<?php echo $i; ?>][future_send_date]">
                            <?php _e('Select a date', 'cftp_admin');?>
                          </label>						  
                          <div class="input-group date-container">
                            <input type="text" class="date-field form-control datapick-field" readonly id="file[<?php echo $i; ?>][future_send_date]" name="file[<?php echo $i; ?>][future_send_date]" value="<?php echo ($get_prev_id !=3) ?  date('d-m-Y') : $future_send_date; ?>" />
                            <!-- <input type="text" class="date-field form-control datapick-field" readonly id="file[<?php echo $i; ?>][future_send_date]" name="file[<?php echo $i; ?>][future_send_date]" value="<?php echo (!empty($future_send_date)) ? $future_send_date : date('d-m-Y'); ?>" /> -->
                            <div class="input-group-addon"> <i class="glyphicon glyphicon-time"></i> </div>
                          </div>
                        </div>
                      </div>
                      <?php
												} /** Close $current_level check */
											?>
                    </div>
                  </div>
                </div>
              </div>
              <?php
						}
					?>
              <div class="after_form_buttons">
              <a class="btn btn-default btn-wide" href="<?php echo $get_prev_url; ?>" >Back</a>
                <button type="submit" name="submit" class="btn btn-wide btn-primary">
                <?php _e('Save','cftp_admin'); ?>
                </button>
              </div>
            </div>
          </form>
          <?php
		}
	?>
        </div>
      </div>
    </div>
  </div>
</div>
<script type="text/javascript">
function window_back() {
			//window.history.back(-1);
}
	$(document).ready(function() {
		
		$('.chosen-select').chosen({
			no_results_text	: "<?php _e('Invite User :','cftp_admin'); ?>",
			width			: "98%",
			search_contains	: true,
		});
		// Start by B)
			$(".no-results").click(function(e) {
    			console.log($('span',this).text());
			});
			$(document).on('click', ".no-results", function() {
    			var cc_email = $('span',this).text(); 
				$(".new_client").append('<option val="'+cc_email+'" selected="selected">'+cc_email+'</option>'); 
				$(this).parent().parent().siblings('.chosen-choices').prepend('<li class="search-choice"><span>'+cc_email+'</span><a style="text-decoration:none" class="cc-choice-close">&nbsp;&nbsp;x</a></li>');
			});
			$(document).on('click', ".cc-choice-close", function() {
				var cc_remove_op = $(this).siblings('span').text();
				jQuery(".new_client option:contains('"+cc_remove_op+"')").remove();
				$(this).parent().remove();
			});
		// End by B)
		$('.date-container .date-field').datepicker({
			format		: 'dd-mm-yyyy',
			autoclose	: true,
			todayHighlight	: true,
                        startDate       : new Date()
		});

		$('.add-all').click(function(){
			var type = $(this).data('type');
			var selector = $(this).closest('.' + type).find('select');
			$(selector).find('option').each(function(){
				$(this).prop('selected', true);
			});
			$('select').trigger('chosen:updated');
			return false;
		});

		$('.remove-all').click(function(){
			var type = $(this).data('type');
			var selector = $(this).closest('.' + type).find('select');
			$(selector).find('option').each(function(){
				$(this).prop('selected', false);
			});
			$('select').trigger('chosen:updated');
			return false;
		});

		$("form").submit(function() {
			clean_form(this);

			$(this).find('input[name$="[name]"]').each(function() {	
				is_complete($(this)[0],'<?php echo $validation_no_title; ?>');
			});

			// show the errors or continue if everything is ok
			if (show_form_errors() == false) { return false; }

		});
	});
</script>
<!-- Modal -->
<div id="cc-mail-status" class="modal fade" role="dialog">
  <div class="modal-dialog">

    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">MicroHealth Send</h4>
      </div>
      <div class="modal-body">
		<?php echo isset($cc_status)? $cc_status : ''; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
      </div>
    </div>

  </div>
</div><script language="javascript">$(document).ready(function() {     $("[type='text']").attr('id',function(i){return 'chk' + i;});	 });$(document).ready(function() {     $(".chosen-choices").attr('id',function(i){return 'chosen-' + i;});	 });$(document).ready(function() {     $(".chosen-select").attr('id',function(i){return 'chslt-' + i;});	 });</script> <script language="javascript">document.getElementById('pub_checkbox').onchange = function() { $('#chslt-0').prop('disabled', true).trigger("chosen:updated");if ($("#pub_checkbox").is(":checked")){		$('#chslt-0').prop('disabled', true).val('').trigger('chosen:updated');	}else if (!$("#pub_checkbox").is(":checked")) {		$('#chslt-0').prop('disabled', false).trigger("chosen:updated");	}	};</script> 
<?php include('footer.php'); ?>

