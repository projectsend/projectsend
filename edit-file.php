<?php
/**
 * Edit a file name or description.
 * Files can only be edited by the uploader and level 9 or 8 users.
 *
 * @package ProjectSend
 */
define('IS_FILE_EDITOR', true);

$allowed_levels = array(9,8,7,0);
require_once 'bootstrap.php';

//Add a session check here
if(!check_for_session()) {
    header("location:" . BASE_URI . "index.php");
}

$active_nav = 'files';

$page_title = __('Edit file','cftp_admin');
$page_id = 'file_editor';

include_once ADMIN_VIEWS_DIR . DS . 'header.php';

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
				if (current_role_in($edit_file_allowed)) {
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
					if (!empty($file['name'])) {
						/**
						* If the uploader is a client, set the "client" var to the current
						* uploader username, since the "client" field is not posted.
						*/
						if (CURRENT_USER_LEVEL == 0) {
							$file['assignments']['clients'] = $global_user;
						}

                        $this_upload = new ProjectSend\Classes\UploadFile;
                        $this_upload->setId($this_file_id);

                        /**
						 * Unassigned files are kept as orphans and can be related
						 * to clients or groups later.
						 */

						/** Add to the database for each client / group selected */
						$add_arguments = array(
                            'file_original' => $edit_file_info['url'],
                            'name' => $file['name'],
                            'description' => $file['description'],
                            'uploader' => $global_user,
                            'uploader_id' => CURRENT_USER_ID,
                            'expiry_date' => $file['expiry_date']
                        );

						/** Set notifications to YES by default */
						$send_notifications = true;

						if (!empty($file['hidden'])) {
							$add_arguments['hidden'] = $file['hidden'];
							$send_notifications = false;
						}

						if (CURRENT_USER_LEVEL != 0) {

							if (!empty($file['expires'])) {
								$add_arguments['expires'] = '1';
							}

							if (!empty($file['public'])) {
								$add_arguments['public'] = '1';
                            }
                            
                            $this_upload->saveAssignments($file['assignments']);


							if (!empty($file['assignments']['clients']) || !empty($file['assignments']['groups'])) {
								/**
								 * Remove already assigned clients/groups from the list.
								 * Only adds assignments to the NEWLY selected ones.
								 */
                                $selected_clients = $file['assignments']['clients'];
                                $selected_groups = $file['assignments']['groups'];
								foreach ($file_on_clients as $this_client) { $compare_clients[] = $this_client; }
								foreach ($file_on_groups as $this_group) { $compare_groups[] = $this_group; }
								if (!empty($compare_clients)) {
									$selected_clients = array_diff($selected_clients,$compare_clients);
								}
								if (!empty($compare_groups)) {
									$selected_groups = array_diff($selected_groups,$compare_groups);
								}
                                $add_arguments['assign_to']['clients'] = $selected_clients;
                                $add_arguments['assign_to']['groups'] = $selected_groups;

								/**
								 * On cleaning the DB, only remove the clients/groups
								 * That just have been deselected.
								 */
								$clean_who = $file['assignments'];
							}
							else {
								$clean_who = 'All';
                            }
                            
                            print_r($clean_who); exit;

							/** CLEAN deletes the removed users/groups from the assignments table */
							if ($clean_who == 'All') {
								$clean_all_arguments = array(
																'owner_id' => CURRENT_USER_ID, /** For the log */
																'file_id' => $this_file_id,
																'file_name' => $file['name']
															);
								$clean_assignments = $this_upload->cleanAllAssignments($clean_all_arguments);
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
								$clean_assignments = $this_upload->cleanAssignments($clean_arguments);
							}

							$categories_arguments = array(
														'file_id'		=> $this_file_id,
														'categories'	=> !empty( $file['categories'] ) ? $file['categories'] : '',
													);
							$this_upload->setCategories( $categories_arguments );
						}

						/** Uploader is a client */
						if (CURRENT_USER_LEVEL == 0) {
							$add_arguments['assign_to']['clients'] = array(CURRENT_USER_ID);
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
						$process_file = $this_upload->saveExisting($add_arguments);
						if ($process_file['database'] == true) {
							$add_arguments['new_file_id'] = $process_file['new_file_id'];
							$add_arguments['all_users'] = $users;
							$add_arguments['all_groups'] = $groups;

							if (CURRENT_USER_LEVEL != 0) {
								/**
								 * 2- Add the assignments to the database
								 */
								$process_assignment = $this_upload->addFileAssignment($add_arguments);

								/**
								 * 3- Hide for everyone if checked
								 */
								if (!empty($file['hideall'])) {
									$this_file = new ProjectSend\Classes\FilesActions;
									$hide_file = $this_file->hideForEveryone($this_file_id);
								}
								/**
								 * 4- Add the notifications to the database
								 */
								if ($send_notifications == true) {
									$process_notifications = $this_upload->addNotifications($add_arguments);
								}
							}

							$logger = new \ProjectSend\Classes\ActionsLog();
							$log_action_args = array(
													'action' => $action_log_number,
													'owner_id' => CURRENT_USER_ID,
													'owner_user' => $global_user,
													'affected_file' => $process_file['new_file_id'],
													'affected_file_name' => $file['name']
												);
							$new_record_action = $logger->addEntry($log_action_args);

							$msg = __('The file has been edited succesfuly.','cftp_admin');
                            echo system_message('success',$msg);
                            
                            require_once INCLUDES_DIR . DS . 'upload-send-notifications.php';
						}
					}
				}
			}
			/** Validations OK, show the editor */
	?>
			<form action="edit-file.php?file_id=<?php echo filter_var($this_file_id,FILTER_VALIDATE_INT); ?>" method="post" name="edit_file" id="edit_file">
                <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>" />

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
						$current_statement = $dbh->prepare("SELECT cat_id FROM " . TABLE_CATEGORIES_RELATIONS . " WHERE file_id = :id");
						$current_statement->bindParam(':id', $this_file_id, PDO::PARAM_INT);
						$current_statement->execute();
						if ($current_statement->rowCount() > 0) {
							while ( $assignment_row = $current_statement->fetch() ) {
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
																<input type="text" name="file[<?php echo $i; ?>][name]" value="<?php echo html_output($row['filename']); ?>" class="form-control file_title" placeholder="<?php _e('Enter here the required file title.', 'cftp_admin');?>" required />
															</div>

															<div class="form-group">
																<label><?php _e('Description', 'cftp_admin');?></label>
																<textarea name="file[<?php echo $i; ?>][description]" class="<?php if ( FILES_DESCRIPTIONS_USE_CKEDITOR == 1 ) { echo 'ckeditor'; } ?> form-control" placeholder="<?php _e('Optionally, enter here a description for the file.', 'cftp_admin');?>"><?php echo (!empty($row['description'])) ? html_output($row['description']) : ''; ?></textarea>
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
															} /** Close CURRENT_USER_LEVEL check */
														?>
														</div>
													</div>
											<?php
												} /** Close CURRENT_USER_LEVEL check */
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
															<label><?php _e('Clients', 'cftp_admin');?>:</label>
															<select multiple="multiple" name="file[<?php echo $i; ?>][assignments][clients][]" id="select_clients_<?php echo $i; ?>" class="form-control chosen-select select_clients" data-placeholder="<?php _e('Select one or more options. Type to search.', 'cftp_admin');?>">
                                                                <?php
                                                                    /**
                                                                     * The clients list is generated early on the file so the
                                                                     * array doesn't need to be made once on every file.
                                                                     */
                                                                    foreach($clients as $client => $client_name) {
                                                                ?>
                                                                        <option value="<?php echo $client; ?>"<?php if (in_array($client,$file_on_clients)) { echo ' selected="selected"'; } ?>>
                                                                            <?php echo $client_name; ?>
                                                                        </option>
                                                                <?php
                                                                    }
                                                                ?>
                                                            </select>
															<div class="list_mass_members">
                                                                <span class="btn btn-xs btn-primary add-all" data-type="clients" data-fileid="<?php echo $i; ?>"><?php _e('Add all','cftp_admin'); ?></span>
                                                                <span class="btn btn-xs btn-primary remove-all" data-type="clients" data-fileid="<?php echo $i; ?>"><?php _e('Remove all','cftp_admin'); ?></span>
															</div>

															<label><?php _e('Groups', 'cftp_admin');?>:</label>
															<select multiple="multiple" name="file[<?php echo $i; ?>][assignments][groups][]" id="select_groups_<?php echo $i; ?>" class="form-control chosen-select select_groups" data-placeholder="<?php _e('Select one or more options. Type to search.', 'cftp_admin');?>">
                                                                <?php
                                                                    /**
                                                                     * The groups list is generated early on the file so the
                                                                     * array doesn't need to be made once on every file.
                                                                     */
                                                                    foreach($groups as $group => $group_name) {
                                                                ?>
                                                                        <option value="<?php echo $group; ?>"<?php if (in_array($group,$file_on_groups)) { echo ' selected="selected"'; } ?>>
                                                                            <?php echo $group_name; ?>
                                                                        </option>
                                                                <?php
                                                                    }
                                                                ?>
															</select>
															<div class="list_mass_members">
																<span class="btn btn-xs btn-primary add-all" data-type="groups" data-fileid="<?php echo $i; ?>"><?php _e('Add all','cftp_admin'); ?></span>
																<span class="btn btn-xs btn-primary remove-all" data-type="groups" data-fileid="<?php echo $i; ?>"><?php _e('Remove all','cftp_admin'); ?></span>
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
															<select multiple="multiple" name="file[<?php echo $i; ?>][categories][]" id="select_categories_<?php echo $i; ?>" class="form-control chosen-select select_categories" data-placeholder="<?php _e('Select one or more options. Type to search.', 'cftp_admin');?>">
																<?php
																	/**
																	 * The categories list is generated early on the file so the
																	 * array doesn't need to be made once on every file.
																	 */
																	echo generate_categories_options( $get_categories['arranged'], 0, $current_categories );
																?>
															</select>
															<div class="list_mass_members">
																<a href="#" class="btn btn-xs btn-primary add-all" data-type="categories" data-fileid="<?php echo $i; ?>"><?php _e('Add all','cftp_admin'); ?></a>
																<a href="#" class="btn btn-xs btn-primary remove-all" data-type="categories" data-fileid="<?php echo $i; ?>"><?php _e('Remove all','cftp_admin'); ?></a>
															</div>
														</div>
													</div>
											<?php
												} /** Close CURRENT_USER_LEVEL check */
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

<?php
	include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
