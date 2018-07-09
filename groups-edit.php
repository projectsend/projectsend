<?php
/**
 * Show the form to edit an existing group.
 *
 * @package		ProjectSend
 * @subpackage	Groups
 *
 */
$allowed_levels = array(9,8);
require_once('bootstrap.php');

$active_nav = 'groups';

$page_title = __('Edit group','cftp_admin');

include_once ADMIN_TEMPLATES_DIR . DS . 'header.php';

/** Create the object */
$edit_group = new \ProjectSend\GroupActions();

/** Check if the id parameter is on the URI. */
if (isset($_GET['id'])) {
	$group_id = $_GET['id'];
	/**
	 * Check if the id corresponds to a real group.
	 * Return 1 if true, 2 if false.
	 **/
	$page_status = (group_exists_id($group_id)) ? 1 : 2;
}
else {
	/**
	 * Return 0 if the id is not set.
	 */
	$page_status = 0;
}

/**
 * Get the group information from the database to use on the form.
 * @todo replace when a Group class is made
 */
if ($page_status === 1) {
    // Group information
    $group_arguments = get_group_by_id($group_id);

    // Group members
    $current_members = get_group_members($group_id);
}

if ($_POST) {
	/**
	 * Clean the posted form values to be used on the groups actions,
	 * and again on the form if validation failed.
	 * Also, overwrites the values gotten from the database so if
	 * validation failed, the new unsaved values are shown to avoid
	 * having to type them again.
	 */
    $group_arguments = array(
        'id'            => $group_id,
        'name'          => encode_html($_POST['name']),
        'description'   => encode_html($_POST['description']),
        'members'       => (!empty($_POST["members"])) ? $_POST['members'] : '',
        'public'        => (isset($_POST["public"])) ? 1 : 0,
    );

	/** Validate the information from the posted form. */
	$edit_validate = $edit_group->validate_group($group_arguments);

	/** Create the group if validation is correct. */
	if ($edit_validate == 1) {
		$edit_response = $edit_group->edit_group($group_arguments);
	}

	$location = BASE_URI . 'groups-edit.php?id=' . $group_id . '&status=' . $edit_response['query'];
	header("Location: $location");
	die();
}
?>

<div class="col-xs-12 col-sm-12 col-lg-6">
	<?php
		if (isset($_GET['status'])) {
			switch ($_GET['status']) {
				case 1:
					$msg = __('Group edited correctly.','cftp_admin');
					echo system_message('success',$msg);

					/** Record the action log */
					global $logger;
					$log_action_args = array(
											'action' => 15,
											'owner_id' => CURRENT_USER_ID,
											'affected_account' => $group_id,
											'affected_account_name' => $group_arguments['name']
										);
					$new_record_action = $logger->add_entry($log_action_args);
				break;
				case 0:
					$msg = __('There was an error. Please try again.','cftp_admin');
					echo system_message('danger',$msg);
				break;
			}
		}
	?>

	<div class="white-box">
		<div class="white-box-interior">
			<?php
				/**
				 * If the form was submited with errors, show them here.
				 */
				$validation->list_errors();
			?>

			<?php
				$direct_access_error = __('This page is not intended to be accessed directly.','cftp_admin');
				if ($page_status === 0) {
					$msg = __('No group was selected.','cftp_admin');
					echo system_message('danger',$msg);
					echo '<p>'.$direct_access_error.'</p>';
				}
				else if ($page_status === 2) {
					$msg = __('There is no group with that ID number.','cftp_admin');
					echo system_message('danger',$msg);
					echo '<p>'.$direct_access_error.'</p>';
				}
				else {
					/**
					 * Include the form.
					 */
					$groups_form_type = 'edit_group';
					include_once FORMS_DIR . DS . 'groups.php';
				}
			?>
		</div>
	</div>
</div>

<?php
	include_once ADMIN_TEMPLATES_DIR . DS . 'footer.php';
