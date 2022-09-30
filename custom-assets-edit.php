<?php
/**
 * Show the form to add a new asset.
 *
 */
$allowed_levels = array(9);
require_once 'bootstrap.php';

$asset = new \ProjectSend\Classes\CustomAsset();
$asset_id = (int)$_GET['id'];
if (!is_integer($asset_id)) {
    exit_with_error_code(403);
}

$load_asset = $asset->get($asset_id);
if (!$load_asset) {
    exit_with_error_code(403);
}

$asset_arguments = $asset->getProperties();
$language = $asset_arguments['language'];

$active_nav = 'tools';

$page_title = sprintf(__('Edit %s asset', 'cftp_admin'), format_asset_language_name($language));

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

    $asset->set($asset_arguments);
    $edit_response = $asset->edit();

    if ($edit_response['query'] == 1) {
        $flash->success(__('Asset edited successfully'));
    } else {
        $flash->error(__('There was an error saving to the database'));
    }

    ps_redirect(BASE_URI . 'custom-assets-edit.php?id=' . $asset_id);
}
?>

<div class="row">
    <div class="col-12 col-sm-12 col-lg-6">

        <div class="white-box">
            <div class="white-box-interior">

                <?php
                // If the form was submitted with errors, show them here.
                echo $asset->getValidationErrors();

                $asset_form_type = 'edit';
                include_once FORMS_DIR . DS . 'assets.php';
                ?>
            </div>
        </div>
    </div>
</div>
<?php
include_once ADMIN_VIEWS_DIR . DS . 'footer.php';
