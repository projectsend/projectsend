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

$allowed_levels = array(9);
require_once('sys.includes.php');
$loggedin_id = $_SESSION['loggedin_id'];

$active_nav = 'files';
$cc_active_page = 'Guest Files';

$page_title = __('Guest Files','cftp_admin');

$current_level = get_current_user_level();
$form_action_url = 'guest-files.php';
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
<style media="screen">
	h4{
		font-weight: bold;
	}
	.closeOp {
		display: inline;
		color: #337ab7;
		font-weight: bold;
		cursor: pointer;
		border: none;
		background-color: transparent;
		padding-left: 12px;
	}
#blacklistUl {
	padding: 5px;
	border: solid 1px #eee;
	border-radius: 5px;
}
#blacklistUl li {
	list-style: none;
	background-color: #ccc;
	display: inline;
	padding: 3px 10px;
	border-radius: 5px;
	margin: 3px 3px;
}
.closeOp {
	display: inline;
	color: #337ab7;
	font-weight: bold;
	cursor: pointer;
	padding-left: 5px;
}
#blacklistInput {
	width: 200px;
	display: inline;
	margin-right: 10px;
}
#blacklistUl li.newclose {
	color: green;
	background-color: #fff;
	border: solid 1px;
}
</style>
<div id="main">
<!-- MAIN CONTENT -->
	<div id="content">

		<!-- Added by B) -------------------->
		<div class="container-fluid">
			<div class="row">
				<div class="col-md-12">
					<h2 class="page-title txt-color-blueDark"><?php echo $page_title; ?></h2>
						<!-- <a href="request-drop-off.php" class="btn btn-sm btn-primary right-btn">Request File(s)</a> -->

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
								/*echo system_message('error',$msg); */
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
<style media="screen">
	#blacklist{
		display: none;
	}
</style>
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
				  <!-- <a href="<?php //echo $grid_layout; ?>" class="cc-grid"><i class="fa fa-th" aria-hidden="true"></i></a> -->
                  <!-- <a href="<?php //echo $actual_link; ?>" class="cc-grid"><i class="fa fa-bars" aria-hidden="true"></i></a> -->
                </div>
              </div>
            </div>
          </div>

				<div class="col-md-12">
						<?php
						// $reqstmail = "SELECT email FROM tbl_users WHERE id = ".$loggedin_id;
						//
						// $reqst = $dbh->prepare($reqstmail);
						// $reqst->execute();
						// $rfile = $reqst->fetch();

						$req_by = "SELECT * FROM tbl_drop_off_request WHERE (from_id IS NULL) Order by requested_time DESC";
						// $req_by = "SELECT * FROM tbl_drop_off_request WHERE ( to_email ='".$rfile['email']."'  AND from_id IS NULL) Order by requested_time DESC";

						$req_by_files = $dbh->prepare($req_by);
						$req_by_files->execute();

						$rqcount = $req_by_files->rowCount();

						$count+=$rqcount;
						 ?>
						 <div class="clear"></div>
						 <div class="form_actions_count">
							 <p class="form_count_total">
							 <?php _e('Showing','cftp_admin'); ?>
							 : <span><?php echo $rqcount; ?>
							 <?php _e('files','cftp_admin'); ?>
							 </span></p>
						 </div>
						<table class=" cc-mail-listing-style table table-striped table-bordered table-hover dataTable no-footer" data-page-size="<?php echo FOOTABLE_PAGING_NUMBER; ?>">
							<thead>
								<tr>
									<th class="td_checkbox" data-sort-ignore="true">
									<label class="cc-chk-container">
											<input type="checkbox" name="select_all" id="select_all" value="0" />
											<span class="checkmark"></span> </label>
									</th>
									<th data-type="numeric" data-sort-initial="descending" data-hide="phone"><?php _e('From name','cftp_admin'); ?></th>
									<th data-hide="phone,tablet"><?php _e('Organization','cftp_admin'); ?></th>

									<th><?php _e('From email','cftp_admin'); ?></th>
									<th><?php _e('To email','cftp_admin'); ?></th>
									<th data-hide="phone,tablet"><?php _e('Comment','cftp_admin'); ?></th>
									<th><?php _e('Status','cftp_admin'); ?></th>
									<th><?php _e('Requested Time','cftp_admin'); ?></th>
									<!-- <th><?php // _e('Action','cftp_admin'); ?></th> -->
								</tr>
							</thead>
							<tbody>
								<?php
								if ($rqcount > 0) {
								$req_by_files->setFetchMode(PDO::FETCH_ASSOC);
									while( $row = $req_by_files->fetch() ) {
										$disabled_list='';
										if($row['status']== 1) {
											$disabled_list="disabled";
										}
									?>
								<tr>
										<td><label class="cc-chk-container">
												<input type="checkbox" name="files[]" value="<?php echo $row['id']; ?>" />
												<span class="checkmark"></span> </label></td>
										<td><?php echo $row['from_name']; ?></td>
										<td><?php echo $row['from_organization']; ?></td>
										<td><?php echo $row['from_email']; ?></td>
										<td><?php echo $row['to_email']; ?></td>
										<td><?php echo $row['to_note_request']; ?></td>
										<td class="<?php echo (!empty($row['hidden'])) ? 'file_status_hidden' : 'file_status_visible'; ?>">
											<?php

											$status_hidden	= __('Pending','cftp_admin');
											$hidden = $row['status'];
											$status_visible	= __('Uploaded','cftp_admin');

											$class			= ($hidden == 0) ? 'danger' : 'success';

											?>
											<span class="label label-<?php echo $class; ?>"> <?php echo ($hidden == 0) ? $status_hidden : $status_visible; ?> </span>
										</td>
										<td><?php echo $row['requested_time']; ?></td>
										<!-- <td>
											<a <?php  if($row['status'] != '1') { ?> href="dropoff.php?auth=<?php echo $row['auth_key']; ?>" <?php } ?> <?php if($row['status'] == '1') { echo ("disabled ='disabled'");} ?> class="btn btn-primary btn-sm"  id="<?php echo $row['id']; ?>" >
												<?php _e('Go','cftp_admin'); ?>
										</td> -->
								</tr>
								<?php
									}
								}
								?>
							</tbody>

						</table>
					</div>
          </form>
				</div>
				<div class="col-md-12">
					<?php
					ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);
						if (isset($_POST['submit'])){

									 $clearBL = "TRUNCATE TABLE tbl_blacklist";
		 								$cBL = $dbh->prepare($clearBL);
		 								$cBL->execute();
										if(!empty($_POST['blacklist'])){

										foreach ($_POST['blacklist'] as $mail) {
										$BL = "INSERT INTO tbl_blacklist (mail) VALUES ('".$mail."')";
										$insertBL = $dbh->prepare($BL);
										$insertBL->execute();

										}
										} else {
											$error ="Add an email";
										}
					}
					?>
					<br>
					<h4>Blacklist</h4>
					<br>
						<form id="blacklistForm" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
											<div class="form-group" >
												<ul id="blacklistUl" >
												</ul>
												<label for=""></label>
												<?php //echo($error);
												$blist = $dbh->prepare("SELECT * FROM tbl_blacklist");
												$blist->execute();
												$bl = $blist->fetchAll();
												 ?>
												<select id="blacklist" class="form-control" name="blacklist[]" multiple>
													<?php foreach ($bl as $blocked) { ?>
														<option value="<?php echo($blocked['mail']); ?>" selected><?php echo($blocked['mail']); ?></option>
												<?php	} ?>
												</select>
											</div>
											<div class="searchAdd form-group">
												<input class="form-control" id="blacklistInput" type="email" name="" value="" ><button type="button" class="btn addEmail" name="addmail">Add</button>
											</div>
											<button type="submit" name="submit" class="btn btn-primary">Update</button>
										</form>

				</div>
      </div>
    </div>
  </div>
</div>



</div>
<script type="text/javascript">
	function isEmail(email) {
    var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
    return regex.test(email);
	}
	$(document).ready(function() {
		$("#blacklist > option").each(function() {
			var addEmail = $(this).val();
			var newLi = $('<li>'+addEmail+'<span class="closeOp" b-email="'+addEmail+'" > x </span></li>');
			$('#blacklistUl').append(newLi);
			});
		jQuery(".addEmail").click(function (){
		var bEmail = jQuery('#blacklistInput').val();
		var bExist= 0;
		$("#blacklist > option").each(function() {
			  if($(this).val()==bEmail){
				bExist++;
				}
			});
	// console.log(bExist);

	if(bEmail =='' || ! isEmail(bEmail)){
		alert("Enter a valid email");
	}
	else if(bExist == 0 ) {
		var newOption = $('<option selected value="'+bEmail+'">'+bEmail+'</option>');
		var newLi = $('<li class="newclose">'+bEmail+'<span class="closeOp" b-email="'+bEmail+'" > x </span></li>');
	 	$('#blacklist').append(newOption);
		$('#blacklistUl').append(newLi);
		jQuery('#blacklistInput').val("");
	}else {
	alert("Email already Exist");
	jQuery('#blacklistInput').val("");
	}
	});


$(document).on('click', 'span.closeOp', function () {
		var rmEmail = $(this).attr("b-email");
		$(this).closest('li').remove();
		var bEmail = jQuery('#blacklistInput').val();
		$("#blacklist > option").each(function() {
		if($(this).val()== rmEmail){
		$(this).remove();
		}
	});
	});
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
