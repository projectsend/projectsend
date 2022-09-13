<?php
/**
 * Show the form to add a new group.
 *
 * @package		ProjectSend
 * @subpackage	Groups
 *
 */
$allowed_levels = array(9,8);
require_once 'bootstrap.php';

$active_nav = 'groups';

$page_title = __('Add clients group','cftp_admin');

$page_id = 'group_form';

include_once ADMIN_VIEWS_DIR . DS . 'header.php';

$group = new \ProjectSend\Classes\Groups();

if ($_POST) {
    /**
     * Clean the posted form values to be used on the groups actions,
     * and again on the form if validation failed.
     */
    $group_arguments = [
        'name' => $_POST['name'],
        'description' => $_POST['description'],
        'members' => ( !empty( $_POST['members'] ) ) ? $_POST['members'] : null,
        'public' => (isset($_POST["public"])) ? 1 : 0,
    ];

    /** Validate the information from the posted form. */
    $group->set($group_arguments);
    $create = $group->create();

    if (!empty($create['id'])) {
        $flash->success(__('Group created successfully'));
        $redirect_to = BASE_URI . 'groups-edit.php?id=' . $create['id'];
    } else {
        $flash->error(__('There was an error saving to the database'));
        $redirect_to = BASE_URI . 'groups-add.php';
    }

    header('Location:' . $redirect_to);
    exit;
}
?>
<div class="row">
    <div class="col-xs-12 col-sm-12 col-lg-6">
        <div class="white-box">
            <div class="white-box-interior">
                <?php
                    // If the form was submitted with errors, show them here.
                    echo $group->getValidationErrors();

                    if (isset($create)) {
                        switch ($create['query']) {
                            case 0:
                                $msg = __('There was an error. Please try again.','cftp_admin');
                                echo system_message('danger',$msg);
                            break;
                        }
                    }

                    // Include the form.
                    $groups_form_type = 'new_group';
                    include_once FORMS_DIR . DS . 'groups.php';
                ?>
            </div>
        </div>
    </div>
</div>
<?php
    include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
