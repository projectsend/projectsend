<?php
/**
 * Show the form to add a new group.
 *
 * @package		ProjectSend
 * @subpackage	Groups
 *
 */
$load_scripts	= array(
	'chosen',
); 

$allowed_levels = array(9,8);
require_once('sys.includes.php');

$active_nav = 'category';

$page_title = __('Create New Category','cftp_admin');

include('header.php');?>
<script type="text/javascript">
$("#process_category").submit(function() {
			clean_form( this );

			is_complete( this.category_name, '<?php echo $validation_no_name; ?>' );

			// show the errors or continue if everything is ok
			if (show_form_errors() == false) { return false; }
		});
</script>

<div id="main">
	<div id="content"> 
<?php
if ($_POST) 
{ 
	
	/**
	 * Applies for both ADDING a new category as well
	 * as editing one but with the form already sent.
	 */
	$category_name			= $_POST['category_name'];
	$category_parent		= $_POST['category_parent'];
	$category_description	= $_POST['category_description'];
	
	$category_object = new CategoriesActions();
	$arguments = array(
						'action' 		=>'add',
						'name'			=> $category_name,
						'parent'		=> $category_parent,
						'description'	=> $category_description,
					);
	
	$validate = $category_object->validate_category( $arguments );
	
	$redirect_status		= 'added';				
	
	
	if ( $validate === 1 ) {
		$process = $category_object->save_category( $arguments );
		//var_dump($process);exit;
		if ( $process['query'] === 1 ) {
			$redirect = true;
			$status = $redirect_status;
		}
		else {
			$msg = __('There was a problem savint to the database.','cftp_admin');
			echo system_message('error', $msg);
		}
	}else if( $validate==2 ){
		$msg = __('Subcategory already exist.','cftp_admin');
		echo system_message('warning', $msg);
	}else {
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
	if ($_POST) {
	$new_group = new GroupActions();

	/**
	 * Clean the posted form values to be used on the groups actions,
	 * and again on the form if validation failed.
	 */
	$add_group_data_name = encode_html($_POST['add_group_form_name']);
	$add_group_data_description = encode_html($_POST['add_group_form_description']);
	$add_group_data_members = $_POST['add_group_form_members'];

	/** Arguments used on validation and group creation. */
	$new_arguments = array(
							'id' => '',
							'name' => $add_group_data_name,
							'description' => $add_group_data_description,
							'members' => $add_group_data_members
						);

	/** Validate the information from the posted form. */
	$new_validate = $new_group->validate_group($new_arguments);
	
	/** Create the group if validation is correct. */
	if ($new_validate == 1) {
		$new_response = $new_group->create_group($new_arguments);
	}
	
}
/** Get all the existing categories */
$get_categories = get_categories();
if(isset($get_categories['categories'])){
	$categories	= $get_categories['categories'];
}
if(isset($get_categories['arranged'])){
	$categories	= $get_categories['arranged'];
}

?>
    
    <!-- Added by B) -------------------->
    <div class="container-fluid">
      <div class="row">
        <div class="col-xs-12 col-xs-offset-0 col-sm-8 col-sm-offset-2 col-md-6 col-md-offset-3 white-box">

	<h2><?php echo $page_title; ?></h2>

				<div class="">

					<?php
						/**
						 * If the form was submited with errors, show them here.
						 */
						if($validate!=2){
							$valid_me->list_errors();
						}
					?>
					
					<?php
						if (isset($new_response)) {
							/**
							 * Get the process state and show the corresponding ok or error messages.
							 */
							switch ($new_response['query']) {
								case 1:
									$msg = __('Organization added correctly.','cftp_admin');
									echo system_message('ok',$msg);
			
									/** Record the action log */
									$new_log_action = new LogActions();
									$log_action_args = array(
															'action' => 23,
															'owner_id' => $global_id,
															'affected_account' => $new_response['new_id'],
															'affected_account_name' => $add_group_data_name
														);
									$new_record_action = $new_log_action->log_action_save($log_action_args);
								break;
								case 0:
									$msg = __('There was an error. Please try again.','cftp_admin');
									echo system_message('error',$msg);
								break;
							}
						}
						else {
							/**
							 * If not $new_response is set, it means we are just entering for the first time.
							 * Include the form.
							 */
							$organization_form_type = 'new_organization';
							$form_information['type']='new_category';
							include('categories-form.php');
						}
					?>

				</div>
			</div>
		</div>
	</div>
</div>
</div>
<?php
	include('footer.php');
?>