<?php
/**
 * Allows to hide, show or delete the files assigend to the
 * selected client.
 *
 * @package		ProjectSend
 * @subpackage	Files
 */
$load_scripts	= array(
						'footable',
					); 

$allowed_levels = array(9,8,7);
require_once('sys.includes.php');

$active_nav = 'files';

$page_title = __('Categories administration','cftp_admin');

$current_level = get_current_user_level();

include('header.php');

?>

<script type="text/javascript">
	$(document).ready( function() {
		$("#do_action").click(function() {
			var checks = $("td>input:checkbox").serializeArray(); 
			if (checks.length == 0) { 
				alert('<?php _e('Please select at least one category to proceed.','cftp_admin'); ?>');
				return false; 
			}
			else {
				var action = $('#categories_actions').val();
				if (action == 'delete') {
					var msg_1 = '<?php _e("You are about to delete",'cftp_admin'); ?>';
					var msg_2 = '<?php _e("categories. Are you sure you want to continue?",'cftp_admin'); ?>';
					if (confirm(msg_1+' '+checks.length+' '+msg_2)) {
						return true;
					} else {
						return false;
					}
				}
			}
		});

		$("#process_category").submit(function() {
			clean_form( this );

			is_complete( this.category_name, '<?php echo $validation_no_name; ?>' );

			// show the errors or continue if everything is ok
			if (show_form_errors() == false) { return false; }
		});
	});
</script>

<div id="main">

	<h2><?php echo $page_title; ?></h2>

	<?php
	/**
	 * Messages set when adding or editing a category
	 */
	if ( !empty( $_GET['status'] ) ) {
		$result_status = $_GET['status'];
		switch ( $result_status ) {
			case 'added':
					$msg_text	= __('The category was successfully created.','cftp_admin');
					$msg_type	= 'ok';
				break;
			case 'edited':
					$msg_text	= __('The category was successfully edited.','cftp_admin');
					$msg_type	= 'ok';
				break;
		}

		echo system_message( $msg_type, $msg_text );
	}


	/**
	 * Apply the corresponding action to the selected categories.
	 */
	if ( isset( $_POST['categories_actions'] ) ) {
		/** Continue only if 1 or more categories were selected. */
		if ( !empty($_POST['categories'] ) ) {

			/**
			 * Make a list of categories to avoid individual queries.
			 */
			$selected_categories	= $_POST['categories'];

			$get_categories_delete	= get_categories(
													array(
														'id' => $selected_categories,
													)
												);
			foreach ( $get_categories_delete['categories'] as $delete_cat ) {
				$all_categories[$delete_cat['id']] = $delete_cat['name'];
			}

			$my_info = get_user_by_username(get_current_user_username());
			$affected_users = 0;

			switch($_POST['categories_actions']) {
				case 'delete':
					foreach ($selected_categories as $category) {
						$this_category		= new CategoriesActions();
						$delete_category	= $this_category->delete_category($category);
					}
					$msg = __('The selected categories were deleted.','cftp_admin');
					echo system_message('ok',$msg);
					$log_action_number = 36;
					break;
			}

			/** Record the action log */
			foreach ($selected_categories as $category) {
				$new_log_action = new LogActions();
				$log_action_args = array(
										'action'				=> $log_action_number,
										'owner_id'				=> CURRENT_USER_ID,
										'affected_account_name'	=> $all_categories[$category]
									);
				$new_record_action = $new_log_action->log_action_save($log_action_args);
			}
		}
		else {
			$msg = __('Please select at least one category.','cftp_admin');
			echo system_message('error',$msg);
		}
	}
	
	/** Get all the existing categories */
	$params = array();

	/**
	 * Add the search terms
	 *
	 * TODO: Search gets the real results, but subcategories
	 * are not shown if the parent is not in the results
	 */
	if ( isset( $_GET['search'] ) && !empty( $_GET['search'] ) ) {
		$params['search']	= $_GET['search'];
	}

	$get_categories = get_categories( $params );
	if ( !empty( $get_categories['categories'] ) ) {
		$categories	= $get_categories['categories'];
		$arranged	= $get_categories['arranged'];
	}
	else {
		$categories	= null;
		$arranged	= null;
	}

	/**
	 * Adding or editing a category
	 *
	 * By default, the action is ADD category
	 */
	$form_information = array(
								'type'	=> 'new_category',
								'title'	=> __('Create new category','cftp_admin'),
							);
	
	/** Loading the form in EDIT mode */
	if (
		( !empty( $_GET['action'] ) && $_GET['action'] == 'edit' ) or
		!empty( $_POST['editing_id'] )
	) {
		$action				= 'edit';
		$editing			= !empty( $_POST['editing_id'] ) ? $_POST['editing_id'] : $_GET['id'];
		$form_information	= array(
									'type'	=> 'edit_category',
									'title'	=> __('Edit category','cftp_admin'),
								);

		/**
		 * Get the current information if just entering edit mode
		 */
		$category_name			= $categories[$editing]['name'];
		$category_parent		= $categories[$editing]['parent'];
		$category_description	= $categories[$editing]['description'];
	}


	/**
	 * Process the action
	 */
	if ( isset( $_POST['btn_process'] ) ) {
		/**
		 * Applies for both ADDING a new category as well
		 * as editing one but with the form already sent.
		 */
		$category_name			= $_POST['category_name'];
		$category_parent		= $_POST['category_parent'];
		$category_description	= $_POST['category_description'];

		$category_object = new CategoriesActions();

		$arguments = array(
							'name'			=> $category_name,
							'parent'		=> $category_parent,
							'description'	=> $category_description,
						);

		$validate = $category_object->validate_category( $arguments );

		switch ( $form_information['type'] ) {
			case 'new_category':
					$arguments['action']	= 'add';
					$redirect_status		= 'added';				
				break;
			case 'edit_category':
					$arguments['action']	= 'edit';
					$redirect_status		= 'edited';
					$arguments['id']		= ( $_POST ) ? $_POST['editing_id'] : $_GET['id'];
				break;
		}

		if ( $validate === 1 ) {
			$process = $category_object->save_category( $arguments );
			if ( $process['query'] === 1 ) {
				$redirect = true;
				$status = $redirect_status;
			}
			else {
				$msg = __('There was a problem saving to the database.','cftp_admin');
				echo system_message('error', $msg);
			}
		}
		else {
			$msg = __('Please complete all the required fields.','cftp_admin');
			echo system_message('error', $msg);
		}

		/** Redirect so the actions are reflected immediatly */
		if ( isset( $redirect ) && $redirect === true ) {
			while (ob_get_level()) ob_end_clean();
			$location = BASE_URI . 'categories.php?status=' . $status;
			header("Location: $location");
			die();
		}
	}
?>

		<div class="form_actions_left">
			<div class="form_actions_limit_results">
				<?php show_search_form('categories.php'); ?>
			</div>
		</div>

		<form action="categories.php" name="selected_categories" id="selected_categories" method="post">

			<div class="form_actions_right form-inline">
				<div class="form_actions">
					<div class="form_actions_submit">
						<div class="form-group group_float">
							<label class="control-label hidden-xs hidden-sm"><i class="glyphicon glyphicon-check"></i> <?php _e('Selected categories actions','cftp_admin'); ?>:</label>
							<select name="categories_actions" id="categories_actions" class="txtfield form-control">
								<option value="delete"><?php _e('Delete','cftp_admin'); ?></option>
							</select>
						</div>
						<button type="submit" name="do_action" id="do_action" class="btn btn-sm btn-default"><?php _e('Proceed','cftp_admin'); ?></button>
					</div>
				</div>
			</div>

			<div class="clear"></div>
	
			<div class="form_actions_count">
				<p class="form_count_total"><?php _e('Found','cftp_admin'); ?>: <span><?php echo $get_categories['count']; ?> <?php _e('categories','cftp_admin'); ?></span></p>
			</div>
	
			<div class="clear"></div>

			<?php
				if ( $get_categories['count'] == 0 ) {
					if ( !empty( $get_categories['no_results_type'] ) ) {
						switch ( $get_categories['no_results_type'] ) {
							case 'search':
								$no_results_message = __('Your search keywords returned no results.','cftp_admin');;
								break;
						}
					}
					else {
						$no_results_message = __('There are no categories yet.','cftp_admin');;
					}
					echo system_message('error', $no_results_message);
				}
			?>

			<div class="container-fluid">
				<div class="row">
					<div class="col-xs-12 col-sm-12 col-md-8">
						<table id="categories_tbl" class="footable" data-page-size="<?php echo FOOTABLE_PAGING_NUMBER; ?>">
							<thead>
								<tr>
									<th class="td_checkbox" data-sort-ignore="true">
										<input type="checkbox" name="select_all" id="select_all" value="0" />
									</th>
									<th data-sort-ignore="true"><?php _e('Name','cftp_admin'); ?></th>
									<th data-sort-ignore="true"><?php _e('Files','cftp_admin'); ?></th>
									<th data-hide="phone" data-sort-ignore="true"><?php _e('Description','cftp_admin'); ?></th>
									<th data-hide="phone" data-sort-ignore="true"><?php _e('Actions','cftp_admin'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
									/**
									 * Having the formatting function here seems more convenient
									 * as the HTML layout is easier to edit on it's real context.
									 */
									$c = 0;
									function format_category_row( $categories ) {
										global $c;
										$c++;
										if ( !empty( $categories ) ) {
											foreach ( $categories as $category ) {
												$depth = ( $category['depth'] > 0 ) ? str_repeat( '&mdash;', $category['depth'] ) . ' ' : false;
								?>
												<tr>
													<td>
														<input type="checkbox" name="categories[]" value="<?php echo html_output($category["id"]); ?>" />
													</td>
													<td data-value="<?php echo $c; ?>"><?php echo $depth . html_output($category["name"]); ?></td>
													<td>
														<?php
															$total = $category['file_count'];
															if ( $total > 0 ) {
																$class			= 'success';
																$files_link 	= 'manage-files.php?category=' . $category['id'];
																$files_button	= 'btn-primary';
															}
															else {
																$class			= 'danger';
																$files_link		= 'javascript:void(0);';
																$files_button	= 'btn-default disabled';
															}
														?>
														<span class="label label-<?php echo $class; ?>">
															<?php echo $total; ?>
														</span>
													</td>
													<td><?php echo html_output($category["description"]); ?></td>
													<td>
														<a href="<?php echo $files_link; ?>" class="btn btn-sm <?php echo $files_button; ?>"><?php _e('Manage files','cftp_admin'); ?></a>
														<a href="categories.php?action=edit&id=<?php echo $category["id"]; ?>" class="btn btn-primary btn-sm"><?php _e('Edit','cftp_admin'); ?></a>
													</td>
												</tr>
								<?php
												$children = $category['children'];
												if ( !empty( $children ) ) {
													format_category_row( $children );
												}
											}
										}
									}

									if ( $get_categories['count'] > 0 ) {
										format_category_row( $get_categories['arranged'] );
									}
								?>											
							</tbody>
						</table>
			
			
						<nav aria-label="<?php _e('Results navigation','cftp_admin'); ?>">
							<div class="pagination_wrapper text-center">
								<ul class="pagination hide-if-no-paging"></ul>
							</div>
						</nav>
					</div>

		</form>
		<form action="categories.php" name="process_category" id="process_category" method="post">
			<input type="hidden" name="processing" id="processing" value="1">
			<?php
				if ( !empty( $action ) && $action == 'edit' ) {
			?>
					<input type="hidden" name="editing_id" id="editing_id" value="<?php echo $editing; ?>">
			<?php
				}
			?>

					<div class="col-xs-12 col-sm-12 col-md-4">
						<?php include_once( 'categories-form.php' ); ?>
					</div>
				</div>
			</div>

	</div>

</div>

<?php include('footer.php'); ?>
