<?php
/**
 * Allows to hide, show or delete the files assigend to the
 * selected client.
 *
 * @package		ProjectSend
 * @subpackage	Files
 */
$footable_min = true; // delete this line after finishing pagination on every table
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
			var action = $('#categories_actions').val();
			if (action != 'none') {
				var checks = $("td>input:checkbox").serializeArray(); 
				if (checks.length == 0) { 
					alert('<?php _e('Please select at least one category to proceed.','cftp_admin'); ?>');
					return false; 
				}
				else {
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
if ( isset( $_GET['categories_actions'] ) ) {
	if ( $_GET['categories_actions'] != 'none' ) {
		/** Continue only if 1 or more categories were selected. */
		if ( !empty($_GET['batch'] ) ) {
	
			/**
			 * Make a list of categories to avoid individual queries.
			 */
			$selected_categories	= $_GET['batch'];
	
			$get_categories_delete	= get_categories(
													array(
														'id'		=> $selected_categories,
													)
												);
			foreach ( $get_categories_delete['categories'] as $delete_cat ) {
				$all_categories[$delete_cat['id']] = $delete_cat['name'];
			}
	
			$my_info = get_user_by_username(get_current_user_username());
			$affected_users = 0;
	
			switch($_GET['categories_actions']) {
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
}

/** Get all the existing categories */
$params = array();

$results_show = 'arranged';
/**
 * Add the search terms
 */
if ( isset( $_GET['search'] ) && !empty( $_GET['search'] ) ) {
	$params['search']	= $_GET['search'];
	$results_show = 'categories'; // show from all categories, not the arranged array
}

$params['page']	= ( isset( $_GET["page"] ) ) ? $_GET["page"] : 1;
$page = $params['page'];

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

<div class="col-xs-12 col-sm-12 col-md-8">
	<div class="form_actions_left">
		<div class="form_actions_limit_results">
			<?php show_search_form('categories.php'); ?>
		</div>
	</div>

	<form action="categories.php" class="form-inline" name="selected_categories" id="selected_categories" method="get">

		<div class="form_actions_right form-inline">
			<div class="form_actions">
				<div class="form_actions_submit">
					<div class="form-group group_float">
						<label class="control-label hidden-xs hidden-sm"><i class="glyphicon glyphicon-check"></i> <?php _e('Selected categories actions','cftp_admin'); ?>:</label>
						<select name="categories_actions" id="categories_actions" class="txtfield form-control">
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
							$no_results_message = __('Your search keywords returned no results.','cftp_admin');
							break;
					}
				}
				else {
					$no_results_message = __('There are no categories yet.','cftp_admin');
				}
				echo system_message('error', $no_results_message);
			}

			/**
			 * Generate the table using the class.
			 */
			$table_attributes	= array(
										'id'		=> 'categories_tbl',
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
											'sort_url'		=> 'name',
											'sort_default'	=> true,
											'content'		=> __('Name','cftp_admin'),
										),
										array(
											'content'		=> __('Files','cftp_admin'),
										),
										array(
											'content'		=> __('Description','cftp_admin'),
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

			/**
			 * Having the formatting function here seems more convenient
			 * as the HTML layout is easier to edit on it's real context.
			 */
			$c = 0;

			$pagination_page	= $page;
			$pagination_start	= ( $pagination_page - 1 ) * RESULTS_PER_PAGE;
			$limit_start		= $pagination_start;
			$limit_number		= RESULTS_PER_PAGE;
			$limit_amount		= $limit_start + RESULTS_PER_PAGE;

			$i = 0;
			
			function format_category_row( $arranged ) {
				global $table, $c, $i, $page, $pagination_page, $pagination_start, $limit_start, $limit_number, $limit_amount;

				$c++;
				if ( !empty( $arranged ) ) {
					foreach ( $arranged as $category ) {

						/**
						 * Horrible hacky way to limit results on the table.
						 * The real filtered results should come from the
						 * 'arranged' array of the get_categories results.
						 */
						$i++;
						if  ( $i > $limit_start && $i <= $limit_amount ) {

							$table->add_row();

							$depth = ( $category['depth'] > 0 ) ? str_repeat( '&mdash;', $category['depth'] ) . ' ' : false;

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
							
							$count_format = '<span class="label label-' . $class . '">' . $total . '</span>';
							
							$tbody_cells = array(
													array(
															'checkbox'		=> true,
															'value'			=> $category["id"],
														),
													array(
															'content'		=> $depth . html_output($category["name"]),
															'attributes'	=> array(
																					'data-value'	=> $c,
																				),
														),
													array(
															'content'		=> $count_format,
														),
													array(
															'content'		=> html_output( $category["description"] ),
														),
													array(
															'content'		=> '<a href="'. $files_link .'" class="btn btn-sm ' . $files_button . '">' . __('Manage files','cftp_admin') . '</a>',
														),
													array(
															'content'		=> '<a href="categories.php?action=edit&id=' . $category["id"] .'" class="btn btn-primary btn-sm"><i class="fa fa-pencil"></i><span class="button_label">' . __('Edit','cftp_admin') . '</span></a>'
														),
												);
							foreach ( $tbody_cells as $cell ) {
								$table->add_cell( $cell );
							}
							
							$table->end_row();
						}

						$children = $category['children'];
						if ( !empty( $children ) ) {
							format_category_row( $children );
						}
					}
				}
			}

			if ( $get_categories['count'] > 0 ) {
				format_category_row( $get_categories[$results_show] );
			}

			echo $table->render();

			/**
			 * PAGINATION
			 */
			$pagination_args = array(
									'link'		=> 'categories.php',
									'current'	=> $params['page'],
									'pages'		=> ceil( $get_categories['count'] / RESULTS_PER_PAGE ),
								);
			
			echo $table->pagination( $pagination_args );
		?>
	</form>
</div>
<div class="col-xs-12 col-sm-12 col-md-4">
	<form action="categories.php" class="form-horizontal" name="process_category" id="process_category" method="post">
		<input type="hidden" name="processing" id="processing" value="1">
		<?php
			if ( !empty( $action ) && $action == 'edit' ) {
		?>
				<input type="hidden" name="editing_id" id="editing_id" value="<?php echo $editing; ?>">
		<?php
			}
		?>

		<?php include_once( 'categories-form.php' ); ?>
	</form>
</div>

<?php
	include('footer.php');