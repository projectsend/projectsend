<?php
/**
 * Show the form to add a new group.
 *
 * @package		ProjectSend
 * @subpackage	Groups
 *
 */
$allowed_levels = array(9,8);
require_once('sys.includes.php');

$active_nav = 'groups';

$page_title = __('Add clients group','cftp_admin');

include_once ADMIN_TEMPLATES_DIR . DS . 'header.php';

if ($_POST) {
	$new_group = new ProjectSend\GroupActions();

	/**
	 * Clean the posted form values to be used on the groups actions,
	 * and again on the form if validation failed.
	 */
	$add_group_data_name = encode_html($_POST['add_group_form_name']);
	$add_group_data_description = encode_html($_POST['add_group_form_description']);
	$add_group_data_members = ( !empty( $_POST['add_group_form_members'] ) ) ? $_POST['add_group_form_members'] : null;
	$add_group_data_public = (isset($_POST["add_group_form_public"])) ? 1 : 0;

	/** Arguments used on validation and group creation. */
	$new_arguments = array(
							'id' => '',
							'name' => $add_group_data_name,
							'description' => $add_group_data_description,
							'members' => $add_group_data_members,
							'public' => $add_group_data_public,
						);

	/** Validate the information from the posted form. */
	$new_validate = $new_group->validate_group($new_arguments);

	/** Create the group if validation is correct. */
	if ($new_validate == 1) {
		$new_response = $new_group->create_group($new_arguments);
	}

}
?>

<div class="col-xs-12 col-sm-12 col-lg-6">
	<div class="white-box">
		<div class="white-box-interior">

			<?php
				/**
				 * If the form was submited with errors, show them here.
				 */
				$validation->list_errors();
			?>

			<?php
				if (isset($new_response)) {
					/**
					 * Get the process state and show the corresponding ok or error messages.
					 */
					switch ($new_response['query']) {
						case 1:
							$msg = __('Group added correctly.','cftp_admin');
							echo system_message('ok',$msg);

							/** Record the action log */
							$new_log_action = new ProjectSend\LogActions();
							$log_action_args = array(
													'action' => 23,
													'owner_id' => CURRENT_USER_ID,
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
					$groups_form_type = 'new_group';
					include_once FORMS_DIR . DS . 'groups.php';
				}
			?>

		</div>
	</div>
</div>

<?php
	include_once ADMIN_TEMPLATES_DIR . DS . 'footer.php';
