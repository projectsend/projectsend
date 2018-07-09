<?php
/**
 * Show the list of current groups.
 *
 * @package		ProjectSend
 * @subpackage	Groups
 *
 */
$allowed_levels = array(9,8);
require_once('bootstrap.php');

$active_nav = 'groups';

$page_title = __('Groups administration','cftp_admin');

/**
 * Used when viewing groups a certain client belongs to.
 */
if (!empty($_GET['member']) ) {
    $client_id = $_GET['member'];
    $client = get_client_by_id($client_id);
    if (!empty($client)) {
        $page_title = sprintf(__('Groups where %s is member','cftp_admin'), $client['name']);

		/** Get groups where this client is member */
		$get_groups		= new \ProjectSend\MembersActions();
		$get_arguments	= array(
								'client_id'	=> $client_id,
								'return'	=> 'list',
							);
		$found_groups	= $get_groups->client_get_groups($get_arguments); 
		if ( empty( $found_groups ) ) {
			$found_groups = '';
		}
	}
	else {
		$no_results_error = 'client_not_exists';
	}
}

include_once ADMIN_TEMPLATES_DIR . DS . 'header.php';
?>

<div class="col-xs-12">

<?php
	/**
	 * Apply the corresponding action to the selected users.
	 */
	if (isset($_GET['action']) && $_GET['action'] != 'none') {
		// Continue only if 1 or more users were selected.
		if (!empty($_GET['batch'])) {
			$selected_groups = $_GET['batch'];
			$groups_to_get = implode( ',', array_map( 'intval', array_unique( $selected_groups ) ) );

			// Make a list of groups to avoid individual queries.
            $groups_arguments = [
                'group_ids' => $groups_to_get,
            ];
            $get_groups = get_groups($groups_arguments);

			switch($_GET['action']) {
				case 'delete':
					$deleted_groups = 0;

					foreach ($get_groups as $group => $group_data)  {
						if ( delete_group($group_data['id']) ) {
                            $deleted_groups++;
                        }
                    }
					
					if ($deleted_groups > 0) {
						$msg = __('The selected groups were deleted.','cftp_admin');
						echo system_message('success',$msg);
					}
				break;
			}
		}
		else {
			$msg = __('Please select at least one group.','cftp_admin');
			echo system_message('danger',$msg);
		}
	}
	
	/**
	 * Get the groups
     * @todo use get_groups()
	 */
	$params = array();
	$cq = "SELECT * FROM " . TABLE_GROUPS;

	// Add the search terms
	if ( isset( $_GET['search'] ) && !empty( $_GET['search'] ) ) {
		$cq .= " WHERE (name LIKE :name OR description LIKE :description)";
		$next_clause = ' AND';
		$no_results_error = 'search';

		$search_terms			= '%'.$_GET['search'].'%';
		$params[':name']		= $search_terms;
		$params[':description']	= $search_terms;
	}
	else {
		$next_clause = ' WHERE';
	}
	
	// Add the member
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

	/**
	 * Add the order.
	 * Defaults to order by: name, order: ASC
	 */
	$cq .= sql_add_order( TABLE_GROUPS, 'name', 'asc' );

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
			<?php show_search_form('groups.php'); ?>
		</div>
	</div>

	<form action="groups.php" name="groups_list" method="get" class="form-inline">
		<?php form_add_existing_parameters(); ?>
		<div class="form_actions_right">
			<div class="form_actions">
				<div class="form_actions_submit">
					<div class="form-group group_float">
						<label class="control-label hidden-xs hidden-sm"><i class="glyphicon glyphicon-check"></i> <?php _e('Selected groups actions','cftp_admin'); ?>:</label>
						<select name="action" id="action" class="txtfield form-control">
							<?php
								$actions_options = array(
														'none'			=> __('Select action','cftp_admin'),
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
			<p><?php _e('Found','cftp_admin'); ?>: <span><?php echo $count_for_pagination; ?> <?php _e('groups','cftp_admin'); ?></span></p>
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
						case 'client_not_exists':
							$no_results_message = __('The client does not exist.','cftp_admin');
							break;
						case 'is_not_member':
							$no_results_message = __('There are no groups where this client is member.','cftp_admin');
							break;
					}
				}
				else {
					$no_results_message = __('There are no groups created yet.','cftp_admin');
				}
				echo system_message('danger',$no_results_message);
			}


			if ($count > 0) {
				/**
				 * Generate the table using the class.
				 */
				$table_attributes	= array(
											'id'		=> 'groups_tbl',
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
												'content'		=> __('Group name','cftp_admin'),
											),
											array(
												'sortable'		=> true,
												'sort_url'		=> 'description',
												'content'		=> __('Description','cftp_admin'),
												'hide'			=> 'phone',
											),
											array(
												'content'		=> __('Members','cftp_admin'),
											),
											array(
												'content'		=> __('Files','cftp_admin'),
												'hide'			=> 'phone',
											),
											array(
												'sortable'		=> true,
												'sort_url'		=> 'active',
												'content'		=> __('Public','cftp_admin'),
											),
											array(
												'sortable'		=> true,
												'sort_url'		=> 'created_by',
												'content'		=> __('Created by','cftp_admin'),
												'hide'			=> 'phone',
											),
											array(
												'sortable'		=> true,
												'sort_url'		=> 'timestamp',
												'content'		=> __('Added on','cftp_admin'),
												'hide'			=> 'phone',
											),
											array(
												'content'		=> __('View','cftp_admin'),
												'hide'			=> 'phone',
											),
											array(
												'content'		=> __('Actions','cftp_admin'),
												'hide'			=> 'phone',
											),
										);
				$table->thead( $thead_columns );

				$sql->setFetchMode(PDO::FETCH_ASSOC);
				while ( $row = $sql->fetch() ) {
					$table->add_row();

					/**
					 * Prepare the information to be used later on the cells array
                     * @todo a Group class object needs to return this information
                    */
                    $members_count = count_members_on_group($row['id']);
                    $files_count = count_files_on_group($row['id']);

                    /**
					 * 1- Get account creation date
					 */
					$date = date(TIMEFORMAT,strtotime($row['timestamp']));
					
					/**
					 * 2- Button class for the manage files link
					 */
					if ( $files_count > 0 ) {
						$files_link	= 'manage-files.php?group_id=' . html_output( $row['id'] );
						$files_btn	= 'btn-primary';
					}
					else {
						$files_link	= '#';
						$files_btn	= 'btn-default disabled';
					}
					
					/**
					 * 3- Visibility
					 */
					 if ($row['public'] == '1') {
						 $visibility_link	= '<a href="javascript:void(0);" class="btn btn-primary btn-sm public_link" data-type="group" data-id="' . $row['id'] .'" data-token="' . html_output($row['public_token']) .'">';
						 $visibility_label	= __('Public','cftp_admin');
					 }
					 else {
						 $visibility_link	= '<a href="javascript:void(0);" class="btn btn-default btn-sm disabled" title="">';
						 $visibility_label	= __('Private','cftp_admin');
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
													'content'		=> html_output( $row["description"] ),
												),
											array(
													'content'		=> $members_count,
												),
											array(
													'content'		=> $files_count,
												),
											array(
													//'content'		=> ( $row["public"] == '1' ) ? __('Yes','cftp_admin') : __('No','cftp_admin'),
													'content'		=> $visibility_link . $visibility_label . '</a>',
												),
											array(
													'content'		=> html_output( $row["created_by"] ),
												),
											array(
													'content'		=> $date,
												),
											array(
													'actions'		=> true,
													'content'		=> '<a href="' . $files_link . '" class="btn ' . $files_btn . ' btn-sm">' . __('Files','cftp_admin') . '</a>',
												),
											array(
													'actions'		=> true,
													'content'		=> '<a href="groups-edit.php?id=' . html_output( $row["id"] ) . '" class="btn btn-primary btn-sm"><i class="fa fa-pencil"></i><span class="button_label">' . __('Edit','cftp_admin') . '</span></a>' . "\n"
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
										'link'		=> basename($_SERVER['SCRIPT_FILENAME']),
										'current'	=> $pagination_page,
										'pages'		=> ceil( $count_for_pagination / RESULTS_PER_PAGE ),
									);
				
				echo $table->pagination( $pagination_args );
			}

		?>
	</form>
	
</div>

<?php
	include_once ADMIN_TEMPLATES_DIR . DS . 'footer.php';
