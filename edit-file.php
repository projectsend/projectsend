<?php
/**
 * Edit a file name or description.
 * Files can only be edited by the uploader and level 9 or 8 users.
 *
 * @package ProjectSend
 */
 
$multiselect	= 1;
$datepicker		= 1;

$allowed_levels = array(9,8,7,0);
require_once('sys.includes.php');

$active_nav = 'files';

$page_title = __('Edit file','cftp_admin');
include('header.php');

define('CAN_INCLUDE_FILES', true);

/**
 * The file's id is passed on the URI.
 */
if (!empty($_GET['file_id'])) {
	$this_file_id = mysql_real_escape_string($_GET['file_id']);
}

/** Fill the users array that will be used on the notifications process */
$users = array();
$cq = "SELECT id, name, level FROM tbl_users ORDER BY name ASC";
$sql = $database->query($cq);
while($row = mysql_fetch_array($sql)) {
	$users[$row["id"]] = $row["name"];
	if ($row["level"] == '0') {
		$clients[$row["id"]] = $row["name"];
	}
}
/** Fill the groups array that will be used on the form */
$groups = array();
$cq = "SELECT id, name FROM tbl_groups ORDER BY name ASC";
$sql = $database->query($cq);
	while($row = mysql_fetch_array($sql)) {
	$groups[$row["id"]] = $row["name"];
}

/**
 * Get the user level to determine if the uploader is a
 * system user or a client.
 */
$current_level = get_current_user_level();



//echo '<pre>'; print_r($_POST); echo '</pre>'; // DEBUG

?>

<div id="main">
	<h2><?php echo $page_title; ?></h2>

	<?php
		/**
		 * Show an error message if no ID value is passed on the URI.
		 */
		if(empty($this_file_id)) {
			$no_results_error = 'no_id_passed';
		}
		else {
			$database->MySQLDB();
			$files_query = 'SELECT * FROM tbl_files WHERE id="' . $this_file_id . '"';
	
			/**
			 * Count the files assigned to this client. If there is none, show
			 * an error message.
			 */
			$sql = $database->query($files_query);
			$count = mysql_num_rows($sql);
			if (!$count) {
				$no_results_error = 'id_not_exists';
			}
	
			/**
			 * Continue if client exists and has files under his account.
			 */
			while($row = mysql_fetch_array($sql)) {
				$edit_file_info['url'] = $row['url'];
				$edit_file_info['id'] = $row['id'];

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
			<div class="whiteform whitebox whitebox_text">
				<?php echo $no_results_message; ?>
			</div>
	<?php
		}
		else {

			/**
			 * See what clients or groups already have this file assigned.
			 */
			$file_on_clients = array();
			$file_on_groups = array();

			if(isset($_POST['submit'])) {

				$assignments_query = 'SELECT file_id, client_id, group_id FROM tbl_files_relations WHERE file_id="' . $this_file_id . '"';
				$assignments_sql = $database->query($assignments_query);
				$assignments_count = mysql_num_rows($assignments_sql);
				if ($assignments_count > 0) {
					while ($assignment_row = mysql_fetch_array($assignments_sql)) {
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
						if ($current_level == 0) {
							$file['assignments'] = 'c'.$global_user;
						}
						
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
					
						if (!empty($file['hidden'])) {
							$add_arguments['hidden'] = $file['hidden'];
							$send_notifications = false;
						}
						
						if ($current_level != 0) {

							if (!empty($file['expires'])) {
								$add_arguments['expires'] = '1';
							}

							if (!empty($file['public'])) {
								$add_arguments['public'] = '1';
							}

							if (!empty($file['assignments'])) {
								/**
								 * Remove already assigned clients/groups from the list.
								 * Only adds assignments to the NEWLY selected ones.
								 */
								$full_list = $file['assignments'];
								foreach ($file_on_clients as $this_client) { $compare_clients[] = 'c'.$this_client; }
								foreach ($file_on_groups as $this_group) { $compare_groups[] = 'g'.$this_group; }
								if (!empty($compare_clients)) {
									$full_list = array_diff($full_list,$compare_clients);
								}
								if (!empty($compare_groups)) {
									$full_list = array_diff($full_list,$compare_groups);
								}
								$add_arguments['assign_to'] = $full_list;
								
								/**
								 * On cleaning the DB, only remove the clients/groups
								 * That just have been deselected.
								 */
								$clean_who = $file['assignments'];
							}
							else {
								$clean_who = 'All';
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
						}
						
						/** Uploader is a client */
						if ($current_level == 0) {
							$add_arguments['assign_to'] = array('c'.$global_id);
							$add_arguments['hidden'] = '0';
							$add_arguments['uploader_type'] = 'client';
							$action_log_number = 33;
						}
						else {
							$add_arguments['uploader_type'] = 'user';
							$action_log_number = 32;
						}
						/**
						 * 1- Add the file to the database
						 */
						$process_file = $this_upload->upload_add_to_database($add_arguments);
						if($process_file['database'] == true) {
							$add_arguments['new_file_id'] = $process_file['new_file_id'];
							$add_arguments['all_users'] = $users;
							$add_arguments['all_groups'] = $groups;
							
							if ($current_level != 0) {
								/**
								 * 2- Add the assignments to the database
								 */
								$process_assignment = $this_upload->upload_add_assignment($add_arguments);

								/**
								 * 3- Hide for everyone if checked
								 */
								if (!empty($file['hideall'])) {
									$this_file = new FilesActions();
									$hide_file = $this_file->hide_for_everyone($this_file_id);
								}
								/**
								 * 4- Add the notifications to the database
								 */
								if ($send_notifications == true) {
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

							$msg = __('The file has been edited succesfuly.','cftp_admin');
							echo system_message('ok',$msg);
							
							include(ROOT_DIR.'/upload-send-notifications.php');
						}
					}
				}
			}
			/** Validations OK, show the editor */
	?>
			<form action="edit-file.php?file_id=<?php echo $this_file_id; ?>" method="post" name="edit_file" id="edit_file">
				<?php
					/** Reconstruct the current assignments arrays */
					$file_on_clients = array();
					$file_on_groups = array();
					$assignments_query = 'SELECT file_id, client_id, group_id FROM tbl_files_relations WHERE file_id="' . $this_file_id . '"';
					$assignments_sql = $database->query($assignments_query);
					$assignments_count = mysql_num_rows($assignments_sql);
					if ($assignments_count > 0) {
						while ($assignment_row = mysql_fetch_array($assignments_sql)) {
							if (!empty($assignment_row['client_id'])) {
								$file_on_clients[] = $assignment_row['client_id'];
							}
							elseif (!empty($assignment_row['group_id'])) {
								$file_on_groups[] = $assignment_row['group_id'];
							}
						}
					}

					$i = 1;
					$files_query = 'SELECT * FROM tbl_files WHERE id="' . $this_file_id . '"';
					$sql = $database->query($files_query);
					while($row = mysql_fetch_array($sql)) {
				?>
						<div class="row-fluid edit_files">
							<div class="span1">
								<div class="file_number">
									<p><?php echo $i; ?></p>
								</div>
							</div>
							<div class="span11">
								<div class="row-fluid">
									<div class="<?php echo ($global_level != 0) ? 'span4' : 'span12'; ?> file_data">
										<div class="row-fluid">
											<div class="span12">
												<h3><?php _e('File information', 'cftp_admin');?></h3>
												<p class="on_disc_name">
													<?php echo $row['url']; ?>
												</p>
												<label><?php _e('Title', 'cftp_admin');?></label>
												<input type="text" name="file[<?php echo $i; ?>][name]" value="<?php echo $row['filename']; ?>" class="file_title" placeholder="<?php _e('Enter here the required file title.', 'cftp_admin');?>" />
												<label><?php _e('Description', 'cftp_admin');?></label>
												<textarea name="file[<?php echo $i; ?>][description]" placeholder="<?php _e('Optionally, enter here a description for the file.', 'cftp_admin');?>"><?php echo (!empty($row['description'])) ? $row['description'] : ''; ?></textarea>
											</div>
										</div>
									</div>
									<?php
										/** The following options are available to users only */
										if ($global_level != 0) {
									?>
											<div class="span4 file_data">
												<?php
													/**
													* Only show the EXPIRY options if the current
													* uploader is a system user, and not a client.
													*/
													if (!empty($row['expiry_date'])) {
														$expiry_date = date('d-m-Y', strtotime($row['expiry_date']));
													}
												?>
												<h3><?php _e('Expiration date', 'cftp_admin');?></h3>
												<label><input type="checkbox" name="file[<?php echo $i; ?>][expires]" value="1" <?php if ($row['expires']) { ?>checked="checked"<?php } ?> /> <?php _e('File expires', 'cftp_admin');?></label>

												<label for="file[<?php echo $i; ?>][expires_date]"><?php _e('Select a date', 'cftp_admin');?></label>

												<div class="input-append date">
													<input type="text" class="span8 datapick-field" readonly="readonly" id="file[<?php echo $i; ?>][expiry_date]" name="file[<?php echo $i; ?>][expiry_date]" value="<?php echo (!empty($expiry_date)) ? $expiry_date : date('d-m-Y'); ?>" /><span class="add-on"><i class="icon-th"></i></span>
												</div>
												
												<div class="divider"></div>

												<h3><?php _e('Public downloading', 'cftp_admin');?></h3>
												<label><input type="checkbox" name="file[<?php echo $i; ?>][public]" value="1" <?php if ($row['public_allow']) { ?>checked="checked"<?php } ?> /> <?php _e('Allow public downloading of this file.', 'cftp_admin');?></label>
											</div>
											<div class="span4 assigns file_data">
												<?php
													/**
													* Only show the CLIENTS select field if the current
													* uploader is a system user, and not a client.
													*/
												?>
												<h3><?php _e('Assignations', 'cftp_admin');?></h3>
												<label><?php _e('Assign this file to', 'cftp_admin');?>:</label>
												<select multiple="multiple" name="file[<?php echo $i; ?>][assignments][]" class="form-control chosen-select" data-placeholder="<?php _e('Select one or more options. Type to search.', 'cftp_admin');?>">
													<optgroup label="<?php _e('Clients', 'cftp_admin');?>">
														<?php
															/**
															 * The clients list is generated early on the file so the
															 * array doesn't need to be made once on every file.
															 */
															foreach($clients as $client => $client_name) {
															?>
																<option value="<?php echo 'c'.$client; ?>"<?php if (in_array($client,$file_on_clients)) { echo ' selected="selected"'; } ?>>
																	<?php echo $client_name; ?>
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
																<option value="<?php echo 'g'.$group; ?>"<?php if (in_array($group,$file_on_groups)) { echo ' selected="selected"'; } ?>>
																	<?php echo $group_name; ?>
																</option>
															<?php
															}
														?>
													</optgroup>
												</select>
												<div class="list_mass_members">
													<a href="#" class="btn add-all"><?php _e('Add all','cftp_admin'); ?></a>
													<a href="#" class="btn remove-all"><?php _e('Remove all','cftp_admin'); ?></a>
												</div>

												<div class="divider"></div>
	
												<label><input type="checkbox" name="file[<?php echo $i; ?>][hidden]" value="1" /> <?php _e('Mark as hidden (will not send notifications) for new assigned clients and groups.', 'cftp_admin');?></label>
												<label><input type="checkbox" name="file[<?php echo $i; ?>][hideall]" value="1" /> <?php _e('Hide from every already assigned clients and groups.', 'cftp_admin');?></label>
											</div>
									<?php
										} /** Close $current_level check */
									?>
								</div>
							</div>
						</div>
				<?php
					}
				?>
				<div class="after_form_buttons">
					<a href="<?php echo BASE_URI; ?>manage-files.php" name="cancel" class="btn btn-wide"><?php _e('Cancel','cftp_admin'); ?></a>
					<button type="submit" name="submit" class="btn btn-wide btn-primary"><?php _e('Continue','cftp_admin'); ?></button>
				</div>
			</form>
	<?php
		}

		$database->Close();
	?>
</div>

<script type="text/javascript">
	$(document).ready(function() {
		$('.chosen-select').chosen({
			no_results_text	: "<?php _e('No results where found.','cftp_admin'); ?>",
			width			: "98%"
		});

		$('.input-append.date').datepicker({
			format			: 'dd-mm-yyyy',
			autoclose		: true,
			todayHighlight	: true
		});

		$('.add-all').click(function(){
			var selector = $(this).closest('.assigns').find('select');
			$(selector).find('option').each(function(){
				$(this).prop('selected', true);
			});
			$('select').trigger('chosen:updated');
			return false;
		});

		$('.remove-all').click(function(){
			var selector = $(this).closest('.assigns').find('select');
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

<?php include('footer.php'); ?>