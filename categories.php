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

$allowed_levels = array(9,8);
require_once('sys.includes.php');

$active_nav = 'files';
$cc_active_page = 'Categories';

$page_title = __('Categories Administration','cftp_admin');

$current_level = get_current_user_level();

include('header.php');

?>

<script type="text/javascript">
	$(document).ready( function() {
		$("#do_action").click(function() {
			var file_count = 0;
			$("input[name='categories[]']").each( function () { 
			
			if($(this).prop("checked") == true){
				var flag = $(this).closest('td').siblings().find('span').data('filecount');
				file_count = file_count+flag;
			}
			});
			var checks = $("td>input:checkbox").serializeArray(); 
			if (checks.length == 0) { 
				alert('<?php _e('Please select at least one category to proceed.','cftp_admin'); ?>');
				return false; 
			}
			else {
				var action = $('#categories_actions').val();
				if (action == 'delete') {
					var msg_1 = '<?php _e("You are about to delete",'cftp_admin'); ?>';
					if(file_count > 0) {
						var msg_2 = '<?php _e("categories that contains files. Are you sure you want to continue?",'cftp_admin'); ?>';
					}
					else {
					var msg_2 = '<?php _e("categories. Are you sure you want to continue?",'cftp_admin'); ?>';
					}
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
<div id="content"> 
    
    <!-- Added by B) -------------------->
    <div class="container-fluid">
      <div class="row">
        <div class="col-md-12">
	
	<h2 class="page-title txt-color-blueDark"><?php echo $page_title; ?></h2>
<a href="category-add.php" class="btn btn-sm btn-primary right-btn">New Category</a></div>
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
						$check_category	= $this_category->check_category($category);
						if($check_category['status']==true){
							$delete_category	= $this_category->delete_category($category);
							$msg = __($check_category['msg'],'cftp_admin');
							echo system_message('ok',$msg);
						}else{
							$msg = __($check_category['msg'],'cftp_admin');
							echo system_message('error',$msg);
						}
					}
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
	$params['search']= isset($_POST['search'])?$_POST['search']:'';
	$get_categories = get_categories($params);
		if(isset($get_categories['categories'])){
			$categories	= $get_categories['categories'];
		}
		if(isset($get_categories['arranged'])){
			$categories	= $get_categories['arranged'];
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
				$msg = __('There was a problem savint to the database.','cftp_admin');
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
				<form action="categories.php" name="cat_search" method="post" class="form-inline">
					<div class="form-group group_float">
						<input type="text" name="search" id="search" value="<?php echo isset($_POST['search'])?$_POST['search']:''; ?>" class="txtfield form_actions_search_box form-control" />
					</div>
					<button type="submit" id="btn_proceed_search" class="btn btn-sm btn-default"><?php _e('Search','cftp_admin'); ?></button>
				</form>
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
				<p class="form_count_total"><?php _e('Showing','cftp_admin'); ?>: <span><?php echo $get_categories['count']; ?> <?php _e('categories','cftp_admin'); ?></span></p>
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
					<div class="col-xs-12 col-sm-12 col-md-12">
                    <section id="no-more-tables">
						<table id="categories_tbl" class="table table-striped table-bordered table-hover dataTable no-footer" data-page-size="<?php echo FOOTABLE_PAGING_NUMBER; ?>">
							<thead>
								<tr>
									<th class="td_checkbox" data-sort-ignore="true">
										<input type="checkbox" name="select_all" id="select_all" value="0" />
									</th>
									<th data-sort-ignore="true"><?php _e('Name','cftp_admin'); ?></th>
									<th data-sort-ignore="true"><?php _e('Files*','cftp_admin'); ?></th>
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
													<td data-value="<?php echo $c; ?>">
													<a href="category-edit.php?action=edit&id=<?php echo $category["id"]; ?>"><?php _e($depth . html_output($category["name"]),'cftp_admin'); ?></a></td>
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
														<span class="label label-<?php echo $class; ?>" data-filecount="<?php echo	 $total; ?>">
															<?php echo	 $total; ?>
														</span>
													</td>
													<td><?php echo html_output($category["description"]); ?></td>
													<td>
														<a href="<?php echo $files_link; ?>" class="btn btn-sm <?php echo $files_button; ?>"><?php _e('Manage files','cftp_admin'); ?></a>
														
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
										if(!empty($get_categories['arranged'])){
											format_category_row( $get_categories['arranged'] );
										}
										else{
											format_category_row( $get_categories['categories'] );
										}
									}
								?>											
							</tbody>
						</table>
			    <p>*NOTE: Count does not include files in subfolders.</p>
                    </section>
			
			
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
						<?php //include_once( 'categories-form.php' ); ?>
					</div>
         </form>
				</div>
			</div>

	</div>

</div>
</div>
</div>
</div>

<?php include('footer.php'); ?>


<style type="text/css">
/*-------------------- Responsive table by B) -----------------------*/
@media only screen and (max-width: 1200px) {
    #content {
        padding-top:30px;
    }
    
    /* Force table to not be like tables anymore */
    #no-more-tables table, 
    #no-more-tables thead, 
    #no-more-tables tbody, 
    #no-more-tables th, 
    #no-more-tables td, 
    #no-more-tables tr { 
        display: block; 
    }
 
    /* Hide table headers (but not display: none;, for accessibility) */
    #no-more-tables thead tr { 
        position: absolute;
        top: -9999px;
        left: -9999px;
    }
 
    #no-more-tables tr { border: 1px solid #ccc; }
 
    #no-more-tables td { 
        /* Behave  like a "row" */
        border: none;
        border-bottom: 1px solid #eee; 
        position: relative;
        padding-left: 50%; 
        white-space: normal;
        text-align:left;
    }
 
    #no-more-tables td:before { 
        /* Now like a table header */
        position: absolute;
        /* Top/left values mimic padding */
        top: 6px;
        left: 6px;
        width: 45%; 
        padding-right: 10px; 
        white-space: nowrap;
        text-align:left;
        font-weight: bold;
    }
 
    /*
    Label the data
    */

    
    td:nth-of-type(1):before { content: ""; }
    td:nth-of-type(2):before { content: "Name"; }
    td:nth-of-type(3):before { content: "Files"; }
    td:nth-of-type(4):before { content: "Description"; }
    td:nth-of-type(5):before { content: "Actions"; }
}
/*-------------------- Responsive table End--------------------------*/
</style>
