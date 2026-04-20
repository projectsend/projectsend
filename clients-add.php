<?php
/**
 * Show the form to add a new client.
 */
require_once 'bootstrap.php';
check_access_enhanced(['create_clients', 'manage_clients'], 'any');

$active_nav = 'clients';

$page_title = __('Add client', 'cftp_admin');

$page_id = 'client_form';

$new_client = new \ProjectSend\Classes\Users();

include_once ADMIN_VIEWS_DIR . DS . 'header.php';

// Set checkboxes as 1 to default them to checked when first entering the form
$client_arguments = array(
    'notify_upload' => 1,
    'active' => 1,
    'notify_account' => 1,
    'require_password_change' => 1,
);

if ($_POST) {
    /**
     * Clean the posted form values to be used on the clients actions,
     * and again on the form if validation failed.
     */
    $client_arguments = array(
        'username' => $_POST['username'],
        'password' => $_POST['password'],
        'name' => $_POST['name'],
        'email' => $_POST['email'],
        'role_id' => \ProjectSend\Classes\Roles::getClientRoleId(),
        'address' => (isset($_POST["address"])) ? $_POST['address'] : '',
        'phone' => (isset($_POST["phone"])) ? $_POST['phone'] : '',
        'contact' => (isset($_POST["contact"])) ? $_POST['contact'] : '',
        'max_file_size' => (isset($_POST["max_file_size"])) ? $_POST['max_file_size'] : '',
        'max_disk_quota' => (isset($_POST["max_disk_quota"])) ? $_POST['max_disk_quota'] : '',
        'notify_upload' => (isset($_POST["notify_upload"])) ? 1 : 0,
        'notify_account' => (isset($_POST["notify_account"])) ? 1 : 0,
        'active' => (isset($_POST["active"])) ? 1 : 0,
        'can_upload_public' => (isset($_POST["can_upload_public"])) ? 1 : 0,
        'require_password_change' => (isset($_POST["require_password_change"])) ? true : false,
        'type' => 'new_client',
    );

    // Process custom fields
    $custom_field_data = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'custom_field_') === 0) {
            $field_id = str_replace('custom_field_', '', $key);
            $custom_field_data[$field_id] = $value;
        }
    }

    // Validate the information from the posted form.
    $new_client->setType('new_client');
    $new_client->set($client_arguments);
    $new_client->custom_field_data = $custom_field_data;
    $create = $new_client->create();

    // Record the action log
    $logger = new \ProjectSend\Classes\ActionsLog;
    $record = $logger->addEntry([
        'action' => 3,
        'owner_user' => CURRENT_USER_USERNAME,
        'owner_id' => CURRENT_USER_ID,
        'affected_account' => $new_client->id,
        'affected_account_name' => $new_client->name
    ]);

    $add_to_groups = (!empty($_POST['groups_request'])) ? $_POST['groups_request'] : '';
    if (!empty($add_to_groups)) {
        array_map('encode_html', $add_to_groups);
        $memberships = new \ProjectSend\Classes\GroupsMemberships;
        $memberships->clientAddToGroups([
            'client_id' => $new_client->getId(),
            'group_ids' => $add_to_groups,
            'added_by' => CURRENT_USER_USERNAME,
        ]);
    }

    if ($create['status'] === 'success') {
        $flash->success($create['message']);

        if (isset($create['email'])) {
            switch ($create['email']) {
                case 2:
                    $flash->success(__('A welcome message was not sent to the new account owner.', 'cftp_admin'));
                    break;
                case 1:
                    $flash->success(__('A welcome message with login information was sent to the new account owner.', 'cftp_admin'));
                    break;
                case 0:
                    $flash->error(__("E-mail notification couldn't be sent.", 'cftp_admin'));
                    break;
            }
        }

        ps_redirect(BASE_URI . 'clients-edit.php?id=' . $create['id']);
    } else {
        // Don't redirect on error - let the page continue to render with form values intact
    }
}
?>

<div class="row">
    <div class="col-12 col-sm-12 col-lg-6">
        <div class="white-box">
            <div class="white-box-interior">
                <?php
                // Display validation errors if form submission failed
                if (!empty($create['errors'])) {
                    echo $create['errors'];
                }

                // If the form was submitted with errors, show them here.
                $clients_form_type = 'new_client';
                include_once FORMS_DIR . DS . 'clients.php';
                ?>
            </div>
        </div>
    </div>
</div>
<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
