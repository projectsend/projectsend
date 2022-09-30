<?php
/**
 * Show the form to add a new asset.
 *
 */
$allowed_levels = array(9);
require_once 'bootstrap.php';

$asset = new \ProjectSend\Classes\CustomAsset();

$language = (!empty($_POST)) ? $_POST['language'] : $_GET['language'];
if (!in_array($language, array_keys(get_asset_languages()))) {
    exit_with_error_code(403);
}

$active_nav = 'tools';

$page_title = sprintf(__('Add new %s asset', 'cftp_admin'), format_asset_language_name($language));

$page_id = 'asset_editor';

add_codemirror_assets();

include_once ADMIN_VIEWS_DIR . DS . 'header.php';

if ($_POST) {
    /**
     * Clean the posted form values to be used on the actions,
     * and again on the form if validation failed.
     */
    $asset_arguments = [
        'title' => $_POST['title'],
        'content' => $_POST['content'],
        'language' => $language,
        'location' => $_POST['location'],
        'position' => $_POST['position'],
        'enabled' => (isset($_POST["enabled"])) ? 1 : 0,
    ];

    /** Validate the information from the posted form. */
    $asset->set($asset_arguments);
    $create = $asset->create();

    if (!empty($new_response['id'])) {
        $redirect_to = BASE_URI . 'custom-assets-edit.php?id=' . $new_response['id'];
    }

    if (!empty($create['id'])) {
        $flash->success(__('Asset created successfully'));
        $redirect_to = BASE_URI . 'custom-assets-edit.php?id=' . $create['id'];
    } else {
        $flash->error(__('There was an error saving to the database'));
        $redirect_to = BASE_URI . 'custom-assets-add.php';
    }

    ps_redirect($redirect_to);
}
?>
<div class="row">
    <div class="col-12 col-sm-12 col-lg-6">
        <div class="white-box">
            <div class="white-box-interior">
                <?php
                // If the form was submitted with errors, show them here.
                echo $asset->getValidationErrors();

                $asset_form_type = 'new';
                include_once FORMS_DIR . DS . 'assets.php';
                ?>
            </div>
        </div>
    </div>
</div>
<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
