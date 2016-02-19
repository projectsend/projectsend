<?php
/**
 * Show the list of activities logged.
 *
 * @package		ProjectSend
 * @subpackage	Log
 *
 */
$footable = 1;
$allowed_levels = array(9);
require_once('sys.includes.php');
$page_title = __('Recent activities log','cftp_admin');;

include('header.php');
?>

<script type="text/javascript">
	$(document).ready( function() {
		$("#do_action").click(function() {
			var checks = $("td>input:checkbox").serializeArray(); 
			var action = $('#log_actions').val();

			if (action == 'delete') {
				if (checks.length == 0) { 
					alert('<?php _e('Please select at least one activity to proceed.','cftp_admin'); ?>');
					return false; 
				}
				else {
					var msg_1 = '<?php _e("You are about to delete",'cftp_admin'); ?>';
					var msg_2 = '<?php _e("activities. Are you sure you want to continue?",'cftp_admin'); ?>';
					if (confirm(msg_1+' '+checks.length+' '+msg_2)) {
						return true;
					} else {
						return false;
					}
				}
			}
			if (action == 'clear') {
				var msg = '<?php _e("You are about to delete all activities from the log. Only those used for statistics will remain. Are you sure you want to continue?",'cftp_admin'); ?>';
				if (confirm(msg)) {
					return true;
				} else {
					return false;
				}
			}

			if (action == 'download') {
				$(document).psendmodal();
				$('.modal_content').html('<p class="loading-img">'+
											'<img src="<?php echo BASE_URI; ?>img/ajax-loader.gif" alt="Loading" /></p>'+
											'<p class="lead text-center text-info"><?php _e('Please wait while your download is prepared.','cftp_admin'); ?></p>'
										);
				$('.modal_content').append('<iframe src="<?php echo BASE_URI; ?>includes/actions.log.export.php?format=csv"></iframe>');
				return false;
			}
		});

	});
</script>

<div id="main">
	<h2><?php echo $page_title; ?></h2>

<?php

	/**
	 * Apply the corresponding action to the selected users.
	 */
	if(isset($_POST['log_actions']) && $_POST['log_actions'] != 'none') {
		/** Continue only if 1 or more users were selected. */
			switch($_POST['log_actions']) {
				case 'delete':

					$selected_actions = $_POST['activities'];
					$deleted_actions = 0;

					if(!empty($_POST['activities'])) {
						foreach ($selected_actions as $selected_action) {
							$del_sql = $database->query("DELETE FROM tbl_actions_log WHERE id='$selected_action'");
							$deleted_actions++;
						}
						
						if ($deleted_actions > 0) {
							$msg = __('The selected activities were deleted.','cftp_admin');
							echo system_message('ok',$msg);
						}
					}
					else {
						$msg = __('Please select at least one activity.','cftp_admin');
						echo system_message('error',$msg);
					}
				break;
				case 'clear':
					$keep = '5,8,9';
					$del_sql = $database->query("DELETE FROM tbl_actions_log WHERE NOT (action IN ($keep))");
					$msg = __('The log was cleared. Only data used for statistics remained. You can delete them manually if you want.','cftp_admin');
					echo system_message('ok',$msg);
				break;
			}
	}

	$database->MySQLDB();
	$cq = "SELECT * FROM tbl_actions_log";

	/** Add the search terms */	
	if(isset($_POST['search']) && !empty($_POST['search'])) {
		$search_terms = mysql_real_escape_string($_POST['search']);
		$cq .= " WHERE (owner_user LIKE '%$search_terms%' OR affected_file_name LIKE '%$search_terms%' OR affected_account_name LIKE '%$search_terms%')";
		$next_clause = ' AND';
		$no_results_error = 'search';
	}
	else {
		$next_clause = ' WHERE';
	}

	/** Add the activities filter */	
	if(isset($_POST['activity']) && $_POST['activity'] != 'all') {
		$status_filter = $_POST['activity'];
		$cq .= $next_clause. " action='$status_filter'";
		$no_results_error = 'filter';
	}

	$cq .= " ORDER BY id DESC";
	
	$sql = $database->query($cq);
	$count = mysql_num_rows($sql);
?>

	<div class="form_actions_left">
		<div class="form_actions_limit_results">
			<form action="actions-log.php" name="log_search" method="post" class="form-inline">
				<input type="text" name="search" id="search" value="<?php if(isset($_POST['search']) && !empty($_POST['search'])) { echo  htmlentities($_POST['search'],ENT_QUOTES,"utf8"); } ?>" class="txtfield form_actions_search_box" />
				<button type="submit" id="btn_proceed_search" class="btn btn-small"><?php _e('Search','cftp_admin'); ?></button>
			</form>

			<form action="actions-log.php" name="actions_filters" method="post" class="form-inline">
				<select name="activity" id="activity" class="txtfield" style="width:360px;">
					<option value="all"><?php _e('All activities','cftp_admin'); ?></option>
						<option value="0"><?php _e('ProjecSend has been installed','cftp_admin'); ?></option>
						<option value="1"><?php _e('Account logs in through the form','cftp_admin'); ?></option>
						<option value="24"><?php _e('Account logs in through cookies','cftp_admin'); ?></option>
						<option value="31"><?php _e('Account (user or client) logs out','cftp_admin'); ?></option>
						<option value="2"><?php _e('A user creates a new user account','cftp_admin'); ?></option>
						<option value="3"><?php _e('A user creates a new client account','cftp_admin'); ?></option>
						<option value="4"><?php _e('A client registers an account for himself','cftp_admin'); ?></option>
						<option value="5"><?php _e('A file is uploaded by an user','cftp_admin'); ?></option>
						<option value="6"><?php _e('A file is uploaded by a client','cftp_admin'); ?></option>
						<option value="7"><?php _e('A file is downloaded by a user (on "Client view" mode)','cftp_admin'); ?></option>
						<option value="8"><?php _e('A file is downloaded by a client','cftp_admin'); ?></option>
						<option value="9"><?php _e('A zip file was generated by a client','cftp_admin'); ?></option>
						<option value="10"><?php _e('A file has been unassigned from a client.','cftp_admin'); ?></option>
						<option value="11"><?php _e('A file has been unassigned from a group','cftp_admin'); ?></option>
						<option value="12"><?php _e('A file has been deleted','cftp_admin'); ?></option>
						<option value="13"><?php _e('A user was edited','cftp_admin'); ?></option>
						<option value="14"><?php _e('A client was edited','cftp_admin'); ?></option>
						<option value="15"><?php _e('A group was edited','cftp_admin'); ?></option>
						<option value="16"><?php _e('A user was deleted','cftp_admin'); ?></option>
						<option value="17"><?php _e('A client was deleted','cftp_admin'); ?></option>
						<option value="18"><?php _e('A group was deleted','cftp_admin'); ?></option>
						<option value="19"><?php _e('A client account was activated','cftp_admin'); ?></option>
						<option value="20"><?php _e('A client account was deactivated','cftp_admin'); ?></option>
						<option value="27"><?php _e('A user account was activated','cftp_admin'); ?></option>
						<option value="28"><?php _e('A user account was deactivated','cftp_admin'); ?></option>
						<option value="21"><?php _e('A file was marked as hidden','cftp_admin'); ?></option>
						<option value="22"><?php _e('A file was marked as visible','cftp_admin'); ?></option>
						<option value="23"><?php _e('A user creates a new group','cftp_admin'); ?></option>
						<option value="25"><?php _e('A file is assigned to a client','cftp_admin'); ?></option>
						<option value="26"><?php _e('A file is assigned to a group','cftp_admin'); ?></option>
						<option value="27"><?php _e('A user account was marked as active','cftp_admin'); ?></option>
						<option value="28"><?php _e('A user account was marked as inactive','cftp_admin'); ?></option>
						<option value="29"><?php _e('The logo on "Branding" was changed','cftp_admin'); ?></option>
						<option value="30"><?php _e('ProjectSend was updated','cftp_admin'); ?></option>
						<option value="32"><?php _e('A system user edited a file.','cftp_admin'); ?></option>
						<option value="33"><?php _e('A client edited a file.','cftp_admin'); ?></option>
				</select>
				<button type="submit" id="btn_proceed_filter_clients" class="btn btn-small"><?php _e('Filter','cftp_admin'); ?></button>
			</form>
		</div>
	</div>

	<form action="actions-log.php" name="actions_list" method="post" class="form-inline">
		<div class="form_actions_right">
			<div class="form_actions">
				<div class="form_actions_submit">
					<label><?php _e('Activities actions','cftp_admin'); ?>:</label>
					<select name="log_actions" id="log_actions" class="txtfield">
						<option value="none"><?php _e('Select action','cftp_admin'); ?></option>
						<option value="download"><?php _e('Download as csv','cftp_admin'); ?></option>
						<option value="delete"><?php _e('Delete selected','cftp_admin'); ?></option>
						<option value="clear"><?php _e('Clear entire log','cftp_admin'); ?></option>
					</select>
					<button type="submit" id="do_action" name="proceed" class="btn btn-small"><?php _e('Proceed','cftp_admin'); ?></button>
				</div>
			</div>
		</div>
		<div class="clear"></div>

		<div class="form_actions_count">
			<p><?php _e('Showing','cftp_admin'); ?>: <span><?php echo $count; ?> <?php _e('activities','cftp_admin'); ?></span></p>
		</div>

		<div class="clear"></div>

		<?php
			if (!$count) {
				if (isset($no_results_error)) {
					switch ($no_results_error) {
						case 'filter':
							$no_results_message = __('The filters you selected returned no results.','cftp_admin');;
							break;
					}
				}
				else {
					$no_results_message = __('There are no activities recorded.','cftp_admin');;
				}
				echo system_message('error',$no_results_message);
			}
		?>

		<table id="activities_tbl" class="footable" data-page-size="<?php echo FOOTABLE_PAGING_NUMBER_LOG; ?>">
			<thead>
				<tr>
					<th class="td_checkbox" data-sort-ignore="true">
						<input type="checkbox" name="select_all" id="select_all" value="0" />
					</th>
					<th data-type="numeric" data-sort-initial="descending"><?php _e('Date','cftp_admin'); ?></th>
					<th><?php _e('Author','cftp_admin'); ?></th>
					<th data-hide="phone"><?php _e('Activity','cftp_admin'); ?></th>
					<th data-hide="phone">&nbsp;</th>
					<th data-hide="phone">&nbsp;</th>
					<th data-hide="phone">&nbsp;</th>
				</tr>
			</thead>
			<tbody>
			
			<?php
				while($log = mysql_fetch_array($sql)) {
					$this_action = render_log_action(
										array(
											'action' => $log['action'],
											'timestamp' => $log['timestamp'],
											'owner_id' => $log['owner_id'],
											'owner_user' => $log['owner_user'],
											'affected_file' => $log['affected_file'],
											'affected_file_name' => $log['affected_file_name'],
											'affected_account' => $log['affected_account'],
											'affected_account_name' => $log['affected_account_name']
										)
					);
					$date = date(TIMEFORMAT_USE,strtotime($log['timestamp']));
				?>
				<tr>
					<td><input type="checkbox" name="activities[]" value="<?php echo $log["id"]; ?>" /></td>
					<td data-value="<?php echo strtotime($log['timestamp']); ?>">
						<?php echo $date; ?>
					</td>
					<td><?php echo (!empty($this_action["1"])) ? $this_action["1"] : ''; ?></td>
					<td><?php echo $this_action["text"]; ?></td>
					<td><?php echo (!empty($this_action["2"])) ? $this_action["2"] : ''; ?></td>
					<td><?php echo (!empty($this_action["3"])) ? $this_action["3"] : ''; ?></td>
					<td><?php echo (!empty($this_action["4"])) ? $this_action["4"] : ''; ?></td>
				</tr>
						
				<?php
				}
			
				$database->Close();
			?>
			
			</tbody>
		</table>

		<div class="pagination pagination-centered hide-if-no-paging"></div>
	</form>
	
</div>

<?php include('footer.php'); ?>