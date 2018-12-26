<?php
/**
 * Show the list of current clients.
 *
 * @package		ProjectSend
 * @subpackage	Clients
 *
 */
$allowed_levels = array(9,8);
require_once('bootstrap.php');

$active_nav = 'clients';
$this_page = basename($_SERVER['SCRIPT_FILENAME']);

$page_title = __('Account requests','cftp_admin');

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
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
				echo system_message('success',$msg);
				break;
			case 'delete':
				$msg = __('The selected clients were deleted.','cftp_admin');
				echo system_message('success',$msg);
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
						$process_memberships	= new \ProjectSend\MembersActions();

						/**
						 * 1- Approve or deny account
						 */
						$process_account = new \ProjectSend\ClientActions();

						/** $client['account'] == 1 means approve that account */
						if ( !empty( $client['account'] ) and $client['account'] == '1' ) {
							$email_type = 'account_approve';
							/**
							 * 1 - Approve account
							 */
							$approve = $process_account->account_approve( $client['id'] );
							/**
							 * 2 - Prepare memberships information
							 */
							if ( empty( $client['groups'] ) ) {
								$client['groups'] = array();
							}

							$memberships_arguments = array(
															'client_id'	=> $client['id'],
															'approve'	=> $client['groups'],
														);
						}
						else {
							$email_type = 'account_deny';

							/**
							 * 1 - Deny account
							 */
							$deny = $process_account->account_deny( $client['id'] );
							/**
							 * 2 - Deny all memberships
							 */
							$memberships_arguments = array(
															'client_id'	=> $client['id'],
															'deny_all'	=> true,
														);
						}

						/**
						 * 2 - Process memberships requests
						 */
						$process_requests	= $process_memberships->group_process_memberships( $memberships_arguments );

						/**
						 * 3- Send email to the client
						 */
						/** Send email */
						$processed_requests = $process_requests['memberships'];
						$client_information = get_client_by_id( $client['id'] );

						$notify_client = new \ProjectSend\EmailsPrepare();
						$email_arguments = array(
														'type'			=> $email_type,
														'username'		=> $client_information['username'],
														'name'			=> $client_information['name'],
														'addresses'		=> $client_information['email'],
														'memberships'	=> $processed_requests,
														'preview'		=> true,
													);
						$notify_send = $notify_client->send($email_arguments);
					}

					$log_action_number = 38;
					break;
				case 'delete':
					foreach ($selected_clients as $client) {
						$this_client = new \ProjectSend\ClientActions();
						$delete_client = $this_client->delete($client['id']);
					}
					
					$log_action_number = 17;
					break;
				default:
					break;
			}

			/** Record the action log */
			if ( !empty( $log_action_number ) ) {
				foreach ($selected_clients_ids as $client) {
					global $logger;
					$log_action_args = array(
											'action' => $log_action_number,
											'owner_id' => CURRENT_USER_ID,
											'affected_account_name' => $all_users[$client]
										);
					$new_record_action = $logger->add_entry($log_action_args);
				}
			}

			/** Redirect after processing */
			while (ob_get_level()) ob_end_clean();
			$action_redirect = html_output($_POST['action']);
			$location = BASE_URI . 'clients-requests.php?action=' . $action_redirect;
			if ( !empty( $_POST['denied'] ) && $_POST['denied'] == 1 ) {
				$location .= '&denied=1';
			}
			header("Location: $location");
			die();
		}
		else {
			$msg = __('Please select at least one client.','cftp_admin');
			echo system_message('danger',$msg);
		}
	}

	/** Query the clients */
	$params = array();

	$cq = "SELECT * FROM " . TABLE_USERS . " WHERE level='0' AND account_requested='1'";
	
	if ( isset( $_GET['denied'] ) && !empty( $_GET['denied'] ) ) {
		$cq .= " AND account_denied='1'";
		$current_filter = 'denied';  // Which link to highlight
	}
	else {
		$cq .= " AND account_denied='0'";
		$current_filter = 'new';
	}

	/** Add the search terms */	
	if ( isset( $_GET['search'] ) && !empty( $_GET['search'] ) ) {
		$cq .= " AND (name LIKE :name OR username LIKE :user OR address LIKE :address OR phone LIKE :phone OR email LIKE :email OR contact LIKE :contact)";
		$no_results_error = 'search';

		$search_terms		= '%'.$_GET['search'].'%';
		$params[':name']	= $search_terms;
		$params[':user']	= $search_terms;
		$params[':address']	= $search_terms;
		$params[':phone']	= $search_terms;
		$params[':email']	= $search_terms;
		$params[':contact']	= $search_terms;
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
				<?php show_search_form($this_page); ?>
			</div>
		</div>

		<form action="<?php echo $this_page; ?>" name="clients_list" method="post" class="form-inline">
			<?php form_add_existing_parameters(); ?>
			<div class="form_actions_right">
				<div class="form_actions">
					<div class="form_actions_submit">
						<div class="form-group group_float">
							<label class="control-label hidden-xs hidden-sm"><i class="glyphicon glyphicon-check"></i> <?php _e('Selected clients actions','cftp_admin'); ?>:</label>
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
				<p><?php _e('Found','cftp_admin'); ?>: <span><?php echo $count_for_pagination; ?> <?php _e('requests','cftp_admin'); ?></span></p>
			</div>

			<div class="form_results_filter">
				<?php
					$filters = array(
									'new'		=> array(
														'title'	=> __('New account requests','cftp_admin'),
														'link'	=> $this_page,
														'count'	=> COUNT_CLIENTS_REQUESTS,
													),
									'denied'	=> array(
														'title'	=> __('Denied accounts','cftp_admin'),
														'link'	=> $this_page . '?denied=1',
														'count'	=> COUNT_CLIENTS_DENIED,
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
					echo system_message('danger',$no_results_message);
				}

				if ($count > 0) {
					/**
					 * Pre-populate a membership requests array
					 */
					$get_requests	= new \ProjectSend\MembersActions();
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
					$table = new \ProjectSend\TableGenerate( $table_attributes );
	
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
													'sort_url'		=> 'username',
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
													'content'		=> __('Account','cftp_admin'),
													'hide'			=> 'phone',
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
												array(
													'sortable'		=> true,
													'sort_url'		=> 'timestamp',
													'content'		=> __('Added on','cftp_admin'),
													'hide'			=> 'phone,tablet',
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

						$client_user	= $row["username"];
						$client_id		= $row["id"];

						/**
						 * Get account creation date
						 */
						$date = date(TIMEFORMAT,strtotime($row['timestamp']));
						
						/**
						 * Make an array of group membership requests
						 */
						$membership_requests	= '';
						$membership_select		= '';

						/**
						 * Checkbox on the first column
						 */
						$selectable = '<input name="accounts['.$row['id'].'][id]" value="'.$row['id'].'" type="checkbox" class="batch_checkbox" data-clientid="' . $client_id . '">';

						/**
						 * Checkbox for the account action
						 */
						$action_checkbox = '';
						$account_request = '<div class="request_checkbox">
													<label for="' . $action_checkbox . '">
														<input ' . $toggle_attr . ' type="checkbox" value="1" name="accounts['.$row['id'].'][account]" id="' . $action_checkbox . '" class="checkbox_options account_action checkbox_toggle" data-client="'.$client_id.'" />
													</label>
												</div>';


						/**
						 * Checkboxes for every membership request
						 */
						if ( !empty( $get_requests[$row['id']]['requests'] ) ) {
							foreach ( $get_requests[$row['id']]['requests'] as $request ) {
								$this_checkbox = $client_id . '_' . $request['id'];
								$membership_requests .= '<div class="request_checkbox">
															<label for="' . $this_checkbox . '">
																<input ' . $toggle_attr . ' type="checkbox" value="' . $request['id'] . '" name="accounts['.$row['id'].'][groups][]' . $request['id'] . '" id="' . $this_checkbox . '" class="checkbox_options membership_action checkbox_toggle" data-client="'.$client_id.'" /> '. $request['name'] .'
															</label>
														</div>';
								
								//echo '<input type="hidden" name="accounts['.$row['id'].'][requests][]" value="' . $request['id'] . '">';
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
														'content'		=> html_output( $row["name"] ),
													),
												array(
														'content'		=> html_output( $row["username"] ),
													),
												array(
														'content'		=> html_output( $row["email"] ),
													),
												array(
														'content'		=> $account_request,
													),
												array(
														'content'		=> $membership_requests,
													),
												array(
														'content'		=> $membership_select,
													),
												array(
														'content'		=> $date,
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
	include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
