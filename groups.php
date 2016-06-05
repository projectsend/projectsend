<?php
/**
 * Show the list of current groups.
 *
 * @package		ProjectSend
 * @subpackage	Groups
 *
 */
$footable = 1;
$allowed_levels = array(9,8);
require_once('sys.includes.php');

$active_nav = 'groups';

$page_title = __('Groups administration','cftp_admin');;

/**
 * Used when viewing groups a certain client belongs to.
 */
if(!empty($_GET['member'])) {
	$member = $_GET['member'];
	/** Add the name of the client to the page's title. */
	$sql_name = $dbh->prepare("SELECT name from " . TABLE_USERS . " WHERE id=:id");
	$sql_name->bindParam(':id', $member, PDO::PARAM_INT);
	$sql_name->execute();

	if ( $sql_name->rowCount() > 0) {
		$sql_name->setFetchMode(PDO::FETCH_ASSOC);
		while ( $row_member = $sql_name->fetch() ) {
			$page_title = ' '.__('Groups where','cftp_admin').' '.html_entity_decode($row_member['name']).' '.__('is member','cftp_admin');
		}
		$member_exists = 1;

		/** Find groups where the client is member */
		$sql_is_member = $dbh->prepare("SELECT DISTINCT group_id FROM " . TABLE_MEMBERS . " WHERE client_id=:id");
		$sql_is_member->bindParam(':id', $member, PDO::PARAM_INT);
		$sql_is_member->execute();

		if ( $sql_is_member->rowCount() > 0) {
			$sql_is_member->setFetchMode(PDO::FETCH_ASSOC);
			while ( $row_groups = $sql_is_member->fetch() ) {
				$groups_ids[] = $row_groups["group_id"];
			}
			$found_groups = implode(',',$groups_ids);
		}
		else {
			$found_groups = '';
		}
	}
	else {
		$no_results_error = 'client_not_exists';
	}
}

include('header.php');


?>

<script type="text/javascript">
	$(document).ready( function() {
		$("#do_action").click(function() {
			var checks = $("td>input:checkbox").serializeArray(); 
			if (checks.length == 0) { 
				alert('<?php _e('Please select at least one group to proceed.','cftp_admin'); ?>');
				return false; 
			}
			else {
				var msg_1 = '<?php _e("You are about to delete",'cftp_admin'); ?>';
				var msg_2 = '<?php _e("groups. Are you sure you want to continue?",'cftp_admin'); ?>';
				if (confirm(msg_1+' '+checks.length+' '+msg_2)) {
					return true;
				} else {
					return false;
				}
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
	if(isset($_POST['groups_actions']) && $_POST['groups_actions'] != 'none') {
		/** Continue only if 1 or more users were selected. */
		if(!empty($_POST['groups'])) {
			$selected_groups = $_POST['groups'];
			$groups_to_get = implode( ',', array_map( 'intval', array_unique( $selected_groups ) ) );

			/**
			 * Make a list of groups to avoid individual queries.
			 */
			$sql_grps = $dbh->prepare("SELECT id, name FROM " . TABLE_GROUPS . " WHERE FIND_IN_SET(id, :groups)");
			$sql_grps->bindParam(':groups', $groups_to_get);
			$sql_grps->execute();
			$sql_grps->setFetchMode(PDO::FETCH_ASSOC);
			while( $data_group = $sql_grps->fetch() ) {
				$all_groups[$data_group['id']] = $data_group['name'];
			}

			switch($_POST['groups_actions']) {
				case 'delete':
					$deleted_groups = 0;

					foreach ($selected_groups as $groups) {
						$this_group = new GroupActions();
						$delete_group = $this_group->delete_group($groups);
						$deleted_groups++;

						/** Record the action log */
						$new_log_action = new LogActions();
						$log_action_args = array(
												'action' => 18,
												'owner_id' => $global_id,
												'affected_account_name' => $all_groups[$groups]
											);
						$new_record_action = $new_log_action->log_action_save($log_action_args);		
					}
					
					if ($deleted_groups > 0) {
						$msg = __('The selected groups were deleted.','cftp_admin');
						echo system_message('ok',$msg);
					}
				break;
			}
		}
		else {
			$msg = __('Please select at least one group.','cftp_admin');
			echo system_message('error',$msg);
		}
	}
	
	/**
	 * Generate the list of available groups.
	 */

	/**
	 * Generate an array of file count per group
	 */
	$files_amount = array();
	$count_files_sql = $dbh->prepare("SELECT group_id, COUNT(file_id) as files FROM " . TABLE_FILES_RELATIONS . " WHERE group_id IS NOT NULL GROUP BY group_id");
	$count_files_sql->execute();
	$count_files = $count_files_sql->rowCount();
	if ($count_files > 0) {
		$count_files_sql->setFetchMode(PDO::FETCH_ASSOC);
		while ( $crow = $count_files_sql->fetch() ) {
			$files_amount[$crow['group_id']] = $crow['files'];
		}
	}

	/**
	 * Generate an array of amount of users on each group
	 */
	$members_amount = array();
	$count_members_sql = $dbh->prepare("SELECT group_id, COUNT(client_id) as members FROM " . TABLE_MEMBERS . " GROUP BY group_id");
	$count_members_sql->execute();
	$count_members = $count_members_sql->rowCount();
	if ($count_members > 0) {
		while ( $mrow = $count_members_sql->fetch() ) {
			$members_amount[$mrow['group_id']] = $mrow['members'];
		}
	}



	$params = array();
	$cq = "SELECT * FROM " . TABLE_GROUPS;

	/** Add the search terms */	
	if ( isset( $_POST['search'] ) && !empty( $_POST['search'] ) ) {
		$cq .= " WHERE (name LIKE :name OR description LIKE :description)";
		$next_clause = ' AND';
		$no_results_error = 'search';

		$search_terms			= '%'.$_POST['search'].'%';
		$params[':name']		= $search_terms;
		$params[':description']	= $search_terms;
	}
	else {
		$next_clause = ' WHERE';
	}
	
	/** Add the member */
	if (isset($found_groups)) {
		if ($found_groups != '') {
			$cq .= $next_clause. " FIND_IN_SET(id, :groups)";
			$params[':groups']		= $found_groups;
		}
		else {
			$cq .= $next_clause. " id = NULL";
		}
		$no_results_error = 'is_not_member';
	}

	$cq .= " ORDER BY name ASC";
	
	$sql = $dbh->prepare( $cq );
	$sql->execute( $params );
	$count = $sql->rowCount();
?>

	<div class="form_actions_left">
		<div class="form_actions_limit_results">
			<form action="groups.php<?php if(isset($member_exists)) { ?>?member=<?php echo html_output($member); } ?>" name="groups_search" method="post" class="form-inline">
				<input type="text" name="search" id="search" value="<?php if(isset($_POST['search']) && !empty($_POST['search'])) { echo html_output($_POST['search']); } ?>" class="txtfield form_actions_search_box" />
				<button type="submit" id="btn_proceed_search" class="btn btn-small"><?php _e('Search','cftp_admin'); ?></button>
			</form>
		</div>
	</div>

	<form action="groups.php<?php if(isset($member_exists)) { ?>?member=<?php echo html_output($member); } ?>" name="groups_list" method="post" class="form-inline">
		<div class="form_actions_right">
			<div class="form_actions">
				<div class="form_actions_submit">
					<label><?php _e('Selected groups actions','cftp_admin'); ?>:</label>
					<select name="groups_actions" id="groups_actions" class="txtfield">
						<option value="none"><?php _e('Select action','cftp_admin'); ?></option>
						<option value="delete"><?php _e('Delete','cftp_admin'); ?></option>
					</select>
					<button type="submit" id="do_action" name="proceed" class="btn btn-small"><?php _e('Proceed','cftp_admin'); ?></button>
				</div>
			</div>
		</div>
		<div class="clear"></div>

		<div class="form_actions_count">
			<p><?php _e('Showing','cftp_admin'); ?>: <span><?php echo $count; ?> <?php _e('groups','cftp_admin'); ?></span></p>
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
						case 'client_not_exists':
							$no_results_message = __('The client does not exist.','cftp_admin');;
							break;
						case 'is_not_member':
							$no_results_message = __('There are no groups where this client is member.','cftp_admin');;
							break;
					}
				}
				else {
					$no_results_message = __('There are no groups created yet.','cftp_admin');;
				}
				echo system_message('error',$no_results_message);
			}
		?>

		<table id="groups_tbl" class="footable" data-page-size="<?php echo FOOTABLE_PAGING_NUMBER; ?>">
			<thead>
				<tr>
					<th class="td_checkbox" data-sort-ignore="true">
						<input type="checkbox" name="select_all" id="select_all" value="0" />
					</th>
					<th data-sort-initial="true"><?php _e('Group name','cftp_admin'); ?></th>
					<th data-hide="phone"><?php _e('Description','cftp_admin'); ?></th>
					<th data-type="numeric"><?php _e('Members','cftp_admin'); ?></th>
					<th data-hide="phone" data-type="numeric"><?php _e('Files','cftp_admin'); ?></th>
					<th data-hide="phone"><?php _e('Created by','cftp_admin'); ?></th>
					<th data-hide="phone" data-type="numeric"><?php _e('Added on','cftp_admin'); ?></th>
					<th data-hide="phone" data-sort-ignore="true"><?php _e('Actions','cftp_admin'); ?></th>
				</tr>
			</thead>
			<tbody>
			
			<?php
				$sql->setFetchMode(PDO::FETCH_ASSOC);
				while ( $row = $sql->fetch() ) {
					$date = date(TIMEFORMAT_USE,strtotime($row['timestamp']));
				?>
				<tr>
					<td>
						<?php if ($row["id"] != '1') { ?>
							<input type="checkbox" name="groups[]" value="<?php echo $row["id"]; ?>" />
						<?php } ?>
					</td>
					<td><?php echo html_output($row["name"]); ?></td>
					<td><?php echo html_output($row["description"]); ?></td>
					<td>
						<?php
							if (isset($members_amount[$row['id']])) {
								echo $members_amount[$row['id']];
							}
							else {
								echo '0';
							}
						?>
					</td>
					<td>
						<?php
							if (isset($files_amount[$row['id']])) {
								echo $files_amount[$row['id']];
							}
							else {
								echo '0';
							}
						?>
					</td>
					<td><?php echo html_output($row["created_by"]); ?></td>
					<td data-value="<?php echo html_output($row['timestamp']); ?>">
						<?php echo $date; ?>
					</td>
					<td>
						<a href="manage-files.php?group_id=<?php echo $row["id"]; ?>" class="btn btn-primary btn-small"><?php _e('Manage files','cftp_admin'); ?></a>
						<a href="groups-edit.php?id=<?php echo $row["id"]; ?>" class="btn btn-primary btn-small"><?php _e('Edit','cftp_admin'); ?></a>
					</td>
				</tr>
						
				<?php
				}
			?>
			
			</tbody>
		</table>

		<div class="pagination pagination-centered hide-if-no-paging"></div>
	</form>
	
</div>

<?php include('footer.php'); ?>