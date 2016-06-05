	<?php
/**
 * Allows to hide, show or delete the files assigend to the
 * selected client.
 *
 * @package ProjectSend
 */
$footable = 1;
$allowed_levels = array(9,8,7,0);
require_once('sys.includes.php');

$active_nav = 'files';

$page_title = __('Manage files','cftp_admin');

$current_level = get_current_user_level();

/**
 * Used to distinguish the current page results.
 * Global means all files.
 * Client or group is only when looking into files
 * assigned to any of them.
 */
$results_type = 'global';

/**
 * The client's id is passed on the URI.
 * Then get_client_by_id() gets all the other account values.
 */
if (isset($_GET['client_id'])) {
	$this_id = mysql_real_escape_string($_GET['client_id']);
	$this_client = get_client_by_id($this_id);
	/** Add the name of the client to the page's title. */
	if(!empty($this_client)) {
		$page_title .= ' '.__('for client','cftp_admin').' '.html_entity_decode($this_client['name']);
		$search_on = 'client_id';
		$name_for_actions = $this_client['username'];
		$results_type = 'client';
	}
}

/**
 * The group's id is passed on the URI also.
 */
if (isset($_GET['group_id'])) {
	$this_id = mysql_real_escape_string($_GET['group_id']);
	$sql_name = $database->query("SELECT name from tbl_groups WHERE id='$this_id'");
	if (mysql_num_rows($sql_name) > 0) {
		while($row_group = mysql_fetch_array($sql_name)) {
			$group_name = $row_group["name"];
		}
		/** Add the name of the client to the page's title. */
		if(!empty($group_name)) {
			$page_title .= ' '.__('for group','cftp_admin').' '.html_entity_decode($group_name);
			$search_on = 'group_id';
			$name_for_actions = html_entity_decode($group_name);
			$results_type = 'group';
		}
	}
}

include('header.php');
?>

<script type="text/javascript">
	$(document).ready(function() {
		$("#do_action").click(function() {
			var checks = $("td>input:checkbox").serializeArray(); 
			if (checks.length == 0) { 
				alert('<?php _e('Please select at least one file to proceed.','cftp_admin'); ?>');
				return false; 
			} 
			else {
				var action = $('#files_actions').val();
				if (action == 'delete') {
					var msg_1 = '<?php _e("You are about to delete",'cftp_admin'); ?>';
					var msg_2 = '<?php _e("files permanently and for every client/group. Are you sure you want to continue?",'cftp_admin'); ?>';
					if (confirm(msg_1+' '+checks.length+' '+msg_2)) {
						return true;
					} else {
						return false;
					}
				}
				else if (action == 'unassign') {
					var msg_1 = '<?php _e("You are about to unassign",'cftp_admin'); ?>';
					var msg_2 = '<?php _e("files from this account. Are you sure you want to continue?",'cftp_admin'); ?>';
					if (confirm(msg_1+' '+checks.length+' '+msg_2)) {
						return true;
					} else {
						return false;
					}
				}
			}
		});
		
		<?php
			if ($results_type != 'client') {
		?>
				$(".downloaders").click(function() {
					$(document).psendmodal();
					$('.modal_content').html('<p class="loading-img">'+
												'<img src="<?php echo BASE_URI; ?>img/ajax-loader.gif" alt="Loading" /></p>'+
												'<p class="lead text-center text-info"><?php _e('Please wait while the system gets the required information.','cftp_admin'); ?></p>'
											);
					
					var file_name = $(this).attr('title');
					var file_id = $(this).attr('rel');
					$.get('<?php echo BASE_URI; ?>process.php', { do:"get_downloaders", sys_user:"<?php echo $global_id; ?>", file_id:file_id },
						function(data) {
							$('.modal_content').html('<h4><?php _e('Downloaders of file:','cftp_admin'); ?> <strong>'+file_name+'</strong></h4>');
							$('.modal_content').append('<ul class="downloaders_list"></ul>');
							var obj = $.parseJSON(data);
							for (i = 0; i < obj.length; i++) {
								$('.modal_content .downloaders_list').append('<li><img src="<?php echo BASE_URI; ?>img/downloader-' + obj[i].type + '.png" alt="" /><div class="downloader_count">' +  obj[i].count + ' <?php _e('times','cftp_admin'); ?></div><p class="downloader_name">' + obj[i].name + '</p><p class="downloader_email">' +  obj[i].email + '</p></li>');
							}
						}
					);					
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
				return '<strong><?php _e('Click to select','cftp_admin'); ?></strong><textarea class="input-large public_link_copy" rows="4"><?php echo BASE_URI; ?>download.php?id=' + id + '&token=' + token + '</textarea><small><?php _e('Send this URL to someone to download the file without registering or logging in.','cftp_admin'); ?></small><div class="close-popover"><button type="button" id="example" class="btn btn-inverse btn-small"><?php _e('Close','cftp_admin'); ?></button></div>';
			}
		});

		$(".close-popover button").live('click', function(e) {
			var popped = $(this).closest('td').find('.public_link');
			popped.popover('hide');
		});

		$(".public_link_copy").live('click', function(e) {
			$(this).select();
			$(this).mouseup(function() {
				$(this).unbind("mouseup");
				return false;
			});
		});
	});
</script>

<div id="main">

	<h2><?php echo $page_title; ?></h2>

	<?php
		/**
		 * Apply the corresponding action to the selected files.
		 */
		if(isset($_POST['do_action'])) {
			/** Continue only if 1 or more files were selected. */
			if(!empty($_POST['files'])) {
				$selected_files = array_map('intval',array_unique($_POST['files']));
				$files_to_get = implode(',',$selected_files);
	            
				/**
				 * Make a list of files to avoid individual queries.
				 * First, get all the different files under this account.
				 */
				$sql_distinct_files = $database->query("SELECT file_id FROM tbl_files_relations WHERE id IN ($files_to_get)");
				
				while($data_file_relations = mysql_fetch_array($sql_distinct_files)) {
					$all_files_relations[] = $data_file_relations['file_id']; 
					$files_to_get = implode(',',$all_files_relations);
				}
                
				/**
				 * Then get the files names to add to the log action.
				 */
				$sql_file = $database->query("SELECT id, filename FROM tbl_files WHERE id IN ($files_to_get)");
				while($data_file = mysql_fetch_array($sql_file)) {
					$all_files[$data_file['id']] = $data_file['filename'];
				}
				
				switch($_POST['files_actions']) {
					case 'hide':
						/**
						 * Changes the value on the "hidden" column value on the database.
						 * This files are not shown on the client's file list. They are
						 * also not counted on the home.php files count when the logged in
						 * account is the client.
						 */
						foreach ($selected_files as $work_file) {
							$this_file = new FilesActions();
							$hide_file = $this_file->change_files_hide_status($work_file,'1');
						}
						$msg = __('The selected files were marked as hidden.','cftp_admin');
						echo system_message('ok',$msg);
						$log_action_number = 21;
						break;

					case 'show':
						/**
						 * Reverse of the previous action. Setting the value to 0 means
						 * that the file is visible.
						 */
						foreach ($selected_files as $work_file) {
							$this_file = new FilesActions();
							$show_file = $this_file->change_files_hide_status($work_file,'0');
						}
						$msg = __('The selected files were marked as visible.','cftp_admin');
						echo system_message('ok',$msg);
						$log_action_number = 22;
						break;

					case 'unassign':
						/**
						 * Remove the file from this client's account only.
						 */
						foreach ($selected_files as $work_file) {
							$this_file = new FilesActions();
							$unassign_file = $this_file->unassign_file($work_file);
						}
						$msg = __('The selected files were unassigned from this client.','cftp_admin');
						echo system_message('ok',$msg);
						if ($search_on == 'group_id') {
							$log_action_number = 11;
						}
						elseif ($search_on == 'client_id') {
							$log_action_number = 10;
						}
						break;

					case 'delete':
						foreach ($selected_files as $work_file) {
							$this_file = new FilesActions();
							$delete_file = $this_file->delete_files($work_file);
						}
						$msg = __('The selected files were deleted.','cftp_admin');
						echo system_message('ok',$msg);
						$log_action_number = 12;
						break;
				}

				/** Record the action log */
				foreach ($all_files as $work_file_id => $work_file) {
					$new_log_action = new LogActions();
					$log_action_args = array(
											'action' => $log_action_number,
											'owner_id' => $global_id,
											'affected_file' => $work_file_id,
											'affected_file_name' => $work_file
										);
					if (!empty($name_for_actions)) {
						$log_action_args['affected_account_name'] = $name_for_actions;
						$log_action_args['get_user_real_name'] = true;
					}
					$new_record_action = $new_log_action->log_action_save($log_action_args);
				}
			}
			else {
				$msg = __('Please select at least one file.','cftp_admin');
				echo system_message('error',$msg);
			}
		}
		
		/**
		 * Global form action
		 */
		$form_action_url = 'manage-files.php';

		$database->MySQLDB();

		if (isset($search_on)) {
			$cq = "SELECT * FROM tbl_files_relations WHERE $search_on = '$this_id'";
			$form_action_url .= '?'.$search_on.'='.$this_id;

			/** Add the status filter */	
			if(isset($_POST['status']) && $_POST['status'] != 'all') {
				$set_and = true;
				$status_filter = mysql_real_escape_string($_POST['status']);
				$cq .= " AND hidden='$status_filter'";
				$no_results_error = 'filter';
			}

			/**
			 * Count the files assigned to this client. If there is none, show
			 * an error message.
			 */
			$sql = $database->query($cq);
	
			if (mysql_num_rows($sql) > 0) {
				/**
				 * Get the IDs of files that match the previous query.
				 */
				while($row_files = mysql_fetch_array($sql)) {
					$files_ids[] = $row_files['file_id'];
					$gotten_files = implode(',',$files_ids);
				}
			}
			else {
				$count = 0;
				$no_results_error = 'filter';
			}
		}
			
		/**
		 * Get the files
		 */
		$fq = "SELECT * from tbl_files";

		if ( isset($search_on) && !empty($gotten_files) ) {
			$conditions[] = "id IN ($gotten_files)";
		}

		/** Add the search terms */	
		if(isset($_POST['search']) && !empty($_POST['search'])) {
			$search_terms = mysql_real_escape_string($_POST['search']);
			$conditions[] = "(filename LIKE '%$search_terms%' OR description LIKE '%$search_terms%')";
			$no_results_error = 'search';
		}

		/**
		 * If the user is an uploader, or a client is editing his files
		 * only show files uploaded by that account.
		*/
		$current_level = get_current_user_level();
		if ($current_level == '7' || $current_level == '0') {
			$conditions[] = "uploader = '$global_user'";
			$no_results_error = 'account_level';
		}

		/**
		 * Build the final query
		 */
		if ( !empty( $conditions ) ) {
			foreach ( $conditions as $index => $condition ) {
				$fq .= ( $index == 0 ) ? ' WHERE ' : ' AND ';
				$fq .= $condition;
			}
		}

		/** Debug query */
		//echo $fq;
		//print_r( $conditions );

		$sql_files = $database->query($fq);
		$count = mysql_num_rows($sql_files);
	?>
		<div class="form_actions_left">
			<div class="form_actions_limit_results">
				<form action="<?php echo html_output($form_action_url); ?>" name="files_search" method="post" class="form-inline">
					<input type="text" name="search" id="search" value="<?php if(isset($_POST['search']) && !empty($search_terms)) { echo html_output($search_terms); } ?>" class="txtfield form_actions_search_box" />
					<button type="submit" id="btn_proceed_search" class="btn btn-small"><?php _e('Search','cftp_admin'); ?></button>
				</form>

				<?php
					/** Filters are not available for clients */
					if($current_level != '0' && $results_type != 'global') {
				?>
						<form action="<?php echo html_output($form_action_url); ?>" name="files_filters" method="post" class="form-inline">
							<select name="status" id="status" class="txtfield">
								<option value="all"><?php _e('All statuses','cftp_admin'); ?></option>
								<option value="1"><?php _e('Hidden','cftp_admin'); ?></option>
								<option value="0"><?php _e('Visible','cftp_admin'); ?></option>
							</select>
							<button type="submit" id="btn_proceed_filter_clients" class="btn btn-small"><?php _e('Filter','cftp_admin'); ?></button>
						</form>
				<?php
					}
				?>
			</div>
		</div>


		<form action="<?php echo html_output($form_action_url); ?>" name="files_list" method="post" class="form-inline">
			<?php
				/** Actions are not available for clients */
				if($current_level != '0') {
			?>
					<div class="form_actions_right">
						<div class="form_actions">
							<div class="form_actions_submit">
								<label><?php _e('Selected files actions','cftp_admin'); ?>:</label>
								<select name="files_actions" id="files_actions" class="txtfield">
									<?php
										/** Options only available when viewing a client/group files list */
										if (isset($search_on)) {
									?>
											<option value="hide"><?php _e('Hide','cftp_admin'); ?></option>
											<option value="show"><?php _e('Show','cftp_admin'); ?></option>
											<option value="unassign"><?php _e('Unassign','cftp_admin'); ?></option>
									<?php
										}
									?>
									<option value="delete"><?php _e('Delete','cftp_admin'); ?></option>
								</select>
								<button type="submit" name="do_action" id="do_action" class="btn btn-small"><?php _e('Proceed','cftp_admin'); ?></button>
							</div>
						</div>
					</div>
			<?php
				}
			?>

			<div class="clear"></div>
	
			<div class="form_actions_count">
				<p class="form_count_total"><?php _e('Showing','cftp_admin'); ?>: <span><?php echo $count; ?> <?php _e('files','cftp_admin'); ?></span></p>
			</div>
	
			<div class="clear"></div>

			<?php
				if (!$count) {
					if (isset($no_results_error)) {
						switch ($no_results_error) {
							case 'search':
								$no_results_message = __('Your search keywords returned no results.','cftp_admin');;
								break;
							case 'filter':
								$no_results_message = __('The filters you selected returned no results.','cftp_admin');;
								break;
							case 'none_assigned':
								$no_results_message = __('There are no files assigned to this client.','cftp_admin');;
								break;
							case 'account_level':
								$no_results_message = __('You have not uploaded any files for this account.','cftp_admin');;
								break;
						}
					}
					else {
						$no_results_message = __('There are no files for this client.','cftp_admin');;
					}
					echo system_message('error',$no_results_message);
				}
			?>

			<table id="files_list" class="footable" data-page-size="<?php echo FOOTABLE_PAGING_NUMBER; ?>">
				<thead>
					<tr>
						<?php
							/** Actions are not available for clients */
							if($current_level != '0') {
						?>
								<th class="td_checkbox" data-sort-ignore="true">
									<input type="checkbox" name="select_all" id="select_all" value="0" />
								</th>
						<?php
							}
						?>
						<th data-type="numeric" data-sort-initial="descending" data-hide="phone"><?php _e('Date','cftp_admin'); ?></th>
						<th data-hide="phone,tablet"><?php _e('Ext.','cftp_admin'); ?></th>
						<th><?php _e('Title','cftp_admin'); ?></th>
						<th><?php _e('Size','cftp_admin'); ?></th>
						<?php
							if($current_level != '0') {
						?>
								<th data-hide="phone,tablet"><?php _e('Uploader','cftp_admin'); ?></th>
								<th data-hide="phone"><?php _e('Public','cftp_admin'); ?></th>
								<th data-hide="phone"><?php _e('Expiry','cftp_admin'); ?></th>
						<?php
							}

							/**
							 * These columns are only available when filtering by client or group.
							 */
							if (isset($search_on)) {
						?>
								<th data-hide="phone"><?php _e('Status','cftp_admin'); ?></th>
								<th data-hide="phone"><?php _e('Download count','cftp_admin'); ?></th>
						<?php
							}
							else {
								if($current_level != '0') {
						?>
									<th data-hide="phone"><?php _e('Total downloads','cftp_admin'); ?></th>
						<?php
								}
							}
						?>
						<th data-hide="phone" data-sort-ignore="true"><?php _e('Actions','cftp_admin'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
						if ($count > 0) {
							while($row = mysql_fetch_array($sql_files)) {
								$file_id = $row['id'];
								/**
								 * Construct the complete file URI to use on the download button.
								 */
								$this_file_absolute = UPLOADED_FILES_FOLDER.$row['url'];
								$this_file_uri = BASE_URI.UPLOADED_FILES_URL.$row['url'];
								
								/**
								 * Download count and visibility status are only available when
								 * filtering by client or group.
								 */
								$query_this_file = "SELECT * FROM tbl_files_relations WHERE file_id='".$row['id']."'";
								if (isset($search_on)) {
									$query_this_file .= " AND $search_on = $this_id";
									/**
									 * Count how many times this file has been downloaded
									 * Here, the download count is specific per user.
									 */
									switch ($results_type) {
										case 'client':
												$download_count_sql	= $database->query("SELECT user_id, file_id FROM tbl_downloads WHERE file_id = '".$row['id']."' AND user_id = '".$this_id."'");
												$download_count		= mysql_num_rows($download_count_sql);
											break;

										case 'group':
												$download_count_sql	= $database->query("SELECT file_id FROM tbl_downloads WHERE file_id = '".$row['id']."'");
												$download_count		= mysql_num_rows($download_count_sql);
											break;
									}
								}
								else {
									/**
									 * Count how many times this file has been downloaded
									 * Here, the download count is general.
									 */
									$download_count_sql	= $database->query("SELECT file_id FROM tbl_downloads WHERE file_id = '".$row['id']."'");
									$download_count		= mysql_num_rows($download_count_sql);
								}

								$sql_this_file = $database->query($query_this_file);
								
								$file_id = 0;
								$data_file = '1';

								while($data_file = mysql_fetch_array($sql_this_file)) {
									$file_id = $data_file['id'];
									$hidden = $data_file['hidden'];
								}
								$date = date(TIMEFORMAT_USE,strtotime($row['timestamp']));
					?>
								<tr>
									<?php
										/** Actions are not available for clients */
										if($current_level != '0') {
									?>
											<td><input type="checkbox" name="files[]" value="<?php echo filter_var($file_id,FILTER_VALIDATE_INT); ?>" /></td>
									<?php
										}
									?>
									<td data-value="<?php echo strtotime($row['timestamp']); ?>">
										<?php echo $date; ?>
									</td>
									<td>
										<?php
											$pathinfo = pathinfo($row['url']);
											$extension = strtolower($pathinfo['extension']);
											echo html_output($extension);
										?>
									</td>
									<td class="file_name">
										<?php
											/**
											 * Clients cannot download from here.
											 */
											if($current_level != '0') {
												$download_link = BASE_URI.'process.php?do=download&amp;client='.$global_user.'&amp;id='.$row['id'].'&amp;n=1';
										?>
												<a href="<?php echo $download_link; ?>" target="_blank">
													<?php echo html_output($row['filename']); ?>
												</a>
										<?php
											}
											else {
												echo html_output($row['filename']);
											}
										?>
									</td>
									<td data-value="<?php echo get_real_size($this_file_absolute); ?>"><?php $this_file_size = get_real_size($this_file_absolute); echo html_output(format_file_size($this_file_size)); ?></td>
									<?php
										if($current_level != '0') {
									?>
											<td><?php echo html_output($row['uploader']); ?></td>
											<td>
												<?php
													if ($row['public_allow'] == '1') {
												?>
														<a href="javascript:void(0);" class="btn-primary btn btn-small public_link" data-id="<?php echo $row['id']; ?>" data-token="<?php echo html_output($row['public_token']); ?>" data-placement="top" data-toggle="popover" data-original-title="<?php _e('Public URL','cftp_admin'); ?>">
												<?php
													}
													else {
												?>
														<a href="javascript:void(0);" class="disabled btn btn-small" rel="" title="">
												<?php
													}
															$status_public	= __('Public','cftp_admin');
															$status_private	= __('Private','cftp_admin');
															echo ($row['public_allow'] === '1') ? $status_public : $status_private;
												?>
												</a>
											</td>
											<td>
												<?php
													if ($row['expires'] == '0') {
												?>
														<a href="javascript:void(0);" class="btn btn-success disabled btn-small">
															<?php _e('Does not expire','cftp_admin'); ?>
														</a>
												<?php
													}
													else {
														if (time() > strtotime($row['expiry_date'])) {
												?>
															<a href="javascript:void(0);" class="btn btn-danger disabled btn-small" rel="" title="">
																<?php _e('Expired on','cftp_admin'); ?> <?php echo date(TIMEFORMAT_USE,strtotime($row['expiry_date'])); ?>
															</a>
												<?php
														}
														else {
												?>
															<a href="javascript:void(0);" class="btn btn-info disabled btn-small" rel="" title="">
																<?php _e('Expires on','cftp_admin'); ?> <?php echo date(TIMEFORMAT_USE,strtotime($row['expiry_date'])); ?>
															</a>
												<?php
														}
													}
												?>
											</td>
									<?php
										}

										/**
										 * These columns are only available when filtering by client or group.
										 */
										if (isset($search_on)) {
									?>
											<td class="<?php echo ($hidden === '1') ? 'file_status_hidden' : 'file_status_visible'; ?>">
												<?php
													$status_hidden = __('Hidden','cftp_admin');
													$status_visible = __('Visible','cftp_admin');
													echo ($hidden === '1') ? $status_hidden : $status_visible;
												?>
											</td>
											<td>
												<?php
													switch ($results_type) {
														case 'client':
															echo $download_count; ?> <?php _e('times','cftp_admin');
															break;
				
														case 'group':
												?>
															<a href="javascript:void(0);" class="<?php if ($download_count > 0) { echo 'downloaders btn-primary'; } else { echo 'disabled'; } ?> btn btn-small" rel="<?php echo $row["id"]; ?>" title="<?php echo html_output($row['filename']); ?>">
																<?php echo $download_count; ?> <?php _e('downloads','cftp_admin'); ?>
															</a>
												<?php
														break;
													}
												?>
											</td>
									<?php
										}
										else {
											if($current_level != '0') {
									?>
												<td>
													<a href="javascript:void(0);" class="<?php if ($download_count > 0) { echo 'downloaders btn-primary'; } else { echo 'disabled'; } ?> btn btn-small" rel="<?php echo $row["id"]; ?>" title="<?php echo html_output($row['filename']); ?>">
														<?php echo $download_count; ?> <?php _e('downloads','cftp_admin'); ?>
													</a>
												</td>
									<?php
											}
										}
									?>
									<td>
										<a href="edit-file.php?file_id=<?php echo $row["id"]; ?>" class="btn btn-primary btn-small">
											<?php _e('Edit','cftp_admin'); ?>
										</a>
									</td>
								</tr>
					<?php
							}
						}
					?>
				</tbody>
			</table>

			<div class="pagination pagination-centered hide-if-no-paging"></div>

		</form>

		<?php
			if($current_level != '0') {
		?>
				<div class="message message_info"><?php _e('Please note that downloading a file from here will not add to the download count.','cftp_admin'); ?></div>
		<?php
			}
		?>

	<?php
		$database->Close();
	?>

	</div>

</div>

<?php include('footer.php'); ?>
