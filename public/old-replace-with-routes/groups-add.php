<?php
/**
 * Show the form to add a new group.
 *
 * @package		ProjectSend
 * @subpackage	Groups
 *
 */
$allowed_levels = array(9,8);
require_once('bootstrap.php');

$active_nav = 'groups';

$page_title = __('Add clients group','cftp_admin');

include_once ADMIN_VIEWS_DIR . DS . 'header.php';

if ($_POST) {
	$new_group = new \ProjectSend\GroupActions();

	/**
	 * Clean the posted form values to be used on the groups actions,
	 * and again on the form if validation failed.
	 */
    $group_arguments = [
        'id'            => '',
        'name'          => encode_html($_POST['name']),
        'description'   => encode_html($_POST['description']),
        'members'       => ( !empty( $_POST['members'] ) ) ? $_POST['members'] : null,
        'public'        => (isset($_POST["public"])) ? 1 : 0,
    ];

	/** Validate the information from the posted form. */
	$new_validate = $new_group->validate_group($group_arguments);

	/** Create the group if validation is correct. */
	if ($new_validate == 1) {
		$new_response = $new_group->create_group($group_arguments);
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
							echo system_message('success',$msg);

							/** Record the action log */
							global $logger;
							$log_action_args = array(
													'action' => 23,
													'owner_id' => CURRENT_USER_ID,
													'affected_account' => $new_response['new_id'],
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
	include_once ADMIN_VIEWS_DIR . DS . 'footer.php';