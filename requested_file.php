<?php
/**
 * Allows to hide, show or delete the files assigend to the
 * selected client.
 *
 * @package ProjectSend 
 */

$load_scripts	= array(
						'footable',
					); 

$allowed_levels = array(9,8,7,0);
require_once('sys.includes.php');
$loggedin_id = $_SESSION['loggedin_id'];

$active_nav = 'files';
$cc_active_page = 'Requested Files';

$page_title = __('Requested Files','cftp_admin');

$current_level = get_current_user_level();
$form_action_url = 'requested_file.php';
/*
 * Get the total downloads count here. The results are then
 * referenced on the results table.
 */
$downloads_information = generate_downloads_count();

/**
 * The client's id is passed on the URI.
 * Then get_client_by_id() gets all the other account values.
 */

include('header.php');
$grid_layout= SITE_URI.'requested_file.php?view=grid';/*echo $actual_link; */
$actual_link = SITE_URI.'requested_file.php';
?>

<div id="main"> 
<!-- MAIN CONTENT -->
	<div id="content"> 

		<!-- Added by B) -------------------->
		<div class="container-fluid">
			<div class="row">
				<div class="col-md-12">
					<h2 class="page-title txt-color-blueDark"><?php echo $page_title; ?></h2>
						<a href="request-drop-off.php" class="btn btn-sm btn-primary right-btn">Request a File</a>

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
				/*$sql_distinct_files = $dbh->prepare("SELECT file_id FROM " . TABLE_FILES_RELATIONS . " WHERE FIND_IN_SET(id, :files)");
				$sql_distinct_files->bindParam(':files', $files_to_get);
				$sql_distinct_files->execute();
				$sql_distinct_files->setFetchMode(PDO::FETCH_ASSOC);
				
				while( $data_file_relations = $sql_distinct_files->fetch() ) {
					$all_files_relations[] = $data_file_relations['file_id']; 
					$files_to_get = implode(',',$all_files_relations);
				}*/
				
				/**
				 * Then get the files names to add to the log action.
				 */
				$sql_file = $dbh->prepare("SELECT id, filename FROM " . TABLE_FILES . " WHERE FIND_IN_SET(id, :files)");
				$sql_file->bindParam(':files', $files_to_get);
				$sql_file->execute();
				$sql_file->setFetchMode(PDO::FETCH_ASSOC);

				while( $data_file = $sql_file->fetch() ) {
					$all_files[$data_file['id']] = $data_file['filename'];
				}
				
				switch($_POST['files_actions']) {


					case 'delete':
						$delete_results	= array(
												'ok'		=> 0,
												'errors'	=> 0,
											);
						$success_count = 0;
						$failed_count = 0;
						foreach ($selected_files as $index => $file_id) {
							//echo $file_id;exit;
							$sql =$dbh->prepare("DELETE FROM tbl_drop_off_request WHERE id = :file_id");
							$sql->bindParam(':file_id', $file_id);
							if($sql->execute()){
								$msg_succes = __('The selected request were deleted.','cftp_admin');
								$success_count++;
								/*echo system_message('ok',$msg); */
								$log_action_number = 12;
							}else{
								$msg_failed = __('Some request could not be deleted.','cftp_admin');
								$failed_count++;
								/* echo system_message('error',$msg); */
							}

						}
						if($success_count>0) {
							echo system_message('ok',$msg_succes);
						}
						if($failed_count>0) {
							echo system_message('ok',$msg_failed);
						}


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


?>
         <form action="<?php echo html_output($form_action_url); ?>" name="files_list" method="post" class="form-inline">
          <div class="form-inline">
            <div class="form_actions_right">
              <div class="form_actions">
                <div class="form_actions_submit">
                  <div class="form-group group_float">
                    <label class="control-label hidden-xs hidden-sm"><i class="glyphicon glyphicon-check"></i>
                      <?php _e('Selected files actions','cftp_admin'); ?>
                      :</label>
                    <select name="files_actions" id="files_actions" class="txtfield form-control">
                      <option value="delete">
                      <?php _e('Delete','cftp_admin'); ?>
                      </option>
                    </select>
                  </div>
                  <button type="submit" name="do_action" id="do_action" class="btn btn-sm btn-default">
                  <?php _e('Proceed','cftp_admin'); ?>
                  </button>
				  <!-- Added by rj to view the layout of of requested item listing -->
				  <a href="<?php echo $grid_layout; ?>" class="cc-grid"><i class="fa fa-th" aria-hidden="true"></i></a> 
                  <a href="<?php echo $actual_link; ?>" class="cc-grid"><i class="fa fa-bars" aria-hidden="true"></i></a>
                </div>
              </div>
            </div>
          </div>
						<?php
						/** Debug query */

						$q_sent_file = "SELECT * FROM tbl_drop_off_request WHERE from_id = ".$loggedin_id." Order by requested_time DESC";

						$sql_files = $dbh->prepare($q_sent_file);
						$sql_files->execute();

						$count = $sql_files->rowCount();
						?>
							<div class="clear"></div>
							<div class="form_actions_count">
								<p class="form_count_total">
								<?php _e('Showing','cftp_admin'); ?>
								: <span><?php echo $count; ?>
								<?php _e('files','cftp_admin'); ?>
								</span></p>
							</div>
							<div class="clear"></div>
            				<?php if(isset($_GET['view']) && $_GET['view']==='grid')							
							{
								if($count>0)
								{ 
									$sql_files->setFetchMode(PDO::FETCH_ASSOC);	?>
									<div class="row">
									<?php 								
									while( $row = $sql_files->fetch() ) 
									{ 
									?>
										<div class="col-md-4">
											<div class="grid_box">
												<label class="cc-chk-container cc-chk-container-grid-box">
												<input type="checkbox" name="files[]" value="<?php echo $row['id']; ?>" />
												<span class="checkmark"></span></label>
												<p><span class="grid_box_span"><?php _e('To name','cftp_admin'); ?>:</span> <?php echo $row['to_name']; ?></p>
												<p><span class="grid_box_span"><?php _e('Subject','cftp_admin'); ?>:</span> <?php echo $row['to_subject_request']; ?></p>
												<p><span class="grid_box_span"><?php _e('Note','cftp_admin'); ?>:</span> <span class="tooltip"> <?php echo $row['to_note_request']; ?> </span></span></p>
												<p><span class="grid_box_span"><?php _e('email','cftp_admin'); ?>:</span> <?php echo $row['to_email']; ?></p>
												<?php 
												$disabled_grid = '';
												if($row['status']===1)
												{
													$disabled_grid="disabled";
												?>
													<p><span class="grid_box_span"><?php _e('Status','cftp_admin'); ?>: Uploaded</span></p>
												<?php 
												} 
												else 
												{ ?>
													<p><span class="grid_box_span"><?php _e('Status','cftp_admin'); ?>: Pending</span></p>
												<?php 
												} ?>
												<p><span class="grid_box_span"><?php _e('Requested Time','cftp_admin'); ?>:</span> <?php echo $row['requested_time']; ?></p>
												<p><div class="btn btn-primary btn-sm resend_grid_box <?php echo !empty($disabled_grid)?$disabled_grid:'resend_it'; ?>"  id="<?php echo $row['id']; ?>" ><?php _e('Resend','cftp_admin'); ?>
												</div></p>
											</div>
										</div>
									<?php 
									} ?>
									</div>
							<?php } 
							} 							
							else 
							{ ?>
            			<section id="no-more-tables">
              				<table id="files_list" class=" cc-mail-listing-style table table-striped table-bordered table-hover dataTable no-footer" data-page-size="<?php echo FOOTABLE_PAGING_NUMBER; ?>">
                			<thead>
                  			<tr>
                    <th class="td_checkbox" data-sort-ignore="true"> 
		    <label class="cc-chk-container">
                        <input type="checkbox" name="select_all" id="select_all" value="0" />
                        <span class="checkmark"></span> </label>
                    </th>
                    <th data-type="numeric" data-sort-initial="descending" data-hide="phone"><?php _e('To name','cftp_admin'); ?></th>
                    <th data-hide="phone,tablet"><?php _e('Subject.','cftp_admin'); ?></th>
                    <th data-hide="phone,tablet"><?php _e('Organization','cftp_admin'); ?></th>
					
                    <th><?php _e('email','cftp_admin'); ?></th>
					<th><?php _e('Note','cftp_admin'); ?></th>
                    <th><?php _e('Status','cftp_admin'); ?></th>
                    <th><?php _e('Requested Time','cftp_admin'); ?></th>
                    <th><?php _e('Action','cftp_admin'); ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php
									if ($count > 0) {
									$sql_files->setFetchMode(PDO::FETCH_ASSOC);
										while( $row = $sql_files->fetch() ) {
											$disabled_list='';
											if($row['status']== 1) {
												$disabled_list="disabled";
											}
										?>
                  <tr>
                    <td><label class="cc-chk-container">
                        <input type="checkbox" name="files[]" value="<?php echo $row['id']; ?>" />
                        <span class="checkmark"></span> </label></td>
                    <td><?php echo $row['to_name']; ?></td>
                    <td class="file_name"><?php echo $row['to_subject_request']; ?></td>
                    <td><?php echo $row['from_organization']; ?></td>
                    <td><?php echo $row['to_email']; ?></td>
					<td><?php echo $row['to_note_request']; ?></td>
                    <td class="<?php echo (!empty($row['hidden'])) ? 'file_status_hidden' : 'file_status_visible'; ?>"><?php

										$status_hidden	= __('Pending','cftp_admin');
										$hidden = $row['status'];
										$status_visible	= __('Uploaded','cftp_admin');

										$class			= ($hidden == 0) ? 'danger' : 'success';

									?>
        <span class="label label-<?php echo $class; ?>"> <?php echo ($hidden == 0) ? $status_hidden : $status_visible; ?> </span></td>
                    <td><?php echo $row['requested_time']; ?></td>
                    <td><div class="btn btn-primary btn-sm <?php echo !empty($disabled_list)?$disabled_list:'resend_it'; ?>"  id="<?php echo $row['id']; ?>" >
                        <?php _e('Resend','cftp_admin'); ?>
                      </div></td>
                  </tr>
                  <?php
										}
									}
									?>
                </tbody>
              </table>
            </section>
            <?php } ?>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
<script type="text/javascript">
	$(document).ready(function() {

		$(".resend_it").click(function(event) {
		var e_id = event.target.id;
		var postData = {  "e_id": e_id };
			$.ajax({
				type: "POST",
				url: "resend_requested_file.php",
				data: postData,
				traditional: true,
				success: function (data) {
					if(data='done'){
						alert('Request has been resend successfully!!')
						location.reload(); 
					}
				}
			});
		});
		$("#do_action").click(function() {
			var checks = $("td input:checkbox").serializeArray(); 
			if (checks.length == 0) { 
				alert('<?php _e('Please select at least one file to proceed.','cftp_admin'); ?>');
				return false; 
			} 
			else {
				var action = $('#files_actions').val();
				if (action == 'delete') {
					var msg_1 = '<?php _e("You are about to delete the request",'cftp_admin'); ?>';
					var msg_2 = '<?php _e("Are you sure you want to continue?",'cftp_admin'); ?>';
					if (confirm(msg_1+' '+checks.length+' '+msg_2)) {
						return true;
					} else {
						return false;
					}
				}
			}
		});
	});
</script>
<?php include('footer.php'); ?>


<style type="text/css">
/*-------------------- Responsive table by B) -----------------------*/
@media only screen and (max-width: 1200px) {
    #content {
        padding-top:30px;
    }
    
    /* Force table to not be like tables anymore */
    #no-more-tables table, 
    #no-more-tables thead, 
    #no-more-tables tbody, 
    #no-more-tables th, 
    #no-more-tables td, 
    #no-more-tables tr { 
        display: block; 
    }
 
    /* Hide table headers (but not display: none;, for accessibility) */
    #no-more-tables thead tr { 
        position: absolute;
        top: -9999px;
        left: -9999px;
    }
 
    #no-more-tables tr { border: 1px solid #ccc; }
 
    #no-more-tables td { 
        /* Behave  like a "row" */
        border: none;
        border-bottom: 1px solid #eee; 
        position: relative;
        padding-left: 50%; 
        white-space: normal;
        text-align:left;
    }
 
    #no-more-tables td:before { 
        /* Now like a table header */
        position: absolute;
        /* Top/left values mimic padding */
        top: 6px;
        left: 6px;
        width: 45%; 
        padding-right: 10px; 
        white-space: nowrap;
        text-align:left;
        font-weight: bold;
    }
 
    /*
    Label the data
    */

    
    td:nth-of-type(1):before { content: ""; }
    td:nth-of-type(2):before { content: "To Name"; }
    td:nth-of-type(3):before { content: "Subject"; }
    td:nth-of-type(4):before { content: "Organization"; }
    td:nth-of-type(5):before { content: "Email"; }
    td:nth-of-type(6):before { content: "Note"; }
	td:nth-of-type(7):before { content: "Status"; }
    td:nth-of-type(8):before { content: "Requested Time"; }
    td:nth-of-type(9):before { content: "Action"; }
}
/*-------------------- Responsive table End--------------------------*/
</style>
