<?php
/**
 * Show the form to add a new client.
 *
 * @package		ProjectSend
 * @subpackage	Clients
 *
 */
$allowed_levels = array(9,8);
require_once 'bootstrap.php';

$active_nav = 'clients';

$page_title = __('Add client','cftp_admin');

$page_id = 'client_form';

$new_client = new \ProjectSend\Classes\Users();

include_once ADMIN_VIEWS_DIR . DS . 'header.php';

/**
 * Set checkboxes as 1 to default them to checked when first entering
 * the form
 */
$client_arguments = array(
    'notify_upload'     => 1,
    'active'            => 1,
    'notify_account'    => 1,
);

if ($_POST) {
    /**
     * Clean the posted form values to be used on the clients actions,
     * and again on the form if validation failed.
     */
    $client_arguments = array(
        'username'=> $_POST['username'],
        'password' => $_POST['password'],
        'name' => $_POST['name'],
        'email' => $_POST['email'],
        'address' => (isset($_POST["address"])) ? $_POST['address'] : '',
        'phone' => (isset($_POST["phone"])) ? $_POST['phone'] : '',
        'contact' => (isset($_POST["contact"])) ? $_POST['contact'] : '',
        'max_file_size' => (isset($_POST["max_file_size"])) ? $_POST['max_file_size'] : '',
        'notify_upload' => (isset($_POST["notify_upload"])) ? 1 : 0,
        'notify_account' => (isset($_POST["notify_account"])) ? 1 : 0,
        'active' => (isset($_POST["active"])) ? 1 : 0,
        'require_password_change' => (isset($_POST["require_password_change"])) ? true : false,
        'type' => 'new_client',
    );

    /** Validate the information from the posted form. */
    /** Create the user if validation is correct. */
    $new_client->setType('new_client');
    $new_client->set($client_arguments);
	if ($new_client->validate()) {
        $new_response = $new_client->create();

        /** Record the action log */
        $logger = new \ProjectSend\Classes\ActionsLog;
        $record = $logger->addEntry([
            'action' => 3,
            'owner_user' => $new_client->username,
            'owner_id' => CURRENT_USER_ID,
            'affected_account' => $new_client->id,
            'affected_account_name' => $new_client->name
        ]);
    
        $add_to_groups = (!empty( $_POST['groups_request'] ) ) ? $_POST['groups_request'] : '';
        if ( !empty( $add_to_groups ) ) {
            array_map('encode_html', $add_to_groups);
            $memberships	= new \ProjectSend\Classes\MembersActions;
            $arguments		= array(
                                    'client_id'	=> $new_client->getId(),
                                    'group_ids'	=> $add_to_groups,
                                    'added_by'	=> CURRENT_USER_USERNAME,
                                );
    
            $memberships->client_add_to_groups($arguments);
        }

        if (!empty($new_response['id'])) {
            $rediret_to = BASE_URI . 'clients-edit.php?id=' . $new_response['id'] . '&status=' . $new_response['query'] . '&is_new=1&notification=' . $new_response['email'];
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
                echo $new_client->getValidationErrors();

                if (isset($new_response)) {
                    /**
                     * Get the process state and show the corresponding ok or error messages.
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
                    $clients_form_type = 'new_client';
                    include_once FORMS_DIR . DS . 'clients.php';
                }
            ?>
        </div>
    </div>
</div>

<?php
    include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
