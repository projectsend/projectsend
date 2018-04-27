<?php
/**
 * Edit a file name or description.
 * Files can only be edited by the uploader and level 9 or 8 users.
 *
 * @package ProjectSend
 */
define('IS_FILE_EDITOR', true);

$load_scripts	= array(
						'datepicker',
						'chosen',
						'ckeditor'
					);

$allowed_levels = array(9,8,7,0);
require_once __DIR__ . '/../sys.includes.php';

//Add a session check here
if(!check_for_session()) {
    header("location:" . BASE_URI . "index.php");
}

$active_nav = 'files';

$page_title = __('Edit file','cftp_admin');
include('header.php');

define('CAN_INCLUDE_FILES', true);

/**
 * The file's id is passed on the URI.
 */
if (!empty($_GET['file_id'])) {
	$this_file_id = $_GET['file_id'];
}

/** Fill the users array that will be used on the notifications process */
$users = array();
$statement = $dbh->prepare("SELECT id, name, level FROM " . TABLE_USERS . " ORDER BY name ASC");
$statement->execute();
$statement->setFetchMode(PDO::FETCH_ASSOC);
while( $row = $statement->fetch() ) {
	$users[$row["id"]] = $row["name"];
	if ($row["level"] == '0') {
		$clients[$row["id"]] = $row["name"];
	}
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



//echo '<pre>'; print_r($_POST); echo '</pre>'; // DEBUG

?>

<div class="col-xs-12">
	<?php
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
					$no_results_message = __('Please go to the clients or groups administration page, select "Manage files" from any client and then click on "Edit" on any file to return here.','cftp_admin');
					break;
				case 'id_not_exists':
					$no_results_message = __('There is not file with that ID number.','cftp_admin');
					break;
				case 'not_uploader':
					$no_results_message = __("You don't have permission to edit this file.",'cftp_admin');
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

			if ( isset($_POST['submit'] ) ) {

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
												'file_original'	=> $edit_file_info['url'],
												'name'			=> $file['name'],
												'description'	=> $file['description'],
												'uploader'		=> $global_user,
												'uploader_id'	=> CURRENT_USER_ID,
												'expiry_date'	=> $file['expiry_date']
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
																'owner_id' => CURRENT_USER_ID, /** For the log */
																'file_id' => $this_file_id,
																'file_name' => $file['name']
															);
								$clean_assignments = $this_upload->clean_all_assignments($clean_all_arguments);
							}
							else {
								$clean_arguments = array (
														'owner_id' => CURRENT_USER_ID, /** For the log */
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

						/** Uploader is a client */
						if ($current_level == 0) {
							$add_arguments['assign_to'] = array('c'.CURRENT_USER_ID);
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
													'owner_id' => CURRENT_USER_ID,
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
			<form action="edit-file.php?file_id=<?php echo filter_var($this_file_id,FILTER_VALIDATE_INT); ?>" method="post" name="edit_file" id="edit_file">
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
							$file_name_title = (!empty( $row['original_url'] ) ) ? $row['original_url'] : $row['url'];
					?>
							<div class="file_editor <?php if ($i%2) { echo 'f_e_odd'; } ?>">
								<div class="row">
									<div class="col-sm-12">
										<div class="file_number">
											<p><span class="glyphicon glyphicon-saved" aria-hidden="true"></span><?php echo html_output($file_name_title); ?></p>
										</div>
									</div>
								</div>
								<div class="row edit_files">
									<div class="col-sm-12">
										<div class="row edit_files_blocks">
											<div class="<?php echo ($global_level != 0 || CLIENTS_CAN_SET_EXPIRATION_DATE == '1') ? 'col-sm-6 col-md-3' : 'col-sm-12 col-md-12'; ?>  column">
												<div class="file_data">
													<div class="row">
														<div class="col-sm-12">
															<h3><?php _e('File information', 'cftp_admin');?></h3>

															<div class="form-group">
																<label><?php _e('Title', 'cftp_admin');?></label>
																<input type="text" name="file[<?php echo $i; ?>][name]" value="<?php echo html_output($row['filename']); ?>" class="form-control file_title" placeholder="<?php _e('Enter here the required file title.', 'cftp_admin');?>" />
															</div>

															<div class="form-group">
																<label><?php _e('Description', 'cftp_admin');?></label>
																<textarea name="file[<?php echo $i; ?>][description]" class="<?php if ( DESCRIPTIONS_USE_CKEDITOR == 1 ) { echo 'ckeditor'; } ?> form-control" placeholder="<?php _e('Optionally, enter here a description for the file.', 'cftp_admin');?>"><?php echo (!empty($row['description'])) ? html_output($row['description']) : ''; ?></textarea>
															</div>
														</div>
													</div>
												</div>
											</div>

											<?php
												/** The following options are available to users or client if clients_can_set_expiration_date set. */
												if ($global_level != 0 || CLIENTS_CAN_SET_EXPIRATION_DATE == '1' ) {
											?>
													<div class="col-sm-6 col-md-3 column_even column">
														<div class="file_data">
															<?php
																/**
																* Only show the EXPIRY options if the current
																* uploader is a system user or client if clients_can_set_expiration_date is set.
																*/
																if (!empty($row['expiry_date'])) {
																	$expiry_date = date('d-m-Y', strtotime($row['expiry_date']));
																}
															?>
															<h3><?php _e('Expiration date', 'cftp_admin');?></h3>

															<div class="form-group">
																<label for="file[<?php echo $i; ?>][expires_date]"><?php _e('Select a date', 'cftp_admin');?></label>
																<div class="input-group date-container">
																	<input type="text" class="date-field form-control datapick-field" readonly id="file[<?php echo $i; ?>][expiry_date]" name="file[<?php echo $i; ?>][expiry_date]" value="<?php echo (!empty($expiry_date)) ? $expiry_date : date('d-m-Y'); ?>" />
																	<div class="input-group-addon">
																		<i class="glyphicon glyphicon-time"></i>
																	</div>
																</div>
															</div>

															<div class="checkbox">
																<label for="exp_checkbox">
																	<input type="checkbox" id="exp_checkbox" name="file[<?php echo $i; ?>][expires]" value="1" <?php if ($row['expires']) { ?>checked="checked"<?php } ?> /> <?php _e('File expires', 'cftp_admin');?>
																</label>
															</div>

															<?php
																/** The following options are available to users only */
																if ($global_level != 0) {
															?>
															<div class="divider"></div>

															<h3><?php _e('Public downloading', 'cftp_admin');?></h3>
															<div class="checkbox">
																<label for="pub_checkbox">
																	<input type="checkbox" id="pub_checkbox" name="file[<?php echo $i; ?>][public]" value="1" <?php if ($row['public_allow']) { ?>checked="checked"<?php } ?> /> <?php _e('Allow public downloading of this file.', 'cftp_admin');?>
																</label>
															</div>

															<div class="divider"></div>
															<h3><?php _e('Public URL', 'cftp_admin');?></h3>
															<div class="public_url">
																<div class="form-group">
																	<textarea class="form-control" readonly><?php echo BASE_URI; ?>download.php?id=<?php echo $row['id']; ?>&token=<?php echo html_output($row['public_token']); ?></textarea>
																</div>
															</div>
														<?php
															} /** Close $current_level check */
														?>
														</div>
													</div>
											<?php
												} /** Close $current_level check */
											?>

											<?php
												/** The following options are available to users only */
												if ($global_level != 0) {
											?>
													<div class="col-sm-6 col-md-3 assigns column">
														<div class="file_data">
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
																<a href="#" class="btn btn-xs btn-primary add-all" data-type="assigns"><?php _e('Add all','cftp_admin'); ?></a>
																<a href="#" class="btn btn-xs btn-primary remove-all" data-type="assigns"><?php _e('Remove all','cftp_admin'); ?></a>
															</div>

															<div class="divider"></div>

															<div class="checkbox">
																<label for="hid_checkbox">
																	<input type="checkbox" id="hid_checkbox" name="file[<?php echo $i; ?>][hidden]" value="1" /> <?php _e('Mark as hidden (will not send notifications) for new assigned clients and groups.', 'cftp_admin');?>
																</label>
															</div>
															<div class="checkbox">
																<label for="hid_existing_checkbox">
																	<input type="checkbox" id="hid_existing_checkbox" name="file[<?php echo $i; ?>][hideall]" value="1" /> <?php _e('Hide from every already assigned clients and groups.', 'cftp_admin');?>
																</label>
															</div>
														</div>
													</div>

													<div class="col-sm-6 col-md-3 categories column">
														<div class="file_data">
															<h3><?php _e('Categories', 'cftp_admin');?></h3>
															<label><?php _e('Add to', 'cftp_admin');?>:</label>
															<select multiple="multiple" name="file[<?php echo $i; ?>][categories][]" class="form-control chosen-select" data-placeholder="<?php _e('Select one or more options. Type to search.', 'cftp_admin');?>">
																<?php
																	/**
																	 * The categories list is generated early on the file so the
																	 * array doesn't need to be made once on every file.
																	 */
																	echo generate_categories_options( $get_categories['arranged'], 0, $current_categories );
																?>
															</select>
															<div class="list_mass_members">
																<a href="#" class="btn btn-xs btn-primary add-all" data-type="categories"><?php _e('Add all','cftp_admin'); ?></a>
																<a href="#" class="btn btn-xs btn-primary remove-all" data-type="categories"><?php _e('Remove all','cftp_admin'); ?></a>
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
						<a href="<?php echo BASE_URI; ?>manage-files.php" name="cancel" class="btn btn-default btn-wide"><?php _e('Cancel','cftp_admin'); ?></a>
						<button type="submit" name="submit" class="btn btn-wide btn-primary"><?php _e('Save','cftp_admin'); ?></button>
					</div>
				</div>
			</form>
	<?php
		}
	?>
</div>

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

<?php
	include('footer.php');
