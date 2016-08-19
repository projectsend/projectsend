<?php
/**
 * Show the list of current clients.
 *
 * @package		ProjectSend
 * @subpackage	Clients
 *
 */
$footable = 1;
$allowed_levels = array(9,8);
require_once('sys.includes.php');

$active_nav = 'clients';

$page_title = __('Clients Administration','cftp_admin');
include('header.php');
?>

<script type="text/javascript">
$(document).ready(function() {
	$("#view_reduced").click(function(){
		$(this).addClass('active_view_button');
		$("#view_full").removeClass('active_view_button');
		$(".extra").hide();
	});
	$("#view_full").click(function(){
		$(this).addClass('active_view_button');
		$("#view_reduced").removeClass('active_view_button');
		$(".extra").show();
	});
	
	$("#do_action").click(function() {
		var checks = $("td>input:checkbox").serializeArray(); 
		if (checks.length == 0) { 
			alert('<?php _e('Please select at least one client to proceed.','cftp_admin'); ?>');
			return false; 
		} 
		else {
			var action = $('#clients_actions').val();
			if (action == 'delete') {
				var msg_1 = '<?php _e("You are about to delete",'cftp_admin'); ?>';
				var msg_2 = '<?php _e("clients and all of the assigned files. Are you sure you want to continue?",'cftp_admin'); ?>';
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

<div id="main">
	<h2><?php echo $page_title; ?></h2>
	
<?php
	/**
	 * Apply the corresponding action to the selected clients.
	 */
	if(isset($_POST['clients_actions'])) {
		/** Continue only if 1 or more clients were selected. */
		if(!empty($_POST['selected_clients'])) {
			$selected_clients = $_POST['selected_clients'];
			$clients_to_get = implode( ',', array_map( 'intval', array_unique( $selected_clients ) ) );

			/**
			 * Make a list of users to avoid individual queries.
			 */
			$sql_user = $dbh->prepare( "SELECT id, name FROM " . TABLE_USERS . " WHERE FIND_IN_SET(id, :clients)" );
			$sql_user->bindParam(':clients', $clients_to_get);
			$sql_user->execute();
			$sql_user->setFetchMode(PDO::FETCH_ASSOC);
			while ( $data_user = $sql_user->fetch() ) {
				$all_users[$data_user['id']] = $data_user['name'];
			}

			switch($_POST['clients_actions']) {
				case 'activate':
					/**
					 * Changes the value on the "active" column value on the database.
					 * Inactive clients are not allowed to log in.
					 */
					foreach ($selected_clients as $work_client) {
						$this_client = new ClientActions();
						$hide_client = $this_client->change_client_active_status($work_client,'1');
					}
					$msg = __('The selected clients were marked as active.','cftp_admin');
					echo system_message('ok',$msg);
					$log_action_number = 19;
					break;

				case 'deactivate':
					/**
					 * Reverse of the previous action. Setting the value to 0 means
					 * that the client is inactive.
					 */
					foreach ($selected_clients as $work_client) {
						$this_client = new ClientActions();
						$hide_client = $this_client->change_client_active_status($work_client,'0');
					}
					$msg = __('The selected clients were marked as inactive.','cftp_admin');
					echo system_message('ok',$msg);
					$log_action_number = 20;
					break;

				case 'delete':
					foreach ($selected_clients as $client) {
						$this_client = new ClientActions();
						$delete_client = $this_client->delete_client($client);
					}
					
					$msg = __('The selected clients were deleted.','cftp_admin');
					echo system_message('ok',$msg);
					$log_action_number = 17;
					break;
			}

			/** Record the action log */
			foreach ($selected_clients as $client) {
				$new_log_action = new LogActions();
				$log_action_args = array(
										'action' => $log_action_number,
										'owner_id' => $global_id,
										'affected_account_name' => $all_users[$client]
									);
				$new_record_action = $new_log_action->log_action_save($log_action_args);
			}
		}
		else {
			$msg = __('Please select at least one client.','cftp_admin');
			echo system_message('error',$msg);
		}
	}

	/** Query the clients */
	$cq = "SELECT * FROM " . TABLE_USERS . " WHERE level='0'";

	/** Add the search terms */	
	if ( isset( $_POST['search'] ) && !empty( $_POST['search'] ) ) {
		$cq .= " AND (name LIKE :name OR user LIKE :user OR address LIKE :address OR phone LIKE :phone OR email LIKE :email OR contact LIKE :contact)";
		$no_results_error = 'search';

		$search_terms		= '%'.$_POST['search'].'%';
		$params[':name']	= $search_terms;
		$params[':user']	= $search_terms;
		$params[':address']	= $search_terms;
		$params[':phone']	= $search_terms;
		$params[':email']	= $search_terms;
		$params[':contact']	= $search_terms;
	}

	/** Add the status filter */	
	if(isset($_POST['status']) && $_POST['status'] != 'all') {
		$cq .= " AND active = :active";
		$no_results_error = 'filter';

		$params[':active']	= (int)$_POST['status'];
	}
	
	$cq .= " ORDER BY name ASC";

	$sql = $dbh->prepare( $cq );
	$sql->execute( $params );
	$count = $sql->rowCount();
?>
		<div class="form_actions_left">
			<div class="form_actions_limit_results">
				<form action="clients.php" name="clients_search" method="post" class="form-inline">
					<input type="text" name="search" id="search" value="<?php if(isset($_POST['search']) && !empty($_POST['search'])) { echo html_output($_POST['search']); } ?>" class="txtfield form_actions_search_box" />
					<button type="submit" id="btn_proceed_search" class="btn btn-small"><?php _e('Search','cftp_admin'); ?></button>
				</form>

				<form action="clients.php" name="clients_filters" method="post" class="form-inline">
					<select name="status" id="status" class="txtfield">
						<option value="all"><?php _e('All statuses','cftp_admin'); ?></option>
						<option value="1"><?php _e('Active','cftp_admin'); ?></option>
						<option value="0"><?php _e('Inactive','cftp_admin'); ?></option>
					</select>
					<button type="submit" id="btn_proceed_filter_clients" class="btn btn-small"><?php _e('Filter','cftp_admin'); ?></button>
				</form>
			</div>
		</div>

		<form action="clients.php" name="clients_list" method="post" class="form-inline">
			<div class="form_actions_right">
				<div class="form_actions">
					<div class="form_actions_submit">
						<label><?php _e('Selected clients actions','cftp_admin'); ?>:</label>
						<select name="clients_actions" id="clients_actions" class="txtfield">
							<option value="activate"><?php _e('Activate','cftp_admin'); ?></option>
							<option value="deactivate"><?php _e('Deactivate','cftp_admin'); ?></option>
							<option value="delete"><?php _e('Delete','cftp_admin'); ?></option>
						</select>
						<button type="submit" id="do_action" name="proceed" class="btn btn-small"><?php _e('Proceed','cftp_admin'); ?></button>
					</div>
				</div>
			</div>
			<div class="clear"></div>

			<div class="form_actions_count">
				<p class="form_count_total"><?php _e('Showing','cftp_admin'); ?>: <span><?php echo html_output($count); ?> <?php _e('clients','cftp_admin'); ?></span></p>
				<ul id="table_view_modes">
					<li><a href="#" id="view_reduced" class="active_view_button"><?php _e('View reduced table','cftp_admin'); ?></a></li><li>
						<a href="#" id="view_full"><?php _e('View full table','cftp_admin'); ?></a></li>
				</ul>
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
						}
					}
					else {
						$no_results_message = __('There are no clients at the moment','cftp_admin');;
					}
					echo system_message('error',$no_results_message);
				}
			?>

			<table id="clients_tbl" class="footable" data-page-size="<?php echo FOOTABLE_PAGING_NUMBER; ?>">
				<thead>
					<tr>
						<th class="td_checkbox" data-sort-ignore="true">
							<input type="checkbox" name="select_all" id="select_all" value="0" />
						</th>
						<th data-sort-initial="true"><?php _e('Full name','cftp_admin'); ?></th>
						<th data-hide="phone,tablet"><?php _e('Log in username','cftp_admin'); ?></th>
						<th data-hide="phone,tablet"><?php _e('E-mail','cftp_admin'); ?></th>
						<th data-hide="phone" data-type="numeric"><?php _e('Files: Own','cftp_admin'); ?></th>
						<th data-hide="phone" data-type="numeric"><?php _e('Files: Groups','cftp_admin'); ?></th>
						<th><?php _e('Status','cftp_admin'); ?></th>
						<th data-hide="phone" data-type="numeric"><?php _e('Groups on','cftp_admin'); ?></th>
						<th data-hide="phone,tablet"><?php _e('Notify','cftp_admin'); ?></th>
						<th data-hide="phone,tablet" data-type="numeric"><?php _e('Added on','cftp_admin'); ?></th>
						<th data-hide="phone,tablet"><?php _e('Address','cftp_admin'); ?></th>
						<th data-hide="phone,tablet"><?php _e('Telephone','cftp_admin'); ?></th>
						<th data-hide="phone,tablet"><?php _e('Internal contact','cftp_admin'); ?></th>
						<th data-hide="phone" data-sort-ignore="true"><?php _e('Actions','cftp_admin'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
						if ($count > 0) {
							$sql->setFetchMode(PDO::FETCH_ASSOC);
							while ( $row = $sql->fetch() ) {
								$found_groups	= '';
								$client_user	= $row["user"];
								$client_id		= $row["id"];

								$sql_groups = $dbh->prepare("SELECT DISTINCT group_id FROM " . TABLE_MEMBERS . " WHERE client_id=:id");
								$sql_groups->bindParam(':id', $client_id, PDO::PARAM_INT);
								$sql_groups->execute();
								$count_groups	= $sql_groups->rowCount();

								if ($count_groups > 0) {
									$sql_groups->setFetchMode(PDO::FETCH_ASSOC);
									while ( $row_groups = $sql_groups->fetch() ) {
										$groups_ids[] = $row_groups["group_id"];
									}
									$found_groups = implode(',',$groups_ids);
								}

								$date = date(TIMEFORMAT_USE,strtotime($row['timestamp']));
					?>
								<tr>
									<td><input type="checkbox" name="selected_clients[]" value="<?php echo html_output($row["id"]); ?>" /></td>
									<td><?php echo html_output($row["name"]); ?></td>
									<td><?php echo html_output($row["user"]); ?></td>
									<td><?php echo html_output($row["email"]); ?></td>
									<td>
										<?php
											$own_files = 0;
											$groups_files = 0;

											$files_query = "SELECT DISTINCT id, file_id, client_id, group_id FROM " . TABLE_FILES_RELATIONS . " WHERE client_id=:id";
											if ( !empty( $found_groups ) ) {
												$files_query .= " OR FIND_IN_SET(group_id, :group_id)";
											}
											$sql_files = $dbh->prepare( $files_query );
											$sql_files->bindParam(':id', $client_id, PDO::PARAM_INT);
											if ( !empty( $found_groups ) ) {
												$sql_files->bindParam(':group_id', $found_groups);
											}

											$sql_files->execute();
											$count_files = $sql_files->rowCount();

											$sql_files->setFetchMode(PDO::FETCH_ASSOC);
											while ( $row_files = $sql_files->fetch() ) {
												if (!is_null($row_files['client_id'])) {
													$own_files++;
												}
												else {
													$groups_files++;
												}
											}
											
											echo html_output($own_files);
										?>
									</td>
									<td><?php echo html_output($groups_files); ?>
									</td>
									<td>
										<?php
											$status_hidden	= __('Inactive','cftp_admin');
											$status_visible	= __('Active','cftp_admin');
											$label			= ($row['active'] === 0) ? $status_hidden : $status_visible;
											$class			= ($row['active'] === 0) ? 'important' : 'success';
										?>
										<span class="label label-<?php echo $class; ?>">
											<?php echo $label; ?>
										</span>
									</td>
									<td><?php echo $count_groups; ?></td>
									<td><?php if ($row["notify"] == '1') { _e('Yes','cftp_admin'); } else { _e('No','cftp_admin'); }?></td>
									<td data-value="<?php echo strtotime($row['timestamp']); ?>">
										<?php echo $date; ?>
									</td>
									<td><?php echo html_output($row["address"]); ?></td>
									<td><?php echo html_output($row["phone"]); ?></td>
									<td><?php echo html_output($row["contact"]); ?></td>
									<td>
										<?php
											if ($own_files + $groups_files > 0) {
												$files_link = 'manage-files.php?client_id='.$row["id"];
												$files_button = 'btn-primary';
											}
											else {
												$files_link = 'javascript:void(0);';
												$files_button = 'disabled';
											}

											if ($count_groups > 0) {
												$groups_link = 'groups.php?member='.$row["id"];
												$groups_button = 'btn-primary';
											}
											else {
												$groups_link = 'javascript:void(0);';
												$groups_button = 'disabled';
											}
										?>
										<a href="<?php echo $files_link; ?>" class="btn btn-small <?php echo $files_button; ?>"><?php _e('Manage files','cftp_admin'); ?></a>
										<a href="<?php echo $groups_link; ?>" class="btn btn-small <?php echo $groups_button; ?>"><?php _e('View groups','cftp_admin'); ?></a>
										<a href="my_files/?client=<?php echo html_output($row["user"]); ?>" class="btn btn-primary btn-small" target="_blank"><?php _e('View as client','cftp_admin'); ?></a>
										<a href="clients-edit.php?id=<?php echo html_output($row["id"]); ?>" class="btn btn-primary btn-small"><?php _e('Edit','cftp_admin'); ?></a>
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

	</div>

</div>

<?php include('footer.php'); ?>