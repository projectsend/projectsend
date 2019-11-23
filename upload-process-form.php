<?php

/**

 * Uploading files, step 2

 *

 * This file handles all the uploaded files, whether you are

 * coming from the "Upload from computer" or "Find orphan files"

 * pages. The only difference is from which POST array it takes

 * the information to list the available files to process.

 *

 * It can display up tp 3 tables:

 * One that will list all the files that were brought in from

 * the first step. One with the confirmed uploaded and assigned

 * files, and a possible third one with the ones that failed.

 *

 * @package ProjectSend

 * @subpackage Upload

 */

$load_scripts	= array(

						'datepicker',

						'footable',

						'chosen',

					);



$allowed_levels = array(9,8,7,0);

require_once('sys.includes.php');
//require_once('future-send.php');


$active_nav = 'files';



$page_title = __('Sent Items', 'cftp_admin');

include('header.php');



define('CAN_INCLUDE_FILES', true);

?>

<div id="main">
	<div id="content">

<!-- Added by B) -------------------->

		<div class="container-fluid">
			<div class="row">
				<div class="col-md-12">
					<h2><?php echo $page_title; ?></h2>
<?php

/**

 * Get the user level to determine if the uploader is a

 * system user or a client.

 */

$current_level = get_current_user_level();



$work_folder = UPLOADED_FILES_FOLDER;



/** Coming from the web uploader */

if(isset($_POST['finished_files'])) {

	$uploaded_files = array_filter($_POST['finished_files']);

}

/** Coming from upload by FTP */

if(isset($_POST['add'])) {

	$uploaded_files = $_POST['add'];

}



/**

 * A hidden field sends the list of failed files as a string,

 * where each filename is separated by a comma.

 * Here we change it into an array so we can list the files

 * on a separate table.

 */

if(isset($_POST['upload_failed'])) {

	$upload_failed_hidden_post = array_filter(explode(',',$_POST['upload_failed']));

}

/**

 * Files that failed are removed from the uploaded files list.

 */

if(isset($upload_failed_hidden_post) && count($upload_failed_hidden_post) > 0) {

	foreach ($upload_failed_hidden_post as $failed) {

		$delete_key = array_search($failed, $uploaded_files);

		unset($uploaded_files[$delete_key]);

	}

}



/** Define the arrays */

$upload_failed = array();

$move_failed = array();



/**

 * $empty_fields counts the amount of "name" fields that

 * were not completed.

 */

$empty_fields = 0;



/** Fill the users array that will be used on the notifications process */

$users = array();

$statement = $dbh->prepare("SELECT id, name, email, level FROM " . TABLE_USERS . " WHERE active = 1 ORDER BY name ASC");

$statement->execute();

$statement->setFetchMode(PDO::FETCH_ASSOC);

while( $row = $statement->fetch() ) {

	$users[$row["id"]] = $row["name"];

	//if ($row["level"] == '0') {

		//$clients[$row["id"]] = $row["name"];

	//}

		$clients[$row["id"]] = $row["name"]." : ".$row["email"];

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

 * Make an array of file urls that are on the DB already.

 */
$urls_db_files=array();
$statement = $dbh->prepare("SELECT DISTINCT url FROM " . TABLE_FILES);

$statement->execute();

$statement->setFetchMode(PDO::FETCH_ASSOC);

while( $row = $statement->fetch() ) {

	$urls_db_files[] = $row["url"];

}



/**

 * A posted form will include information of the uploaded files

 * (name, description and client).

 */
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
				/*echo '<pre>';
				print_r($pass);
				echo'</pre>';*/

	if (isset($_POST['submit'])) {
		/**
		 * Get the ID of the current client that is uploading files.

		 */

		if ($current_level == 0) {

			$client_my_info = get_client_by_username($global_user);

			$client_my_id = $client_my_info["id"];

		}



		$n = 0;



		foreach ($_POST['file'] as $file) {
			$n++;



			if(!empty($file['name'])) {

				/**

				* If the uploader is a client, set the "client" var to the current

				* uploader username, since the "client" field is not posted.

				*/

				/*if ($current_level == 0) {

					$file['assignments'] = 'c'.$global_user;

				}*/



				$this_upload = new PSend_Upload_File();

				if (!in_array($file['file'],$urls_db_files)) {

					$file['file'] = $this_upload->safe_rename($file['file']);

				}

				$location = $work_folder.'/'.$file['file'];



				if(file_exists($location)) {

					/**

					 * If the file isn't already on the database, rename/chmod.

					 */

					if (!in_array($file['file'],$urls_db_files)) {

						$move_arguments = array(

												'uploaded_name' => $location,

												'filename' => $file['file']

											);

						$new_filename = $this_upload->upload_move($move_arguments);

					}

					else {

						$new_filename = $file['original'];

					}

					if (!empty($new_filename)) {

						$delete_key = array_search($file['original'], $uploaded_files);

						unset($uploaded_files[$delete_key]);



						/**

						 * Unassigned files are kept as orphans and can be related

						 * to clients or groups later.

						 */



						/** Add to the database for each client / group selected */

						$add_arguments = array(

												'file' => $new_filename,

												'name' => $file['name'],

												'requestType' => $_POST['request_type'],
												'description' => $file['description'],

												'uploader' => $global_user,

												'uploader_id' => $global_id

											);



						/** Set notifications to YES by default */

						$send_notifications = true;

						if (!empty($file['notify'])) {

							$add_arguments['notify'] = true;

						}else{

							$add_arguments['notify'] = false;

						}



						if (!empty($file['hidden'])) {

							$add_arguments['hidden'] = $file['hidden'];

							$send_notifications = false;

						}

						if (!empty($file['number_downloads'])) {

							$add_arguments['number_downloads'] = $file['number_downloads'];

						}else{

							$add_arguments['number_downloads'] = 0;

						}

						if (!empty($file['future_send_date'])) {

							$add_arguments['future_send_date'] = $file['future_send_date'];

						}



						if (!empty($file['assignments']) || !empty($_POST['new_client']) ) {

									//echo "test";	exit();

//------------------------------------------------------------


				if(isset($_POST['new_client'])){
					$nuser_list = $_POST['new_client'];
				}



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

							$nuser_query = $dbh->prepare("INSERT INTO `".TABLE_USERS."` (`user`, `password`, `name`, `email`, `level`, `timestamp`, `address`, `phone`, `notify`, `contact`, `created_by`, `active`) VALUES ('".$nuser."', '".$cc_enpass."', '', '".$nuser."', '0', CURRENT_TIMESTAMP, NULL, NULL, '1', NULL, NULL, '1')");

							$nuser_query->execute();

							$nuser_id = $dbh->lastInsertId();

// --------------------------------- email notification start here! B)


							$e_notify = new PSend_Email();
							$e_arg = array(
										'type'		=> 'invite_user_to_download_file',
										'address'	=> $nuser,
										'username'	=> $nuser,
										'password'	=> $npw
									);

							$notify_send = $e_notify->psend_send_email($e_arg);
							$new_log_action = new LogActions();
							$log_action_args = array(
													'action' => 3,
													'owner_id' => $global_id,
													'affected_account' =>$nuser_id,
													'affected_account_name' => $nuser
												);
							$new_record_action = $new_log_action->log_action_save($log_action_args);


}



						array_push($full_list,'c'.$nuser_id);

					}

				}

				$full_assi_user = $full_list;
				$add_arguments['assign_to'] = $full_assi_user;
				$assignations_count	= count($full_assi_user);
				$newassigns=1;
						}

						else {

							$assignations_count	= '0';

						}

						if ($assignations_count == '0'){

							$add_arguments['prev_assign'] ='2';
						}

						/** Uploader is a client */

						if ($current_level == 0) {

							$add_arguments['assign_to'] = $file['assignments'];

							$add_arguments['hidden'] = '0';

							$add_arguments['uploader_type'] = 'client';

							if (!empty($file['expires'])) {

								$add_arguments['expires'] = '1';

								$add_arguments['expiry_date'] = $file['expiry_date'];

							}

							$add_arguments['public'] = '0';

						}

						else {

							$add_arguments['uploader_type'] = 'user';

							if (!empty($file['expires'])) {

								$add_arguments['expires'] = '1';

								$add_arguments['expiry_date'] = $file['expiry_date'];

							}

							if (!empty($file['public'])) {

								$add_arguments['public'] = '1';

							}

						}



						if (!in_array($new_filename,$urls_db_files)) {

							$add_arguments['add_to_db'] = true;

						}



						/**

						 * 1- Add the file to the database

						 */

						$process_file = $this_upload->upload_add_to_database($add_arguments);

						if($process_file['database'] == true) {

							$add_arguments['new_file_id']	= $process_file['new_file_id'];

							$add_arguments['all_users']		= $users;

							$add_arguments['all_groups']	= $groups;

							$add_arguments['from_id'] = CURRENT_USER_ID;

							/**

							 * 2- Add the assignments to the database

							 */

							$process_assignment = $this_upload->upload_add_assignment($add_arguments);



							/**

							 * 3- Add the assignments to the database

							 */

							$categories_arguments = array(

										'file_id'		=> $process_file['new_file_id'],
										'categories'	=> !empty( $file['categories'] ) ? $file['categories'] : '',

													);

							$this_upload->upload_save_categories( $categories_arguments );



							/**

							 * 4- Add the notifications to the database

							 */
							$today = date("d-m-Y");
							if ($send_notifications == true && $file['future_send_date'] == $today)  {
								$process_notifications = $this_upload->upload_add_notifications($add_arguments);
							}

							/**

							 * 5- Mark is as correctly uploaded / assigned

							 */

							$upload_finish[$n] = array(

													'file_id'		=> $add_arguments['new_file_id'],

													'file'			=> $file['file'],

													'name'			=> htmlspecialchars($file['name']),

													'description'	=> htmlspecialchars($file['description']),

													'new_file_id'	=> $process_file['new_file_id'],

													'assignations'	=> $assignations_count,

													'public'		=> !empty( $add_arguments['public'] ) ? $add_arguments['public'] : 0,

													'public_token'	=> !empty( $process_file['public_token'] ) ? $process_file['public_token'] : null,

												);

							if (!empty($file['hidden'])) {

								$upload_finish[$n]['hidden'] = $file['hidden'];

							}

						}

					}

				}

			}

			else {

				$empty_fields++;

			}
		}
	}
				/*echo '<pre>';
				print_r($pass);
				echo'</pre>';*/


	/**

	 * Generate the table of files that were assigned to a client

	 * on this last POST. These files appear on this table only once,

	 * so if there is another submition of the form, only the new

	 * assigned files will be displayed.

	 */
	if(!empty($upload_finish)) {
?>
		<h3>
		  <?php _e('Files uploaded correctly','cftp_admin'); ?>
		</h3>
<table id="uploaded_files_tbl" class="footable" data-page-size="<?php echo FOOTABLE_PAGING_NUMBER; ?>">
  <thead>
    <tr>
      <th data-sort-initial="true"><?php _e('Title','cftp_admin'); ?></th>
      <th data-hide="phone"><?php _e('Description','cftp_admin'); ?></th>
      <th data-hide="phone"><?php _e('File Name','cftp_admin'); ?></th>
      <?php

						if ($current_level != 0) {

					?>
      <th data-hide="phone"><?php _e("Status",'cftp_admin'); ?></th>
      <th data-hide="phone"><?php _e('Assignations','cftp_admin'); ?></th>
      <th data-hide="phone"><?php _e('Public','cftp_admin'); ?></th>
      <?php

						}

					?>
      <th data-hide="phone" data-sort-ignore="true"><?php _e("Actions",'cftp_admin'); ?></th>
    </tr>
  </thead>
  <tbody>
    <?php

				foreach($upload_finish as $uploaded) {

			?>
    <tr>
      <td><?php echo html_output($uploaded['name']); ?></td>
      <td><?php echo html_output($uploaded['description']); ?></td>
      <td><?php echo html_output($uploaded['file']); ?></td>
      <?php

							if ($current_level != 0) {

						?>
      <td class="<?php echo (!empty($uploaded['hidden'])) ? 'file_status_hidden' : 'file_status_visible'; ?>"><?php

										$status_hidden	= __('Hidden','cftp_admin');

										$status_visible	= __('Visible','cftp_admin');

										$class			= (!empty($uploaded['hidden'])) ? 'danger' : 'success';

									?>
        <span class="label label-<?php echo $class; ?>"> <?php echo ( !empty( $hidden ) && $hidden == 1) ? $status_hidden : $status_visible; ?> </span></td>
      <td><?php $class = ($uploaded['assignations'] > 0) ? 'success' : 'danger'; ?>
        <span class="label label-<?php echo $class; ?>"> <?php echo $uploaded['assignations']; ?> </span></td>
      <td class="col_visibility"><?php

										if ($uploaded['public'] == '1') {

									?>
        <a href="javascript:void(0);" class="btn btn-primary btn-sm public_link" data-id="<?php echo $uploaded['file_id']; ?>" data-token="<?php echo html_output($uploaded['public_token']); ?>" data-placement="top" data-toggle="popover" data-original-title="<?php _e('Public URL','cftp_admin'); ?>">
        <?php

										}

										else {

									?>
        <a href="javascript:void(0);" class="btn btn-default btn-sm disabled" rel="" title="">
        <?php

										}

												$status_public	= __('Public','cftp_admin');

												$status_private	= __('Private','cftp_admin');

												echo ($uploaded['public'] == 1) ? $status_public : $status_private;

									?>
        </a></td>
      <?php

							}

						?>
      <td><a href="edit-file.php?file_id=<?php echo html_output($uploaded['new_file_id']); ?>&page_id=8" class="btn-primary btn btn-sm">
        <?php _e('Edit file','cftp_admin'); ?></td>
    </tr>
    <?php

				}

			?>
  </tbody>
</table>
<?php

	}



	/**

	 * Generate the table of files ready to be assigned to a client.

	 */

	if(!empty($uploaded_files)) {

?>
<div id="readydiv">
<h3>
  <?php _e('Files ready to upload','cftp_admin'); ?>
</h3>
<p>
  <?php _e('Please complete the following information to finish the uploading process. Remember that "Title" is a required field.','cftp_admin'); ?>
</p>
<?php

			//  if ($current_level != 0) {

		?>
<div class="message message_info"><strong>
  <?php _e('Note','cftp_admin'); ?>
  </strong>:
  <?php _e('Files that are not assigned or made public will be found under Draft. These files will be retained, and you may add them to clients or groups later, or make them public.','cftp_admin'); ?>
</div>
<?php

		//	}



		/**

		 * First, do a server side validation for files that were submited

		 * via the form, but the name field was left empty.

		 */

		if(!empty($empty_fields)) {

			$msg = 'Name and client are required fields for all uploaded files.';

			echo system_message('error',$msg);

		}

?>
<script type="text/javascript">

			$(document).ready(function() {

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


		<form action="upload-process-form.php" name="save_files" id="save_files" method="post">
				<input type="hidden" name="request_type" value="0">
				<select multiple="multiple" name="new_client[]" class="form-control new_client" data-placeholder="<?php _e('Select one or more options. Type to search.', 'cftp_admin');?>" style="display:none">

			<?php

				foreach($uploaded_files as $add_uploaded_field)

				{

					echo '<input type="hidden" name="finished_files[]" value="'.$add_uploaded_field.'" />';

				}

			?>


    <div id="readyfiles" class="container-fluid">
    <?php

					$i = 1;

					foreach ($uploaded_files as $file) {

						clearstatcache();

						$this_upload = new PSend_Upload_File();

						$file_original = $file;

						$location = $work_folder.'/'.$file;



						/**

						 * Check that the file is indeed present on the folder.

						 * If not, it is added to the failed files array.

						 */

						if(file_exists($location)) {

							/** Generate a safe filename */
							/**
							 * Remove the extension from the file name and replace every
							 * underscore with a space to generate a valid upload name.
							 */

							$filename_no_ext = substr($file, 0, strrpos($file, '.'));

							$file_title = str_replace('_',' ',$filename_no_ext);

							if ($this_upload->is_filetype_allowed($file)) {

								if (in_array($file,$urls_db_files)) {

									$statement = $dbh->prepare("SELECT filename, description FROM " . TABLE_FILES . " WHERE url = :url");

									$statement->bindParam(':url', $file);

									$statement->execute();



									while( $row = $statement->fetch() ) {

										$file_title = $row["filename"];

										$description = $row["description"];

									}

								}

					?>
    <div class="file_editor <?php if ($i%2) { echo 'f_e_odd'; } ?>">
		<div class="row">
			<div class="col-sm-12">
				<div class="file_number">
				<p>
				<span class="glyphicon glyphicon-saved" aria-hidden="true"></span><?php echo html_output($file); ?>
				</p>
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
									<input type="hidden" name="file[<?php echo $i; ?>][original]" value="<?php echo html_output($file_original); ?>" />
									<input type="hidden" name="file[<?php echo $i; ?>][file]" value="<?php echo html_output($file); ?>" />

				<div class="form-group">
							<label>
							<?php _e('Title', 'cftp_admin');?>
							</label>
							<input type="text" name="file[<?php echo $i; ?>][name]" value="<?php echo html_output($file_title); ?>" class="form-control file_title" placeholder="<?php _e('Enter here the required file title.', 'cftp_admin');?>" />
				</div>

				<div class="form-group">
					<label>
					<?php _e('Description', 'cftp_admin');?>
					</label>
					<textarea name="file[<?php echo $i; ?>][description]" class="form-control" placeholder="<?php _e('Optionally, enter here a description for the file.', 'cftp_admin');?>"><?php echo (isset($description)) ? html_output($description) : ''; ?></textarea>
				</div>
			</div>
		</div>
	</div>
</div>
												<?php

													/** The following options are available to users only */

													// 1 == 1 for all user

													if ($global_level != 0 || 1 == 1) {

												?>


			<div class="col-sm-6 col-xl-3 column_even column">
				<div class="file_data">
					<?php

						/**

						* Only show the expiration options if the current

						* uploader is a system user, and not a client.

						*/

					?>
					<h3>
					<?php _e('Expiration date', 'cftp_admin');?>
					</h3>


	<div class="form-group">
		<label for="file[<?php echo $i; ?>][expires_date]">
			<?php _e('Select a date', 'cftp_admin');?>
		</label>
						<?php
						if($row['expiry_set']>0)
						{
							$expiry_date = $expiry_date;
						}
						else
						{

							if(isset($expiry_date) && empty($expiry_date) && $expiry_date=='')
							{
								$expiry_date = date('d-m-Y');
							}
							if(isset($expiry_date) )
							{
								$date = strtotime(EXPIRY_MAX_DAYS."day", strtotime("$expiry_date"));
								$expiry_date = date("d-m-Y", $date);
							}

						}
						?>

		<div class="input-group ex_date">

			<input  type="text" class="date-field exPdate form-control datapick-field" readonly id="file[<?php echo $i; ?>][expiry_date]" name="file[<?php echo $i; ?>][expiry_date]" value="<?php echo (!empty($expiry_date)) ? $expiry_date : date('d-m-Y',strtotime("+14 days")); ?>" / >

				<div class="input-group-addon">

					<i class="glyphicon glyphicon-time"></i>

				</div>

		</div>

    </div>

    <div class="checkbox">
		<label for="exp_checkbox_<?php echo $i; ?>">
							<?php
							if($row['expiry_set']>0)
							{
								$checked = 'checked';
							}
							else
							{
								$checked = '';
								if(EXPIRY_MAX_DAYS>0)
								{
									$checked = 'checked';
								}
								else
								{
									$checked = '';
								}
							}
							?>
			<input type="checkbox" class="expires" name="file[<?php echo $i; ?>][expires]" id="exp_checkbox_<?php echo $i; ?>" value="1" <?php echo $checked; ?>  />
			<?php _e('File expires', 'cftp_admin');?>
		</label>
    </div>

    <div class="checkbox">
		<label for="notify_checkbox">
			<input type="checkbox" id="notify_checkbox" name="file[<?php echo $i; ?>][notify]" value="1" <?php if ($row['notify']) { ?>checked="checked"<?php } ?> />
				<?php _e('Don\'t Notify Me', 'cftp_admin');?>
		</label>
    </div>

    <div class="divider">
    </div>
		<?php if ($current_level != 0) { ?>
			<h3>
				<?php _e('Public downloading', 'cftp_admin');?>
			</h3>
    <div class="checkbox">
		<label for="pub_checkbox_<?php echo $i; ?>">
			<input type="checkbox" class="pub_checkbox_status" id="pub_checkbox_<?php echo $i; ?>" name="file[<?php echo $i; ?>][public]" value="1" />
			<?php _e('Allow public downloading of this file.', 'cftp_admin');?>
		</label>
    </div>
	<?php } ?>

    <div class="form-group">

				<?php
				if($row['number_downloads']>0)
				{
					$number_downloads = $row['number_downloads'];
				}
				else
				{
					$number_downloads='';
					if(DOWNLOAD_MAX_TRIES>0)
					{
						$number_downloads = DOWNLOAD_MAX_TRIES;
					}
					else
					{
						$number_downloads = '';
					}
				}
				?>


			<label>
				<?php _e('Number of Downloads Allowed', 'cftp_admin');?>
			</label>


			<input type="text" name="file[<?php echo $i; ?>][number_downloads]" value="<?php echo $number_downloads; ?>" size="1" class="form-control1 file_title" placeholder="<?php _e('Enter number of downloads.', 'cftp_admin');?>" />


    </div>
		</div>
			</div>

	<div class="col-sm-6 col-xl-3 assigns column">

		<div class="file_data" id="rn_assign" >
				<?php

																	/**

																	* Only show the CLIENTS select field if the current

																	* uploader is a system user, and not a client.

																	*/

				?>
				<h3>
					<?php _e('Send To', 'cftp_admin');?>
				</h3>

				<label>
					<?php _e('Assign this file to', 'cftp_admin');?>
					:
				</label>



    <select multiple="multiple" name="file[<?php echo $i; ?>][assignments][]" class="form-control chosen-select assignto chosen-select_pub" data-placeholder="<?php _e('Select one or more options. Type to search.', 'cftp_admin');?>">
    <optgroup label="<?php _e('Clients', 'cftp_admin');?>">


    <?php

	/**

    * The clients list is generated early on the file so the

	* array doesn't need to be made once on every file.

	*/

		foreach($clients as $client => $client_name) {


	?>


    <option value="<?php echo html_output('c'.$client); ?>">
	<?php echo html_output($client_name); ?>
	</option>
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
    <option value="<?php echo html_output('g'.$group); ?>"><?php echo html_output($group_name); ?></option>
    <?php

	}

	?>
    </optgroup>
  </select>


					<div class="list_mass_members"> <a href="#" class="btn btn-xs btn-primary add-all" data-type="assigns">
						<?php _e('Add all','cftp_admin'); ?>
						</a> <a href="#" class="btn btn-xs btn-primary remove-all" data-type="assigns">
						<?php _e('Remove all','cftp_admin'); ?>
						</a> <a href="#" class="btn btn-xs btn-danger copy-all" data-type="assigns">
						<?php _e('Copy selections to other files','cftp_admin'); ?>
						</a>
					</div>
					<div class="divider"></div>

        <?php if ($current_level != 0) { ?>
				  <div class="checkbox">
					<label for="hid_checkbox_<?php echo $i; ?>">
					  <input type="checkbox" id="hid_checkbox_<?php echo $i; ?>" name="file[<?php echo $i; ?>][hidden]" value="1" />
					  <?php _e('Upload hidden (will not send notifications)', 'cftp_admin');?>
					</label>
				  </div>
        <?php } ?>
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


		<select multiple="multiple" name="file[<?php echo $i; ?>][categories][]" class="form-control chosen-select addto" data-placeholder="<?php _e('Select one or more options. Type to search.', 'cftp_admin');?>">
			<?php

			/**

			 * The categories list is generated early on the file so the

			 * array doesn't need to be made once on every file.

			 */

			echo generate_categories_options( $get_categories['arranged'], 0 );

			?>
      </select>




			<div class="list_mass_members"> <a href="#" class="btn btn-xs btn-primary add-all" data-type="categories">
				<?php _e('Add all','cftp_admin'); ?>
				</a> <a href="#" class="btn btn-xs btn-primary remove-all" data-type="categories">
				<?php _e('Remove all','cftp_admin'); ?>
				</a> <a href="#" class="btn btn-xs btn-danger copy-all" data-type="categories">
				<?php _e('Copy selections to other files','cftp_admin'); ?>
				</a>
			</div>
		</div>

    <h3>
      <?php _e('Future Send Date', 'cftp_admin');?>
    </h3>

				<div class="form-group">
				  <label for="file[<?php echo $i; ?>][future_send_date]">
					<?php _e('Select a date', 'cftp_admin');?>
				  </label>
				  <div class="input-group future_date">
					<input type="text" class="date-field form-control datapick-field futuredate" readonly id="file[<?php echo $i; ?>][expiry_date]" name="file[<?php echo $i; ?>][future_send_date]" value="<?php echo (!empty($future_send_date)) ? $future_send_date : date('d-m-Y'); ?>" / >
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

								$i++;

							}

						}

						else {

							$upload_failed[] = $file;

						}

					}

				?>
  </div>

  <!-- container -->

			<?php

				/**

				 * Take the list of failed files and store them as a text string

				 * that will be passed on a hidden field when posting the form.

				 */

				$upload_failed = array_filter($upload_failed);

				$upload_failed_hidden = implode(',',$upload_failed);

			?>


				<input type="hidden" name="upload_failed" value="<?php echo $upload_failed_hidden; ?>" />



			  <div class="after_form_buttons">
				<button type="button" name="button" class="btn btn-wide btn-primary" onClick="validateUsers()">Continue</button>
				<button style="display:none;" type="submit" name="submit" class="btn btn-wide btn-primary"  id="upload-continue" >
				<?php _e('Continue','cftp_admin'); ?>
				</button>
			  </div>
		</form>
</div>
			
<?php

	}

	/**

	 * There are no more files to assign.

	 * Send the notifications

	 */

	else {

		include(ROOT_DIR.'/upload-send-notifications.php');

	}



	/**

	 * Generate the table for the failed files.

	 */

	if(count($upload_failed) > 0) {

?>
						<h3>
						  <?php _e('Files not uploaded','cftp_admin'); ?>
						</h3>


<table id="failed_files_tbl" class="footable" data-page-size="<?php echo FOOTABLE_PAGING_NUMBER; ?>">


					  <thead>
						<tr>
						  <th data-sort-initial="true"><?php _e('File Name','cftp_admin'); ?></th>
						</tr>
					  </thead>


					  <tbody>
								<?php

									foreach($upload_failed as $failed) {

								?>
						<tr>
						  <td><?php echo $failed; ?></td>
						</tr>
						<?php

									}

								?>
					  </tbody>
</table>
						<?php

							}

						?>
</div>
		</div>
				</div>
						</div>
								</div>

<div id="myModal" class="modal fade" role="dialog">
  <div class="modal-dialog">

    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Error!</h4>
      </div>
      <div class="modal-body">
        <p>Future date is shouldn't be greater than the expiry date</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
      </div>
    </div>

  </div>
</div>

<script type="text/javascript">

	$(document).ready(function() {

		<?php

			if(!empty($uploaded_files)) {

		?>

				$('.assignto').chosen({
				<?php if ($current_level != 0) { ?>
				no_results_text	: "<?php _e('Invite User (email only) :','cftp_admin'); ?>",
				<?php } ?>

				width			: "98%",

				search_contains	: true,

				});

				$('.addto').chosen({



				width			: "98%",

				search_contains	: true,

				});



			$(".no-results").click(function(e) {

    			console.log($('span',this).text());

			});

			$(document).on('click', ".assigns .no-results", function() {

    			var cc_email = $('span',this).text();

				$(".new_client").append('<option val="'+cc_email+'" selected="selected">'+cc_email+'</option>');

				$(this).parent().parent().siblings('.chosen-choices').prepend('<li class="search-choice"><span>'+cc_email+'</span><a style="text-decoration:none" class="cc-choice-close">&nbsp;&nbsp;x</a></li>');

			});

			$(document).on('click', ".cc-choice-close", function() {

				var cc_remove_op = $(this).siblings('span').text();

				jQuery(".new_client option:contains('"+cc_remove_op+"')").remove();

				$(this).parent().remove();

			});


				$('.future_date .date-field').datepicker({

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



				$('.copy-all').click(function() {

					if ( confirm( "<?php _e('Copy selection to all files?','cftp_admin'); ?>" ) ) {

						var type = $(this).data('type');

						var selector = $(this).closest('.' + type).find('select');



						var selected = new Array();

						$(selector).find('option:selected').each(function() {

							selected.push($(this).val());

						});



						$('.' + type + ' .chosen-select').each(function() {

							$(this).find('option').each(function() {

								if ($.inArray($(this).val(), selected) === -1) {

									$(this).removeAttr('selected');

								}

								else {

								//	$(this).attr('selected', 'selected');
									$(this).prop('selected', true);


								}

							});

						});

						$('select').trigger('chosen:updated');

					}



					return false;

				});


		<?php

			}

		?>



		$('.public_link').popover({

			html : true,

			content: function() {

				var id		= $(this).data('id');

				var token	= $(this).data('token');

				return '<strong><?php _e('Click to select','cftp_admin'); ?></strong><textarea class="input-large public_link_copy" rows="4"><?php echo BASE_URI; ?>download.php?id=' + id + '&token=' + token + '</textarea><small><?php _e('Send this URL to someone to download the file without registering or logging in.','cftp_admin'); ?></small><div class="close-popover"><button type="button" class="btn btn-inverse btn-sm"><?php _e('Close','cftp_admin'); ?></button></div>';

			}

		});



		$(".col_visibility").on('click', '.close-popover button', function(e) {

			var popped = $(this).parents('.col_visibility').find('.public_link');

			popped.popover('hide');

		});



		$(".col_visibility").on('click', '.public_link_copy', function(e) {

			$(this).select();

			$(this).mouseup(function() {

				$(this).unbind("mouseup");

				return false;

			});

		});



	});

</script>

<script language="javascript">

$(document).ready(function() {
     $("[type='text']").attr('id',function(i){return 'chk' + i;});

});

$(document).ready(function() {
     $(".chosen-choices").attr('id',function(i){return 'chosen-' + i;});

});

$(document).ready(function() {
     $(".chosen-select").attr('id',function(i){return 'chslt-' + i;});

});
$(document).ready(function() {
	if($('.file_number').length == 1)
	{
		$('.copy-all').css("display","none");
	 }

});

</script>

<script language="javascript">
$('[id^=pub_checkbox_]').change(function(e) {
var chslt = $(this).closest('.edit_files').find('.chosen-select_pub');
    chslt.prop('disabled', true).trigger("chosen:updated");
if ($(this).is(":checked")){

	chslt.prop('disabled', true).val('').trigger('chosen:updated');

}
else if (!$(this).is(":checked")) {
chslt.prop('disabled', false).trigger("chosen:updated");
}
});
$(document).ready(function() {
	var today = new Date();
		    var tomorrow = new Date();
		    tomorrow.setDate(today.getDate() +1);
					$('.ex_date .date-field').datepicker({

						format		: 'dd-mm-yyyy',
						autoclose	: true,
						todayHighlight	: true,
	          startDate       : tomorrow

					});
});

$(document).ready(function() {
    var readyfiles = $("#readyfiles").html().trim();
    if (readyfiles == "") {
        $("#readydiv").hide();
    }
});

function validateUsers() {
	if($(".expires").prop('checked') == true){
		var date= new Date($('.exPdate').val());
		var date1= new Date($('.futuredate').val());
	    if (date.getTime() <= date1.getTime()){
	    	$('#myModal').modal('show'); 
	    	return false;
	    }
	}



	var invalid_invites = 0;
	$('.new_client option').each(function () {
		var userinput = $(this).val();
	var pattern = /^\b[A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b$/i

	if(!pattern.test(userinput))
	{
	 $(".search-choice span:contains('"+userinput+"')").parent().remove();
	$(this).remove();
	invalid_invites++;
	}
	});
	if (invalid_invites !=0 ) {
	alert('Invite users with valid email address');
	invalid_invites=0;
	} else{
	$('#upload-continue').click();
	}
}

// function expdatechange(){


// alert(timeStamp($('.exPdate').val()));

	// var exPdate=$('.exPdate').val();
	// console.log(exPdate);
	// var futuredate=$('.futuredate').val();
	// console.log(futuredate);

	// var date1 = new Date(exPdate);
	// console.log(date1);
	// var date2 = new Date(futuredate);
	// console.log(date2);

	// var d1 = Date.parse($('.exPdate').val());
	// console.log(d1);
	// var d2 = Date.parse($('.futuredate').val());
	// console.log(d2);
	// if(date1 <= date2)
	// {
	// 	console.log('less');
	// }else{
	// 	console.log('grater');	
	// }
// }
</script>


<?php

	include('footer.php');

?>
