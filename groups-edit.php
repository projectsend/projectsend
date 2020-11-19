<?php
/**
 * Show the form to edit an existing group.
 *
 * @package		ProjectSend
 * @subpackage	Groups
 *
 */
$allowed_levels = array(9,8);
require_once 'bootstrap.php';

$active_nav = 'groups';

$page_title = __('Edit group','cftp_admin');

$page_id = 'group_form';

include_once ADMIN_VIEWS_DIR . DS . 'header.php';

/** Create the object */
$edit_group = new \ProjectSend\Classes\Groups();

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
    $edit_group->get($group_id);
    $group_arguments = $edit_group->getProperties();
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
        'name'          => $_POST['name'],
        'description'   => $_POST['description'],
        'members'       => (!empty($_POST["members"])) ? $_POST['members'] : null,
        'public'        => (isset($_POST["public"])) ? 1 : 0,
    );

    /** Validate the information from the posted form. */
    $edit_group->set($group_arguments);
    if ($edit_group->validate()) {
        $edit_response = $edit_group->edit();

        $location = BASE_URI . 'groups-edit.php?id=' . $group_id . '&status=' . $edit_response['query'];
        header("Location: $location");
        exit;
    }
}
?>
<div class="row">
    <div class="col-xs-12 col-sm-12 col-lg-6">
        <?php
            /**
             * Get the process state and show the corresponding ok or error message.
             */
            if (isset($_GET['status'])) {
                switch ($_GET['status']) {
                    case 1:
                        $msg = __('Group edited correctly.','cftp_admin');
                        if (isset($_GET['is_new'])) {
                            $msg = __('Group created successfuly.','cftp_admin');
                        }

                        echo system_message('success',$msg);
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
                    // If the form was submited with errors, show them here.
                    echo $edit_group->getValidationErrors();
        
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
</div>
<?php
    include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
