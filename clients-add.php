<?php
/**
 * Show the form to add a new client.
 *
 * @package		ProjectSend
 * @subpackage	Clients
 *
 */
$allowed_levels = array(9,8);
require_once('bootstrap.php');

$active_nav = 'clients';

$page_title = __('Add client','cftp_admin');

include_once ADMIN_TEMPLATES_DIR . DS . 'header.php';

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
    $new_client = new \ProjectSend\ClientActions();

    /**
     * Clean the posted form values to be used on the clients actions,
     * and again on the form if validation failed.
     */
    $client_arguments = array(
        'id'	    		=> '',
        'username'	    	=> encode_html($_POST['username']),
        'password'		    => $_POST['password'],
        //'password_repeat' => $_POST['password_repeat'],
        'name'	    		=> encode_html($_POST['name']),
        'email'		    	=> encode_html($_POST['email']),
        'address'		    => (isset($_POST["address"])) ? encode_html($_POST['address']) : '',
        'phone'	    		=> (isset($_POST["phone"])) ? encode_html($_POST['phone']) : '',
        'contact'	    	=> (isset($_POST["contact"])) ? encode_html($_POST['contact']) : '',
        'max_file_size'	    => (isset($_POST["max_file_size"])) ? encode_html($_POST['max_file_size']) : '',
        'notify_upload'    	=> (isset($_POST["notify_upload"])) ? 1 : 0,
        'notify_account' 	=> (isset($_POST["notify_account"])) ? 1 : 0,
        'active'	    	=> (isset($_POST["active"])) ? 1 : 0,
        'type'		    	=> 'new_client',
    );

    /** Validate the information from the posted form. */
    $new_validate = $new_client->validate_client($client_arguments);
    
    /** Create the client if validation is correct. */
    if ($new_validate == 1) {
        $new_response = $new_client->create_client($client_arguments);
        
        $add_to_groups = (!empty( $_POST['groups_request'] ) ) ? $_POST['groups_request'] : '';
        if ( !empty( $add_to_groups ) ) {
            array_map('encode_html', $add_to_groups);
            $memberships	= new \ProjectSend\MembersActions;
            $arguments		= array(
                                    'client_id'	=> $new_response['new_id'],
                                    'group_ids'	=> $add_to_groups,
                                    'added_by'	=> CURRENT_USER_USERNAME,
                                );
    
            $memberships->client_add_to_groups($arguments);
        }
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
                    switch ($new_response['actions']) {
                        case 1:
                            $msg = __('Client added correctly.','cftp_admin');
                            echo system_message('success',$msg);
    
                            /** Record the action log */
                            global $logger;
                            $log_action_args = array(
                                                    'action' => 3,
                                                    'owner_id' => CURRENT_USER_ID,
                                                    'affected_account' => $new_response['new_id'],
                                                    'affected_account_name' => $client_arguments['name']
                                                );
                            $new_record_action = $logger->add_entry($log_action_args);
                        break;
                        case 0:
                            $msg = __('There was an error. Please try again.','cftp_admin');
                            echo system_message('danger',$msg);
                        break;
                    }
                    /**
                     * Show the ok or error message for the email notification.
                     */
                    switch ($new_response['email']) {
                        case 2:
                            $msg = __('A welcome message was not sent to your client.','cftp_admin');
                            echo system_message('success',$msg);
                        break;
                        case 1:
                            $msg = __('A welcome message with login information was sent to your client.','cftp_admin');
                            echo system_message('success',$msg);
                        break;
                        case 0:
                            $msg = __("E-mail notification couldn't be sent.",'cftp_admin');
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
    include_once ADMIN_TEMPLATES_DIR . DS . 'footer.php';
