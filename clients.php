<?php
/**
 * Show the list of current clients.
 *
 * @package		ProjectSend
 * @subpackage	Clients
 *
 */
$footable_min = true; // delete this line after finishing pagination on every table
$load_scripts	= array(
						'footable',
					); 

$allowed_levels = array(9,8);
require_once('sys.includes.php');

$active_nav = 'clients';

$page_title = __('Clients Administration','cftp_admin');
include('header.php');
?>

<script type="text/javascript">
	$(document).ready(function() {
		$("#do_action").click(function() {
			var checks = $("td>input:checkbox").serializeArray(); 
			if (checks.length == 0) { 
				alert('<?php _e('Please select at least one client to proceed.','cftp_admin'); ?>');
				return false; 
			} 
			else {
				var action = $('#action').val();
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
	if(isset($_GET['action'])) {
		/** Continue only if 1 or more clients were selected. */
		if(!empty($_GET['batch'])) {
			$selected_clients = $_GET['batch'];
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

			switch($_GET['action']) {
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
										'owner_id' => CURRENT_USER_ID,
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
	$params = array();

	$cq = "SELECT * FROM " . TABLE_USERS . " WHERE level='0' AND account_requested='0'";

	/** Add the search terms */	
	if ( isset( $_GET['search'] ) && !empty( $_GET['search'] ) ) {
		$cq .= " AND (name LIKE :name OR user LIKE :user OR address LIKE :address OR phone LIKE :phone OR email LIKE :email OR contact LIKE :contact)";
		$no_results_error = 'search';

		$search_terms		= '%'.$_GET['search'].'%';
		$params[':name']	= $search_terms;
		$params[':user']	= $search_terms;
		$params[':address']	= $search_terms;
		$params[':phone']	= $search_terms;
		$params[':email']	= $search_terms;
		$params[':contact']	= $search_terms;
	}

	/** Add the active filter */	
	if(isset($_GET['active']) && $_GET['active'] != '2') {
		$cq .= " AND active = :active";
		$no_results_error = 'filter';

		$params[':active']	= (int)$_GET['active'];
	}
	
	/**
	 * Add the order.
	 * Defaults to order by: name, order: ASC
	 */
	$cq .= sql_add_order( TABLE_USERS, 'name', 'asc' );

	/**
	 * Pre-query to count the total results
	*/
	$count_sql = $dbh->prepare( $cq );
	$count_sql->execute($params);
	$count_for_pagination = $count_sql->rowCount();

	/**
	 * Repeat the query but this time, limited by pagination
	 */
	$cq .= " LIMIT :limit_start, :limit_number";
	$sql = $dbh->prepare( $cq );

	$pagination_page			= ( isset( $_GET["page"] ) ) ? $_GET["page"] : 1;
	$pagination_start			= ( $pagination_page - 1 ) * RESULTS_PER_PAGE;
	$params[':limit_start']		= $pagination_start;
	$params[':limit_number']	= RESULTS_PER_PAGE;

	$sql->execute( $params );
	$count = $sql->rowCount();
?>
		<div class="form_actions_left">
			<div class="form_actions_limit_results">
				<?php show_search_form('clients.php'); ?>

				<form action="clients.php" name="clients_filters" method="get" class="form-inline">
					<?php form_add_existing_parameters( array('active', 'action') ); ?>
					<div class="form-group group_float">
						<select name="active" id="active" class="txtfield form-control">
							<?php
								$status_options = array(
														'2'		=> __('All statuses','cftp_admin'),
														'1'		=> __('Active','cftp_admin'),
														'0'		=> __('Inactive','cftp_admin'),
													);
								foreach ( $status_options as $val => $text ) {
							?>
									<option value="<?php echo $val; ?>" <?php if ( isset( $_GET['active'] ) && $_GET['active'] == $val ) { echo 'selected="selected"'; } ?>><?php echo $text; ?></option>
							<?php
								}
							?>
						</select>
					</div>
					<button type="submit" id="btn_proceed_filter_clients" class="btn btn-sm btn-default"><?php _e('Filter','cftp_admin'); ?></button>
				</form>
			</div>
		</div>

		<form action="clients.php" name="clients_list" method="get" class="form-inline">
			<?php form_add_existing_parameters(); ?>
			<div class="form_actions_right">
				<div class="form_actions">
					<div class="form_actions_submit">
						<div class="form-group group_float">
							<label class="control-label hidden-xs hidden-sm"><i class="glyphicon glyphicon-check"></i> <?php _e('Selected clients actions','cftp_admin'); ?>:</label>
							<select name="action" id="action" class="txtfield form-control">
								<?php
									$actions_options = array(
															'none'			=> __('Select action','cftp_admin'),
															'activate'		=> __('Activate','cftp_admin'),
															'deactivate'	=> __('Deactivate','cftp_admin'),
															'delete'		=> __('Delete','cftp_admin'),
														);
									foreach ( $actions_options as $val => $text ) {
								?>
										<option value="<?php echo $val; ?>"><?php echo $text; ?></option>
								<?php
									}
								?>
							</select>
						</div>
						<button type="submit" id="do_action" class="btn btn-sm btn-default"><?php _e('Proceed','cftp_admin'); ?></button>
					</div>
				</div>
			</div>
			<div class="clear"></div>

			<div class="form_actions_count">
				<p><?php _e('Found','cftp_admin'); ?>: <span><?php echo $count_for_pagination; ?> <?php _e('clients','cftp_admin'); ?></span></p>
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

				if ($count > 0) {
					/**
					 * Generate the table using the class.
					 */
					$table_attributes	= array(
												'id'		=> 'clients_tbl',
												'class'		=> 'footable table',
											);
					$table = new generateTable( $table_attributes );
	
					$thead_columns		= array(
												array(
													'select_all'	=> true,
													'attributes'	=> array(
																			'class'		=> array( 'td_checkbox' ),
																		),
												),
												array(
													'sortable'		=> true,
													'sort_url'		=> 'name',
													'sort_default'	=> true,
													'content'		=> __('Full name','cftp_admin'),
												),
												array(
													'sortable'		=> true,
													'sort_url'		=> 'user',
													'content'		=> __('Log in username','cftp_admin'),
													'hide'			=> 'phone,tablet',
												),
												array(
													'sortable'		=> true,
													'sort_url'		=> 'email',
													'content'		=> __('E-mail','cftp_admin'),
													'hide'			=> 'phone,tablet',
												),
												array(
													'content'		=> __('Uploads','cftp_admin'),
													'hide'			=> 'phone',
												),
												array(
													'content'		=> __('Files: Own','cftp_admin'),
													'hide'			=> 'phone',
												),
												array(
													'content'		=> __('Files: Groups','cftp_admin'),
													'hide'			=> 'phone',
												),
												array(
													'sortable'		=> true,
													'sort_url'		=> 'active',
													'content'		=> __('Status','cftp_admin'),
												),
												array(
													'content'		=> __('Groups on','cftp_admin'),
													'hide'			=> 'phone',
												),
												array(
													'content'		=> __('Notify','cftp_admin'),
													'hide'			=> 'phone,tablet',
												),
												array(
													'sortable'		=> true,
													'sort_url'		=> 'max_file_size',
													'content'		=> __('Max. upload size','cftp_admin'),
													'hide'			=> 'phone',
												),
												array(
													'sortable'		=> true,
													'sort_url'		=> 'timestamp',
													'content'		=> __('Added on','cftp_admin'),
													'hide'			=> 'phone,tablet',
												),
												/*
												array(
													'content'		=> __('Address','cftp_admin'),
													'hide'			=> 'phone,tablet',
												),
												array(
													'content'		=> __('Telephone','cftp_admin'),
													'hide'			=> 'phone,tablet',
												),
												array(
													'content'		=> __('Internal contact','cftp_admin'),
													'hide'			=> 'phone,tablet',
												),
												*/
												array(
													'content'		=> __('Actions','cftp_admin'),
													'hide'			=> 'phone',
												),
											);
					$table->thead( $thead_columns );
	
					$sql->setFetchMode(PDO::FETCH_ASSOC);
					while ( $row = $sql->fetch() ) {
						$table->add_row();

						$client_user	= $row["user"];
						$client_id		= $row["id"];

						/**
						 * Prepare the information to be used later on the cells array
						 * 1- Count GROUPS where the client is member
						 */
						$get_groups		= new MembersActions();
						$get_arguments	= array(
												'client_id'	=> $client_id,
											);
						$found_groups	= $get_groups->client_get_groups($get_arguments); 
						$count_groups	= count( $found_groups );

						$found_groups = ($count_groups > 0) ? implode( ',', $found_groups ) : '';
						
						/**
						 * 2- Get account creation date
						 */
						$date = date(TIMEFORMAT_USE,strtotime($row['timestamp']));

						/**
						 * Prepare the information to be used later on the cells array
						 * 3- Count uploads
						 */
						$uploads_query = "SELECT DISTINCT id FROM " . TABLE_FILES . " WHERE uploader=:username";
						$uploads_files = $dbh->prepare( $uploads_query );
						$uploads_files->bindParam(':username', $client_user);
						$uploads_files->execute();
						$uploads_count = $uploads_files->rowCount();
						
						/**
						 * 4- Count OWN and GROUP files
						 */
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

						/**
						 * 5- Get active status
						 */
						$status_hidden	= __('Inactive','cftp_admin');
						$status_visible	= __('Active','cftp_admin');
						$label			= ($row['active'] == 0) ? $status_hidden : $status_visible;
						$class			= ($row['active'] == 0) ? 'danger' : 'success';
						
						
						/**
						 * 6- Actions buttons
						 */
						if ($own_files + $groups_files > 0) {
							$files_link		= 'manage-files.php?client_id='.$row["id"];
							$files_button	= 'btn-primary';
						}
						else {
							$files_link		= 'javascript:void(0);';
							$files_button	= 'btn-default disabled';
						}

						if ($count_groups > 0) {
							$groups_link	= 'groups.php?member='.$row["id"];
							$groups_button	= 'btn-primary';
						}
						else {
							$groups_link	= 'javascript:void(0);';
							$groups_button	= 'btn-default disabled';
						}
						
						/**
						 * Add the cells to the row
						 */
						$tbody_cells = array(
												array(
														'checkbox'		=> true,
														'value'			=> $row["id"],
													),
												array(
														'content'		=> html_output( $row["name"] ),
													),
												array(
														'content'		=> html_output( $row["user"] ),
													),
												array(
														'content'		=> html_output( $row["email"] ),
													),
												array(
														'content'		=> $uploads_count,
													),
												array(
														'content'		=> $own_files,
													),
												array(
														'content'		=> $groups_files,
													),
												array(
														'content'		=> '<span class="label label-' . $class . '">' . $label . '</span>',
													),
												array(
														'content'		=> $count_groups,
													),
												array(
														'content'		=> ( $row["notify"] == '1' ) ? __('Yes','cftp_admin') : __('No','cftp_admin'),
													),
												array(
														'content'		=> ( $row["max_file_size"] == '0' ) ? __('Default','cftp_admin') : $row["max_file_size"] . 'mb',
													),
												array(
														'content'		=> $date,
													),
												/*
												array(
														'content'		=> html_output( $row["address"] ),
													),
												array(
														'content'		=> html_output( $row["phone"] ),
													),
												array(
														'content'		=> html_output( $row["contact"] ),
													),
												*/
												array(
														'actions'		=> true,
														'content'		=>  '<a href="' . $files_link . '" class="btn btn-sm ' . $files_button . '">' . __("Manage files","cftp_admin") . '</a>' . "\n" .
																			'<a href="' . $groups_link . '" class="btn btn-sm ' . $groups_button . '">' . __("View groups","cftp_admin") . '</a>' . "\n" .
																			'<a href="my_files/?client=' . html_output( $row["user"] ) . '" class="btn btn-primary btn-sm" target="_blank">' . __('View as client','cftp_admin') . '</a>' . "\n" .
																			'<a href="clients-edit.php?id=' . html_output( $row["id"] ) . '" class="btn btn-primary btn-sm">' . __('Edit','cftp_admin') . '</a>' . "\n"
													),
											);

						foreach ( $tbody_cells as $cell ) {
							$table->add_cell( $cell );
						}
		
						$table->end_row();
					}

					echo $table->render();
	
					/**
					 * PAGINATION
					 */
					$pagination_args = array(
											'link'		=> 'clients.php',
											'current'	=> $pagination_page,
											'pages'		=> ceil( $count_for_pagination / RESULTS_PER_PAGE ),
										);
					
					echo $table->pagination( $pagination_args );
				}
			?>
		</form>
	</div>
</div>

<?php include('footer.php'); ?>