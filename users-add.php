<?php
/**
 * Show the form to add a new system user.
 *
 * @package		ProjectSend
 * @subpackage	Users
 *
 */
$allowed_levels = array(9);
require_once 'bootstrap.php';

if(!check_for_admin()) {
    return;
}

$active_nav = 'users';

$page_title = __('Add system user','cftp_admin');

$page_id = 'user_form';

$new_user = new \ProjectSend\Classes\Users($dbh);

include_once ADMIN_VIEWS_DIR . DS . 'header.php';

/**
 * Set checkboxes as 1 to defaul them to checked when first entering
 * the form
 */
$user_arguments = array(
    'active' => 1,
    'notify_account' => 1,
);

if ($_POST) {
	/**
	 * Clean the posted form values to be used on the user actions,
	 * and again on the form if validation failed.
	 */
    $user_arguments = array(
        'username' => $_POST['username'],
        'password' => $_POST['password'],
        'name' => $_POST['name'],
        'email' => $_POST['email'],
        'role' => $_POST['level'],
        'max_file_size' => (isset($_POST["max_file_size"])) ? $_POST['max_file_size'] : '',
        'notify_account' => (isset($_POST["notify_account"])) ? 1 : 0,
        'active' => (isset($_POST["active"])) ? 1 : 0,
        'type' => 'new_user',
    );

	/** Validate the information from the posted form. */
    /** Create the user if validation is correct. */
    $new_user->setType('new_user');
    $new_user->set($user_arguments);
	if ($new_user->validate()) {
        $new_response = $new_user->create();

        if (!empty($new_response['id'])) {
            $rediret_to = BASE_URI . 'users-edit.php?id=' . $new_response['id'] . '&status=' . $new_response['query'] . '&is_new=1&notification=' . $new_response['email'];
            header('Location:' . $rediret_to);
            exit;
        }
    }
}
?>
<div class="col-xs-12 col-sm-12 col-lg-6">
	<div class="white-box">
		<div class="white-box-interior">
		
			<?php
                // If the form was submited with errors, show them here.
                echo $new_user->getValidationErrors();

				if (isset($new_response)) {
					/**
					 * Get the process state and show the corresponding ok or error message.
					 */
					switch ($new_response['query']) {
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
					$user_form_type = 'new_user';
					include_once FORMS_DIR . DS . 'users.php';
				}
			?>

		</div>
	</div>
</div>

<?php
	include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
