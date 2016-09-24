<?php
/**
 * Show the list of activities logged.
 *
 * @package		ProjectSend
 * @subpackage	Log
 *
 */
$footable_min = true; // delete this line after finishing pagination on every table
$load_scripts	= array(
						'footable',
					); 

$allowed_levels = array(9);
require_once('sys.includes.php');
$page_title = __('Recent activities log','cftp_admin');

include('header.php');
?>

<script type="text/javascript">
	$(document).ready( function() {
		$("#do_action").click(function() {
			var checks = $("td>input:checkbox").serializeArray(); 
			var action = $('#action').val();

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
	if (isset($_GET['action']) && $_GET['action'] != 'none') {
		/** Continue only if 1 or more users were selected. */
			switch($_GET['action']) {
				case 'delete':

					$selected_actions = $_GET['batch'];
					$delete_ids = implode( ',', $selected_actions );

					if ( !empty( $_GET['batch'] ) ) {
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

	/**
	 * Get the actually requested items
	 */
	$cq = "SELECT * FROM " . TABLE_LOG;

	/** Add the search terms */	
	if ( isset($_GET['search']) && !empty($_GET['search'] ) ) {
		$cq .= " WHERE (owner_user LIKE :owner OR affected_file_name LIKE :file OR affected_account_name LIKE :account)";
		$next_clause = ' AND';
		$no_results_error = 'search';
		
		$search_terms		= '%'.$_GET['search'].'%';
		$params[':owner']	= $search_terms;
		$params[':file']	= $search_terms;
		$params[':account']	= $search_terms;
	}
	else {
		$next_clause = ' WHERE';
	}

	/** Add the activities filter */	
	if (isset($_GET['activity']) && $_GET['activity'] != 'all') {
		$cq .= $next_clause. " action=:status";

		$status_filter		= $_GET['activity'];
		$params[':status']	= $status_filter;

		$no_results_error = 'filter';
	}
	
	/**
	 * Add the order.
	 * Defaults to order by: id, order: DESC
	 */
	$cq .= sql_add_order( TABLE_LOG );

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
	$pagination_start			= ( $pagination_page - 1 ) * RESULTS_PER_PAGE_LOG;
	$params[':limit_start']		= $pagination_start;
	$params[':limit_number']	= RESULTS_PER_PAGE_LOG;

	$sql->execute( $params );
	$count = $sql->rowCount();
?>

	<div class="form_actions_left">
		<div class="form_actions_limit_results">
			<form action="actions-log.php" name="log_search" method="get" class="form-inline">
				<?php form_add_existing_parameters( array('search') ); ?>
				<div class="form-group group_float">
					<input type="text" name="search" id="search" value="<?php if(isset($_GET['search']) && !empty($_GET['search'])) { echo html_output($_GET['search']); } ?>" class="txtfield form_actions_search_box form-control" />
				</div>
				<button type="submit" id="btn_proceed_search" class="btn btn-sm btn-default"><?php _e('Search','cftp_admin'); ?></button>
			</form>

			<form action="actions-log.php" name="actions_filters" method="get" class="form-inline form_filters">
				<?php form_add_existing_parameters( array('activity') ); ?>
				<div class="form-group group_float">
					<label for="activity" class="sr-only"><?php _e('Filter activities','cftp_admin'); ?></label>
					<select name="activity" id="activity" class="form-control">
						<option value="all"><?php _e('All activities','cftp_admin'); ?></option>
						<?php
							global $activities_references;
								foreach ( $activities_references as $val => $text ) {
							?>
									<option value="<?php echo $val; ?>" <?php if ( isset( $_GET['activity'] ) && $_GET['activity'] == $val ) { echo 'selected="selected"'; } ?>><?php echo $text; ?></option>
							<?php
								}
							?>
					</select>
				</div>
				<button type="submit" id="btn_proceed_filter_clients" class="btn btn-sm btn-default"><?php _e('Filter','cftp_admin'); ?></button>
			</form>
		</div>
	</div>

	<form action="actions-log.php" name="actions_list" method="get" class="form-inline">
		<?php form_add_existing_parameters(); // Ignore ACTIVITIES, which ?>
		<div class="form_actions_right">
			<div class="form_actions">
				<div class="form_actions_submit">
					<div class="form-group group_float">
						<label class="control-label hidden-xs hidden-sm"><i class="glyphicon glyphicon-check"></i> <?php _e('Activities actions','cftp_admin'); ?>:</label>
						<select name="action" id="action" class="form-control">
							<option value="none"><?php _e('Select action','cftp_admin'); ?></option>
							<option value="download"><?php _e('Download as csv','cftp_admin'); ?></option>
							<option value="delete"><?php _e('Delete selected','cftp_admin'); ?></option>
							<option value="clear"><?php _e('Clear entire log','cftp_admin'); ?></option>
						</select>
					</div>
					<button type="submit" id="do_action" class="btn btn-sm btn-default"><?php _e('Proceed','cftp_admin'); ?></button>
				</div>
			</div>
		</div>
		<div class="clear"></div>

		<div class="form_actions_count">
			<p><?php _e('Found','cftp_admin'); ?>: <span><?php echo $count_for_pagination; ?> <?php _e('activities','cftp_admin'); ?></span></p>
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

		<?php
		 	/**
			 * Generate the table using the class.
			 */
			$table = new generateTable();
			
			$table_attributes	= array(
										'id'		=> 'activities_tbl',
										'class'		=> 'footable table',
									);
			echo $table->open( $table_attributes );

			$thead_columns		= array(
										array(
											'select_all'	=> true,
											'attributes'	=> array(
																	'class'		=> array( 'td_checkbox' ),
																),
										),
										array(
											'sortable'		=> true,
											'sort_url'		=> 'timestamp',
											'sort_default'	=> true,
											'content'		=> __('Date','cftp_admin'),
										),
										array(
											'sortable'		=> true,
											'sort_url'		=> 'owner_id',
											'content'		=> __('Author','cftp_admin'),
										),
										array(
											'sortable'		=> true,
											'sort_url'		=> 'action',
											'content'		=> __('Activity','cftp_admin'),
											'hide'			=> 'phone',
										),
										array(
											'content'		=> '',
											'hide'			=> 'phone',
										),
										array(
											'content'		=> '',
											'hide'			=> 'phone',
										),
										array(
											'content'		=> '',
											'hide'			=> 'phone',
										),
									);
			echo $table->add_thead( $thead_columns );
		?>

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
						<td><input type="checkbox" name="batch[]" value="<?php echo $log["id"]; ?>" /></td>
						<td data-value="<?php echo strtotime($log['timestamp']); ?>">
							<?php echo html_output($date); ?>
						</td>
						<td><?php echo (!empty($this_action["1"])) ? html_output($this_action["1"]) : ''; ?></td>
						<td><?php echo html_output($this_action["text"]); ?></td>
						<td><?php echo (!empty($this_action["2"])) ? html_output($this_action["2"]) : ''; ?></td>
						<td><?php echo (!empty($this_action["3"])) ? html_output($this_action["3"]) : ''; ?></td>
						<td><?php echo (!empty($this_action["4"])) ? html_output($this_action["4"]) : ''; ?></td>
					</tr>
							
					<?php
					}
				?>
			</tbody>
		
		<?php
			echo $table->close();

			/**
			 * PAGINATION
			 */
			$pagination_args = array(
									'link'		=> 'actions-log.php',
									'current'	=> $pagination_page,
									'pages'		=> ceil( $count_for_pagination / RESULTS_PER_PAGE_LOG ),
								);
			
			echo $table->pagination( $pagination_args );
		?>
	</form>
	
</div>

<?php include('footer.php'); ?>