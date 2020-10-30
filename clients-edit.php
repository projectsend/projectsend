<?php
/**
 * Show the form to edit an existing client.
 *
 * @package		ProjectSend
 * @subpackage	Clients
 *
 */
$allowed_levels = array(9,8,0);
require_once 'bootstrap.php';

$active_nav = 'clients';

/** Create the object */
$edit_client = new \ProjectSend\Classes\Users();

/** Check if the id parameter is on the URI. */
if (isset($_GET['id'])) {
	$client_id = $_GET['id'];
	$page_status = (client_exists_id($client_id)) ? 1 : 2;
}
else {
	$page_status = 0;
}

/**
 * Get the clients information from the database to use on the form.
 * @todo replace when a Client class is made
 */
if ($page_status === 1) {
    $edit_client->get($client_id);
    $client_arguments = $edit_client->getProperties();

	/** Get groups where this client is member */
	$get_groups		= new \ProjectSend\Classes\MembersActions;
	$get_arguments	= array(
							'client_id'	=> $client_id,
						);
	$found_groups	= $get_groups->client_get_groups($get_arguments); 
	
	/** Get current membership requests */
	$get_arguments['denied'] = 0;
	$found_requests	= $get_groups->get_membership_requests($get_arguments); 
}

/**
 * Form type
 */
if (CURRENT_USER_LEVEL != 0) {
	$clients_form_type = 'edit_client';
    $ignore_size = false;
}
else {
	$clients_form_type = 'edit_client_self';
	define('EDITING_SELF_ACCOUNT', true);
	$ignore_size = true;
}

/**
 * Compare the client editing this account to the on the db.
 */
if (CURRENT_USER_LEVEL == 0) {
	if (isset($client_arguments) && CURRENT_USER_USERNAME != $client_arguments['username']) {
        $page_status = 3;
	}
}

if ($_POST) {
	/**
	 * If the user is not an admin, check if the id of the client
	 * that's being edited is the same as the current logged in one.
	 */
	if (CURRENT_USER_LEVEL == 0 || CURRENT_USER_LEVEL == 7) {
		if ($client_id != CURRENT_USER_ID) {
			die();
		}
	}

    /**
	 * Clean the posted form values to be used on the user actions,
	 * and again on the form if validation failed.
	 * Also, overwrites the values gotten from the database so if
	 * validation failed, the new unsaved values are shown to avoid
	 * having to type them again.
	 */
    $client_arguments = array(
        'id' => $client_id,
        'username' => $_POST['username'],
        'role' => 0,
        'name' => $_POST['name'],
        'email' => $_POST['email'],
        'address' => (isset($_POST["address"])) ? $_POST['address'] : null,
        'phone' => (isset($_POST["phone"])) ? $_POST['phone'] : null,
        'contact' => (isset($_POST["contact"])) ? $_POST['contact'] : null,
        'notify_upload' => (isset($_POST["notify_upload"])) ? 1 : 0,
        'max_file_size' => $client_arguments['max_file_size'],
        'active' => $client_arguments['active'],
        'type' => 'edit_client',
    );

	if ( $ignore_size == false ) {
		$client_arguments['max_file_size'] = (isset($_POST["max_file_size"])) ? $_POST["max_file_size"] : null;
	}

	if (CURRENT_USER_LEVEL != 0) {
		$client_arguments['active']	= (isset($_POST["active"])) ? 1 : 0;
	}

	/**
	 * If the password field, or the verification are not completed,
	 * send an empty value to prevent notices.
	 */
	$client_arguments['password'] = (isset($_POST['password'])) ? $_POST['password'] : null;

	/** Validate the information from the posted form. */
    $edit_client->set($client_arguments);
    $edit_client->setType("existing_client");
    if ($edit_client->validate()) {
		$edit_response = $edit_client->edit();

        $edit_groups = (!empty( $_POST['groups_request'] ) ) ? $_POST['groups_request'] : array();
        $memberships	= new \ProjectSend\Classes\MembersActions;
        $arguments		= array(
                                'client_id'		=> $client_id,
                                'group_ids'		=> $edit_groups,
                                'request_by'	=> CURRENT_USER_USERNAME,
                            );

	    if (in_array(CURRENT_USER_LEVEL, [8 ,9])) {
            $memberships->client_edit_groups($arguments);
        } else {
            $memberships->update_membership_requests($arguments);
        }
	}

	$location = BASE_URI . 'clients-edit.php?id=' . $client_id . '&status=' . $edit_response['query'];
	header("Location: $location");
	exit;
}

$page_title = __('Edit client','cftp_admin');
if (isset($client_arguments['username']) && CURRENT_USER_USERNAME == $client_arguments['username']) {
	$page_title = __('My account','cftp_admin');
}

$page_id = 'client_form';

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>

<div class="col-xs-12 col-sm-12 col-lg-6">
	<?php
        /**
         * Get the process state and show the corresponding ok or error message.
         */
		if (isset($_GET['status'])) {
			switch ($_GET['status']) {
                case 1:
                    $msg = __('Client edited correctly.','cftp_admin');
                    if (isset($_GET['is_new'])) {
                        $msg = __('Client created successfuly.','cftp_admin');
                    }
					echo system_message('success',$msg);
				break;
				case 0:
					$msg = __('There was an error. Please try again.','cftp_admin');
					echo system_message('danger',$msg);
				break;
			}
        }
        
        /**
         * Email notification with account information after creating it
         */
        if (isset($_GET['notification'])) {
            switch ($_GET['notification']) {
                case 2:
                    $msg = __('A welcome message was not sent to the new account owner.','cftp_admin');
                    echo system_message('info',$msg);
                break;
                case 1:
                    $msg = __('A welcome message with login information was sent to the new account owner.','cftp_admin');
                    echo system_message('success',$msg);
                break;
                case 0:
                    $msg = __("E-mail notification couldn't be sent.",'cftp_admin');
                    echo system_message('danger',$msg);
                break;
            }
        }
	?>
	<div class="white-box">
		<div class="white-box-interior">
	
			<?php
                // If the form was submited with errors, show them here.
                echo $edit_client->getValidationErrors();

                $direct_access_error = __('This page is not intended to be accessed directly.','cftp_admin');
				if ($page_status === 0) {
					$msg = __('No client was selected.','cftp_admin');
					echo system_message('danger',$msg);
					echo '<p>'.$direct_access_error.'</p>';
				}
				else if ($page_status === 2) {
					$msg = __('There is no client with that ID number.','cftp_admin');
					echo system_message('danger',$msg);
					echo '<p>'.$direct_access_error.'</p>';
				}
				else if ($page_status === 3) {
					$msg = __("Your account type doesn't allow you to access this feature.",'cftp_admin');
					echo system_message('danger',$msg);
				}
				else {
					/**
					 * Include the form.
					 */
					include_once FORMS_DIR . DS . 'clients.php';
				}
			?>

		</div>
	</div>
</div>

<?php
	include_once ADMIN_VIEWS_DIR . DS . 'footer.php';