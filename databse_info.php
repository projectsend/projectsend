<?php
/**
 * Show the form to add a new system user.
 *
 * @package		ProjectSend
 * @subpackage	Users
 *
 */
$allowed_levels = array(9);
require_once('sys.includes.php');

if(!check_for_admin()) {
    return;
}
$active_nav = 'users';
$page_title = __('Update DB info','cftp_admin');
include('header.php');

/**
 * Set checkboxes as 1 to defaul them to checked when first entering
 * the form
 */
$add_user_data_active = 1;

/* Getting contents from db info stored file */
$file_path = 'db_details.txt';
$contents = file_get_contents($file_path);
$contents = explode(' ',$contents);
$db_host = $contents[0];
$db_name = $contents[1];
$db_user_name = $contents[2];
$db_password = $contents[3];
	/* AES Decryption started by RJ-23-Nov-2016 */
	$blockSize = 256;
	$inputKey = "project send encryption";
	$file_path = 'db_details.txt';
	$aes = new AES($db_password, $inputKey, $blockSize);
	$decPassword = $aes->decrypt();
	/* AES Decryption ended by RJ-23-Nov-2016 */
if ($_POST) {
	/**
	 * Clean the posted form values to be used on the user actions,
	 * and again on the form if validation failed.
	 */
	$db_host_name = encode_html($_POST['db_host']);
	$db_name = encode_html($_POST['db_name']);
	$db_user_name = encode_html($_POST['db_user_name']);
	$db_password = encode_html($_POST['db_password']);
	if(!empty($db_password)){
		$aes = new AES($db_password, $inputKey, $blockSize);
		$enPassword = $aes->encrypt();
	}
	if(!empty($db_host_name) and !empty($db_name) and !empty($db_user_name) and !empty($enPassword)){
		$final_text = $db_host_name." ".$db_name." ".$db_user_name." ".$enPassword;
	}
	if(!empty($final_text)){
		// Write the contents back to the file
		file_put_contents($file_path, $final_text);
	}	
}
?>

<div id="main">
	<h2><?php echo $page_title; ?></h2>
	
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
						if (isset($new_response)) {
							/**
							 * Get the process state and show the corresponding ok or error message.
							 */
							switch ($new_response['query']) {
								case 1:
									$msg = __('User added correctly.','cftp_admin');
									echo system_message('ok',$msg);
			
									/** Record the action log */
									$new_log_action = new LogActions();
									$log_action_args = array(
															'action' => 2,
															'owner_id' => $global_id,
															'affected_account' => $new_response['new_id'],
															'affected_account_name' => $add_user_data_name
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
							 * If not $new_response is set, it means we are just entering for the first time.
							 * Include the form.
							 */
							$user_form_type = 'new_user';
							include('db-info-update-form.php');
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
