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

$active_nav = 'files';

$page_title = __('Upload files', 'cftp_admin');
include('header.php');

define('CAN_INCLUDE_FILES', true);
?>

<div id="main">
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
 * Make an array of file urls that are on the DB already.
 */
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
				if ($current_level == 0) {
					$file['assignments'] = 'c'.$global_user;
				}
				
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
												'description' => $file['description'],
												'uploader' => $global_user,
												'uploader_id' => $global_id
											);

						/** Set notifications to YES by default */
						$send_notifications = true;

						if (!empty($file['hidden'])) {
							$add_arguments['hidden'] = $file['hidden'];
							$send_notifications = false;
						}
						
						if (!empty($file['assignments'])) {
							$add_arguments['assign_to'] = $file['assignments'];
							$assignations_count	= count($file['assignments']);
						}
						else {
							$assignations_count	= '0';
						}
						
						/** Uploader is a client */
						if ($current_level == 0) {
							$add_arguments['assign_to'] = array('c'.$client_my_id);
							$add_arguments['hidden'] = '0';
							$add_arguments['uploader_type'] = 'client';
							$add_arguments['expires'] = '0';
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
							if ($send_notifications == true) {
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

	/**
	 * Generate the table of files that were assigned to a client
	 * on this last POST. These files appear on this table only once,
	 * so if there is another submition of the form, only the new
	 * assigned files will be displayed.
	 */
	if(!empty($upload_finish)) {
?>
		<h3><?php _e('Files uploaded correctly','cftp_admin'); ?></h3>
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
								<td class="<?php echo (!empty($uploaded['hidden'])) ? 'file_status_hidden' : 'file_status_visible'; ?>">

									<?php
										$status_hidden	= __('Hidden','cftp_admin');
										$status_visible	= __('Visible','cftp_admin');
										$class			= (!empty($uploaded['hidden'])) ? 'danger' : 'success';
									?>
									<span class="label label-<?php echo $class; ?>">
										<?php echo ( !empty( $hidden ) && $hidden == 1) ? $status_hidden : $status_visible; ?>
									</span>
								</td>
								<td>
									<?php $class = ($uploaded['assignations'] > 0) ? 'success' : 'danger'; ?>
									<span class="label label-<?php echo $class; ?>">
										<?php echo $uploaded['assignations']; ?>
									</span>
								</td>
								<td class="col_visibility">
									<?php
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
											</a>
								</td>
						<?php
							}
						?>
						<td>
							<a href="edit-file.php?file_id=<?php echo html_output($uploaded['new_file_id']); ?>" class="btn-primary btn btn-sm"><?php _e('Edit file','cftp_admin'); ?></a>
							<?php
								/*
								 * Show the "My files" button only to clients
								 */
								if ($current_level == 0) {
							?>
									<a href="my_files/" class="btn-primary btn btn-sm"><?php _e('View my files','cftp_admin'); ?></a>
							<?php
								}
							?>
						</td>
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
		<h3><?php _e('Files ready to upload','cftp_admin'); ?></h3>
		<p><?php _e('Please complete the following information to finish the uploading process. Remember that "Title" is a required field.','cftp_admin'); ?></p>

		<?php
			if ($current_level != 0) {
		?>
			<div class="message message_info"><strong><?php _e('Note','cftp_admin'); ?></strong>: <?php _e('You can skip assigning if you want. The files are retained and you may add them to clients or groups later.','cftp_admin'); ?></div>
		<?php
			}

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
			<?php
				foreach($uploaded_files as $add_uploaded_field) {
					echo '<input type="hidden" name="finished_files[]" value="'.$add_uploaded_field.'" />
					';
				}
			?>
			
			<div class="container-fluid">
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
							//$file = $this_upload->safe_rename($file);
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
												<p><span class="glyphicon glyphicon-saved" aria-hidden="true"></span><?php echo html_output($file); ?></p>
											</div>
										</div>
									</div>
									<div class="row edit_files">
										<div class="col-sm-12">
											<div class="row edit_files_blocks">
												<div class="<?php echo ($global_level != 0) ? 'col-sm-6 col-md-3' : 'col-sm-12 col-md-12'; ?> column">
													<div class="file_data">
														<div class="row">
															<div class="col-sm-12">
																<h3><?php _e('File information', 'cftp_admin');?></h3>
																<input type="hidden" name="file[<?php echo $i; ?>][original]" value="<?php echo html_output($file_original); ?>" />
																<input type="hidden" name="file[<?php echo $i; ?>][file]" value="<?php echo html_output($file); ?>" />
		
																<div class="form-group">
																	<label><?php _e('Title', 'cftp_admin');?></label>
																	<input type="text" name="file[<?php echo $i; ?>][name]" value="<?php echo html_output($file_title); ?>" class="form-control file_title" placeholder="<?php _e('Enter here the required file title.', 'cftp_admin');?>" />
																</div>
																
																<div class="form-group">
																	<label><?php _e('Description', 'cftp_admin');?></label>
																	<textarea name="file[<?php echo $i; ?>][description]" class="form-control" placeholder="<?php _e('Optionally, enter here a description for the file.', 'cftp_admin');?>"><?php echo (isset($description)) ? html_output($description) : ''; ?></textarea>
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
																* Only show the expiration options if the current
																* uploader is a system user or client if clients_can_set_expiration_date is set.
																*/
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
																<label for="exp_checkbox_<?php echo $i; ?>">
																	<input type="checkbox" name="file[<?php echo $i; ?>][expires]" id="exp_checkbox_<?php echo $i; ?>" value="1" <?php if ($row['expiry_set']) { ?>checked="checked"<?php } ?> /> <?php _e('File expires', 'cftp_admin');?>
																</label>
															</div>
			
															<?php
																/** The following options are available to users only */
																if ($global_level != 0) {
															?>
			
																<div class="divider"></div>
					
																<h3><?php _e('Public downloading', 'cftp_admin');?></h3>
																
																<div class="checkbox">
																	<label for="pub_checkbox_<?php echo $i; ?>">
																		<input type="checkbox" id="pub_checkbox_<?php echo $i; ?>" name="file[<?php echo $i; ?>][public]" value="1" /> <?php _e('Allow public downloading of this file.', 'cftp_admin');?>
																	</label>
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
																				<option value="<?php echo html_output('c'.$client); ?>"><?php echo html_output($client_name); ?></option>
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
																<div class="list_mass_members">
																	<a href="#" class="btn btn-xs btn-primary add-all" data-type="assigns"><?php _e('Add all','cftp_admin'); ?></a>
																	<a href="#" class="btn btn-xs btn-primary remove-all" data-type="assigns"><?php _e('Remove all','cftp_admin'); ?></a>
																	<a href="#" class="btn btn-xs btn-danger copy-all" data-type="assigns"><?php _e('Copy selections to other files','cftp_admin'); ?></a>
																</div>
	
																<div class="divider"></div>
					
																<div class="checkbox">
																	<label for="hid_checkbox_<?php echo $i; ?>">
																		<input type="checkbox" id="hid_checkbox_<?php echo $i; ?>" name="file[<?php echo $i; ?>][hidden]" value="1" /> <?php _e('Upload hidden (will not send notifications)', 'cftp_admin');?>
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
																		echo generate_categories_options( $get_categories['arranged'], 0 );
																	?>
																</select>
																<div class="list_mass_members">
																	<a href="#" class="btn btn-xs btn-primary add-all" data-type="categories"><?php _e('Add all','cftp_admin'); ?></a>
																	<a href="#" class="btn btn-xs btn-primary remove-all" data-type="categories"><?php _e('Remove all','cftp_admin'); ?></a>
																	<a href="#" class="btn btn-xs btn-danger copy-all" data-type="categories"><?php _e('Copy selections to other files','cftp_admin'); ?></a>
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

			</div> <!-- container -->

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
				<button type="submit" name="submit" class="btn btn-wide btn-primary" id="upload-continue"><?php _e('Continue','cftp_admin'); ?></button>
			</div>
		</form>

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
		<h3><?php _e('Files not uploaded','cftp_admin'); ?></h3>
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

<script type="text/javascript">
	$(document).ready(function() {
		<?php
			if(!empty($uploaded_files)) {
		?>
				$('.chosen-select').chosen({
					no_results_text	: "<?php _e('No results where found.','cftp_admin'); ?>",
					width			: "98%",
					search_contains	: true
				});

				$('.date-container .date-field').datepicker({
					format			: 'dd-mm-yyyy',
					autoclose		: true,
					todayHighlight	: true
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
									$(this).attr('selected', 'selected');
								}
							});
						});
						$('select').trigger('chosen:updated');
					}

					return false;
				});
		
				// Autoclick the continue button
				//$('#upload-continue').click();
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

<?php
	include('footer.php');
?>