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
	if (isset($_POST['log_actions']) && $_POST['log_actions'] != 'none') {
		/** Continue only if 1 or more users were selected. */
			switch($_POST['log_actions']) {
				case 'delete':

					$selected_actions = $_POST['activities'];
					$delete_ids = implode( ',', $selected_actions );

					if ( !empty( $_POST['activities'] ) ) {
							$statement = $dbh->prepare("DELETE FROM " . TABLE_LOG . " WHERE FIND_IN_SET(id, :delete)");
							$params = array(
											':delete'	=> $delete_ids,
										);
							$statement->execute( $params );
						
							$msg = __('The selected activities were deleted.','cftp_admin');
							echo system_message('ok',$msg);
					}
					else {
						$msg = __('Please select at least one activity.','cftp_admin');
						echo system_message('error',$msg);
					}
				break;
				case 'clear':
					$keep = '5,8,9';
					$statement = $dbh->prepare("DELETE FROM " . TABLE_LOG . " WHERE NOT ( FIND_IN_SET(action, :keep) ) ");
					$params = array(
									':keep'	=> $keep,
								);
					$statement->execute( $params );

					$msg = __('The log was cleared. Only data used for statistics remained. You can delete them manually if you want.','cftp_admin');
					echo system_message('ok',$msg);
				break;
			}
	}

	$params	= array();

	$cq = "SELECT * FROM " . TABLE_LOG;

	/** Add the search terms */	
	if ( isset($_POST['search']) && !empty($_POST['search'] ) ) {
		$cq .= " WHERE (owner_user LIKE :owner OR affected_file_name LIKE :file OR affected_account_name LIKE :account)";
		$next_clause = ' AND';
		$no_results_error = 'search';
		
		$search_terms		= '%'.$_POST['search'].'%';
		$params[':owner']	= $search_terms;
		$params[':file']	= $search_terms;
		$params[':account']	= $search_terms;
	}
	else {
		$next_clause = ' WHERE';
	}

	/** Add the activities filter */	
	if(isset($_POST['activity']) && $_POST['activity'] != 'all') {
		$cq .= $next_clause. " action=:status";

		$status_filter		= $_POST['activity'];
		$params[':status']	= $status_filter;

		$no_results_error = 'filter';
	}

	$cq .= " ORDER BY id DESC";
	
	$sql = $dbh->prepare( $cq );
	$sql->execute( $params );
	$count = $sql->rowCount();
?>

	<div class="form_actions_left">
		<div class="form_actions_limit_results">
			<form action="actions-log.php" name="log_search" method="post" class="form-inline">
				<div class="form-group group_float">
					<input type="text" name="search" id="search" value="<?php if(isset($_POST['search']) && !empty($_POST['search'])) { echo html_output($_POST['search']); } ?>" class="txtfield form_actions_search_box form-control" />
				</div>
				<button type="submit" id="btn_proceed_search" class="btn btn-sm btn-default"><?php _e('Search','cftp_admin'); ?></button>
			</form>

			<form action="actions-log.php" name="actions_filters" method="post" class="form-inline form_filters">
				<div class="form-group group_float">
					<label for="activity" class="sr-only"><?php _e('Filter activities','cftp_admin'); ?></label>
					<select name="activity" id="activity" class="form-control">
						<option value="all"><?php _e('All activities','cftp_admin'); ?></option>
						<?php
							$activities = array(
												0	=> 'ProjecSend has been installed',
												1	=> 'Account logs in through the form',
												24	=> 'Account logs in through cookies',
												31	=> 'Account (user or client) logs out',
												2	=> 'A user creates a new user account',
												3	=> 'A user creates a new client account',
												4	=> 'A client registers an account for himself',
												5	=> 'A file is uploaded by an user',
												6	=> 'A file is uploaded by a client',
												7	=> 'A file is downloaded by a user (on "Client view" mode)',
												8	=> 'A file is downloaded by a client',
												9	=> 'A zip file was generated by a client',
												10	=> 'A file has been unassigned from a client.',
												11	=> 'A file has been unassigned from a group',
												12	=> 'A file has been deleted',
												13	=> 'A user was edited',
												14	=> 'A client was edited',
												15	=> 'A group was edited',
												16	=> 'A user was deleted',
												17	=> 'A client was deleted',
												18	=> 'A group was deleted',
												19	=> 'A client account was activated',
												20	=> 'A client account was deactivated',
												27	=> 'A user account was activated',
												28	=> 'A user account was deactivated',
												21	=> 'A file was marked as hidden',
												22	=> 'A file was marked as visible',
												23	=> 'A user creates a new group',
												25	=> 'A file is assigned to a client',
												26	=> 'A file is assigned to a group',
												27	=> 'A user account was marked as active', // TODO: check repetition
												28	=> 'A user account was marked as inactive',
												29	=> 'The logo on "Branding" was changed',
												30	=> 'ProjectSend was updated',
												32	=> 'A system user edited a file.',
												33	=> 'A client edited a file.',
											);
								foreach ( $activities as $val => $text ) {
							?>
									<option value="<?php echo $val; ?>"><?php _e($text,'cftp_admin'); ?></option>
							<?php
								}
							?>
					</select>
				</div>
				<button type="submit" id="btn_proceed_filter_clients" class="btn btn-sm btn-default"><?php _e('Filter','cftp_admin'); ?></button>
			</form>
		</div>
	</div>

	<form action="actions-log.php" name="actions_list" method="post" class="form-inline">
		<div class="form_actions_right">
			<div class="form_actions">
				<div class="form_actions_submit">
					<div class="form-group group_float">
						<label class="control-label hidden-xs hidden-sm"><i class="glyphicon glyphicon-check"></i> <?php _e('Activities actions','cftp_admin'); ?>:</label>
						<select name="log_actions" id="log_actions" class="form-control">
							<option value="none"><?php _e('Select action','cftp_admin'); ?></option>
							<option value="download"><?php _e('Download as csv','cftp_admin'); ?></option>
							<option value="delete"><?php _e('Delete selected','cftp_admin'); ?></option>
							<option value="clear"><?php _e('Clear entire log','cftp_admin'); ?></option>
						</select>
					</div>
					<button type="submit" id="do_action" name="proceed" class="btn btn-sm btn-default"><?php _e('Proceed','cftp_admin'); ?></button>
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
				$sql->setFetchMode(PDO::FETCH_ASSOC);
				while ( $log = $sql->fetch() ) {
					$this_action = render_log_action(
										array(
											'action'				=> $log['action'],
											'timestamp'				=> $log['timestamp'],
											'owner_id'				=> $log['owner_id'],
											'owner_user'			=> $log['owner_user'],
											'affected_file'			=> $log['affected_file'],
											'affected_file_name'	=> $log['affected_file_name'],
											'affected_account'		=> $log['affected_account'],
											'affected_account_name'	=> $log['affected_account_name']
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
			?>
			
			</tbody>
		</table>

		<nav aria-label="<?php _e('Results navigation','cftp_admin'); ?>">
            <div class="pagination_wrapper text-center">
                <ul class="pagination hide-if-no-paging"></ul>
            </div>
        </nav>
	</form>
	
</div>

<?php include('footer.php'); ?>