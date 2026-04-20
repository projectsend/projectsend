<?php
/**
 * Show the form to edit an existing group.
 */
require_once 'bootstrap.php';

$active_nav = 'groups';

$page_title = __('Edit group', 'cftp_admin');

$page_id = 'group_form';

include_once ADMIN_VIEWS_DIR . DS . 'header.php';

/** Check if the id parameter is on the URI. */
if (!isset($_GET['id'])) {
    exit_with_error_code(403);
}

$group_id = $_GET['id'];
if (!group_exists_id($group_id)) {
    exit_with_error_code(403);
}

/** Create the object */
$edit_group = new \ProjectSend\Classes\Groups($group_id);

/** Check if user can edit this group */
if (!$edit_group->canUserEdit()) {
    exit_with_error_code(403);
}

$group_arguments = $edit_group->getProperties();

if ($_POST) {
    /**
     * Clean the posted form values to be used on the groups actions,
     * and again on the form if validation failed.
     * Also, overwrites the values gotten from the database so if
     * validation failed, the new unsaved values are shown to avoid
     * having to type them again.
     */
    $group_arguments = array(
        'id' => $group_id,
        'name' => $_POST['name'],
        'description' => $_POST['description'],
        'members' => (!empty($_POST["members"])) ? $_POST['members'] : null,
        'public' => (isset($_POST["public"])) ? 1 : 0,
    );

    /** Validate the information from the posted form. */
    $edit_group->set($group_arguments);
    $edit_response = $edit_group->edit();

    if ($edit_response['status'] === 'success') {
        $flash->success($edit_response['message']);
    } else {
        $flash->error($edit_response['message']);
    }

    $location = BASE_URI . 'groups-edit.php?id=' . $group_id;
    ps_redirect($location);
}
?>
<div class="row">
    <div class="col-12 col-sm-12 col-lg-6">
        <div class="white-box">
            <div class="white-box-interior">
                <?php
                // If the form was submitted with errors, show them here.
                echo $edit_group->getValidationErrors();

                $groups_form_type = 'edit_group';
                include_once FORMS_DIR . DS . 'groups.php';
                ?>
            </div>
        </div>
    </div>
</div>
<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
