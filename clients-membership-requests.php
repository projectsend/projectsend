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
						'toggle',
					); 

$allowed_levels = array(9,8);
require_once('sys.includes.php');

$active_nav = 'groups';
$this_page = 'clients-membership-requests.php';

$page_title = __('Membership requests','cftp_admin');
include('header.php');
?>

<script type="text/javascript">
	$(document).ready(function() {
		$('.change_all').click(function(e) {
			e.preventDefault();
			var target = $(this).data('target');
			var check = $(this).data('check');
			$("input[data-client='"+target+"']").prop("checked",check).change();
			check_client(target);
		});
		
		$('.account_action').on("change", function() {
			if ( $(this).prop('checked') == false )  {
				var target = $(this).data('client');
				$(".membership_action[data-client='"+target+"']").prop("checked",false).change();
			}
		});

		$('.checkbox_toggle').change(function() {
			var target = $(this).data('client');
			check_client(target);
		});

		function check_client(client_id) {
			$("input[data-clientid='"+client_id+"']").prop("checked",true);
		}
	});
</script>

<div class="col-xs-12">
<?php
	if (isset($_GET['action'])) {
		switch ($_GET['action']) {
			case 'apply':
				$msg = __('The selected actions were applied.','cftp_admin');
				echo system_message('ok',$msg);
				break;
			case 'delete':
				$msg = __('The selected requests were deleted.','cftp_admin');
				echo system_message('ok',$msg);
				break;
		}
	}

	/**
	 * Apply the corresponding action to the selected clients.
	 */
	if ( !empty($_POST) ) {
		//print_array($_POST);

		/** Continue only if 1 or more clients were selected. */
		if(!empty($_POST['accounts'])) {
			$selected_clients = $_POST['accounts'];
			
			$selected_clients_ids = array();
			foreach ( $selected_clients as $id => $data ) {
				$selected_clients_ids[] = $id;
			}
			$clients_to_get = implode( ',', array_map( 'intval', array_unique( $selected_clients_ids ) ) );

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


			switch($_POST['action']) {
				case 'apply':
					$selected_clients = $_POST['accounts'];
					foreach ( $selected_clients as $client ) {
						$email_type = 'client_memberships_process';
						$process_memberships	= new MembersActions();

						/**
						 * 1 - Process memberships requests
						 */
						if ( empty( $client['groups'] ) ) {
							$client['groups'] = array();
						}

						$memberships_arguments = array(
														'client_id'	=> $client['id'],
														'approve'	=> $client['groups'],
													);

						$process_requests	= $process_memberships->group_process_memberships( $memberships_arguments );

						/**
						 * 3- Send email to the client
						 */
						/** Send email */
						$processed_requests = $process_requests['memberships'];
						$client_information = get_client_by_id( $client['id'] );

						$notify_client = new PSend_Email();
						$email_arguments = array(
														'type'			=> $email_type,
														'username'		=> $client_information['username'],
														'name'			=> $client_information['name'],
														'addresses'		=> $client_information['email'],
														'memberships'	=> $processed_requests,
													);
						$notify_send = $notify_client->psend_send_email($email_arguments);
					}
					
					$log_action_number = 39;
					break;
				case 'delete':
					foreach ($selected_clients as $client) {
						$process_memberships	= new MembersActions();

						$memberships_arguments = array(
														'client_id'	=> $client['id'],
														'type'		=> ( !empty( $_POST['denied'] ) && $_POST['denied'] == 1 ) ? 'denied' : 'new',
													);

						$delete_requests = $process_memberships->group_delete_requests( $memberships_arguments );
					}
					
					$log_action_number = 39;
					break;
				default:
					break;
			}

			/** Record the action log */
			if ( !empty( $log_action_number ) ) {
				foreach ($selected_clients_ids as $client) {
					$new_log_action = new LogActions();
					$log_action_args = array(
											'action' => $log_action_number,
											'owner_id' => CURRENT_USER_ID,
											'affected_account_name' => $all_users[$client]
										);
					$new_record_action = $new_log_action->log_action_save($log_action_args);
				}
			}

			/** Redirect after processing */
			while (ob_get_level()) ob_end_clean();
			$action_redirect = html_output($_POST['action']);
			$location = BASE_URI . $this_page . '?action=' . $action_redirect;
			if ( !empty( $_POST['denied'] ) && $_POST['denied'] == 1 ) {
				$location .= '&denied=1';
			}
			header("Location: $location");
			die();
		}
		else {
			$msg = __('Please select at least one client.','cftp_admin');
			echo system_message('error',$msg);
		}
	}

	/** Query the clients */
	/**
	 * TODO: Make a list of existing clients to exclude those with pending account requests
	 * from the membership requests query
	 */

	$params = array();

	$cq = "SELECT client_id, COUNT(group_id) as amount, GROUP_CONCAT(group_id SEPARATOR ',') AS groups FROM " . TABLE_MEMBERS_REQUESTS;
	
	if ( isset( $_GET['denied'] ) && !empty( $_GET['denied'] ) ) {
		$cq .= " WHERE denied='1'";
		$current_filter = 'denied';  // Which link to highlight
		$found_count = COUNT_MEMBERSHIP_DENIED;
	}
	else {
		$cq .= " WHERE denied='0'";
		$current_filter = 'new';
		$found_count = COUNT_MEMBERSHIP_REQUESTS;
	}
	
	$cq .= " GROUP BY client_id";

	/**
	 * Pre-query to count the total results
	*/
	$tq = $cq;
	$tq .= " LIMIT 0 UNION ALL SELECT COUNT(DISTINCT client_id) as clients, COUNT(group_id) as total, null FROM " . TABLE_MEMBERS_REQUESTS . " WHERE denied='0'";
	//echo $tq;

	$count_sql	= $dbh->prepare( $tq );
	$tq_row		= $count_sql->execute($params);
	$tq_row		= $count_sql->fetch();
	$count_for_pagination = $tq_row['client_id'];

	/**
	 * Add the order.
	 * Defaults to order by: name, order: ASC
	 */
	$cq .= sql_add_order( TABLE_USERS, 'client_id', 'asc' );

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
			</div>
		</div>

		<form action="<?php echo $this_page; ?>" name="requests_list" method="post" class="form-inline">
			<?php form_add_existing_parameters(); ?>
			<div class="form_actions_right">
				<div class="form_actions">
					<div class="form_actions_submit">
						<div class="form-group group_float">
							<label class="control-label hidden-xs hidden-sm"><i class="glyphicon glyphicon-check"></i> <?php _e('Selected requests actions','cftp_admin'); ?>:</label>
							<select name="action" id="action" class="txtfield form-control">
								<?php
									$actions_options = array(
															'none'				=> __('Select action','cftp_admin'),
															'apply'				=> __('Apply selection','cftp_admin'),
															'delete'			=> __('Delete requests','cftp_admin'),
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
				<p><?php _e('Found','cftp_admin'); ?>: <span><?php echo $found_count; ?> <?php _e('requests','cftp_admin'); ?></span></p>
			</div>

			<div class="form_results_filter">
				<?php
					$filters = array(
									'new'		=> array(
														'title'	=> __('New requests','cftp_admin'),
														'link'	=> $this_page,
														'count'	=> COUNT_MEMBERSHIP_REQUESTS,
													),
									'denied'	=> array(
														'title'	=> __('Denied requests','cftp_admin'),
														'link'	=> $this_page . '?denied=1',
														'count'	=> COUNT_MEMBERSHIP_DENIED,
													),
								);
					foreach ( $filters as $type => $filter ) {
				?>
						<a href="<?php echo $filter['link']; ?>" class="<?php echo $current_filter == $type ? 'filter_current' : 'filter_option' ?>"><?php echo $filter['title']; ?> (<?php echo $filter['count']; ?>)</a>
				<?php
					}
				?>
			</div>

			<div class="clear"></div>

			<?php
				if (!$count) {
					if (isset($no_results_error)) {
						switch ($no_results_error) {
							case 'search':
								$no_results_message = __('Your search keywords returned no results.','cftp_admin');
								break;
							case 'filter':
								$no_results_message = __('The filters you selected returned no results.','cftp_admin');
								break;
						}
					}
					else {
						$no_results_message = __('There are no requests at the moment','cftp_admin');
					}
					echo system_message('error',$no_results_message);
				}

				if ($count > 0) {
					$get_groups	= new GroupActions();
					$arguments	= array();
					$all_groups	= $get_groups->get_groups($arguments);


					/**
					 * Pre-populate a membership requests array
					 */
					$get_requests	= new MembersActions();
					$arguments		= array();
					if ( $current_filter == 'denied' ) {
						$arguments['denied'] = 1;
					}
					$get_requests	= $get_requests->get_membership_requests( $arguments );

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
													'content'		=> __('Membership requests','cftp_admin'),
													'hide'			=> 'phone',
												),
												array(
													'content'		=> '',
													'hide'			=> 'phone',
													'attributes'	=> array(
																			'class'		=> array( 'select_buttons' ),
																		),
												),
											);
					$table->thead( $thead_columns );
	
					$sql->setFetchMode(PDO::FETCH_ASSOC);

					/**
					 * Common attributes for the togglers
					 */
					$toggle_attr = 'data-toggle="toggle" data-style="membership_toggle" data-on="Accept" data-off="Deny" data-onstyle="success" data-offstyle="danger" data-size="mini"';
					while ( $row = $sql->fetch() ) {
						$table->add_row();
						
						$client_id		= $row["client_id"];

						$query_client = get_client_by_id($client_id);
						
						/**
						 * Make an array of group membership requests
						 */
						$membership_requests	= '';
						$membership_select		= '';

						/**
						 * Checkbox on the first column
						 */
						$selectable = '<input name="accounts['.$client_id.'][id]" value="'.$client_id.'" type="checkbox" class="batch_checkbox" data-clientid="' . $client_id . '">';

						/**
						 * Checkboxes for every membership request
						 */
						if ( !empty( $row['groups'] ) ) {
							$requests = explode(',', $row['groups']);
							foreach ( $requests as $request ) {
								$this_checkbox = $client_id . '_' . $request;
								$membership_requests .= '<div class="request_checkbox">
															<label for="' . $this_checkbox . '">
																<input ' . $toggle_attr . ' type="checkbox" value="' . $request . '" name="accounts['.$client_id.'][groups][]' . $request . '" id="' . $this_checkbox . '" class="checkbox_options membership_action checkbox_toggle" data-client="'.$client_id.'" /> '. $all_groups[$request]['name'] .'
															</label>
														</div>';

							}
							
							$membership_select = '<a href="#" class="change_all btn btn-default btn-xs" data-target="'.$client_id.'" data-check="true">'.__('Accept all','cftp_admin').'</a> 
												  <a href="#" class="change_all btn btn-default btn-xs" data-target="'.$client_id.'" data-check="false">'.__('Deny all','cftp_admin').'</a>';
						}

						/**
						 * Add the cells to the row
						 */
						$tbody_cells = array(
												array(
														'content'		=> $selectable,
														'attributes'	=> array(
																				'class'		=> array( 'footable-visible', 'footable-first-column' ),
																			),
													),
												array(
														'content'		=> html_output( $query_client["name"] ),
													),
												array(
														'content'		=> html_output( $query_client["username"] ),
													),
												array(
														'content'		=> html_output( $query_client["email"] ),
													),
												array(
														'content'		=> $membership_requests,
													),
												array(
														'content'		=> $membership_select,
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
											'link'		=> $this_page,
											'current'	=> $pagination_page,
											'pages'		=> ceil( $count_for_pagination / RESULTS_PER_PAGE ),
										);
					
					echo $table->pagination( $pagination_args );
				}
			?>
		</form>
	</div>
</div>

<?php
	include('footer.php');
