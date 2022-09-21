<?php
/**
 * This file generates the header for the back-end and also for the default
 * template.
 *
 * Other checks for user level are performed later to generate the different
 * menu items, and the content of the page that called this file.
 *
 * @package ProjectSend
 */
if (!defined('VIEW_TYPE')) define('VIEW_TYPE', 'private');

// Check for an active session
redirect_if_not_logged_in();

// Check if the current user has permission to view this page.
redirect_if_role_not_allowed($allowed_levels);

global $flash;

/** If no page title is defined, revert to a default one */
if (!isset($page_title)) { $page_title = __('System Administration','cftp_admin'); }

if (!isset($body_class)) { $body_class = array(); }

if ( !empty( $_COOKIE['menu_contracted'] ) && $_COOKIE['menu_contracted'] == 'true' ) {
    $body_class[] = 'menu_contracted';
}

$body_class[] = 'menu_hidden';

/**
 * Silent updates that are needed even if no user is logged in.
 */
require_once INCLUDES_DIR . DS .'core.update.silent.php';

// Run required database upgrades
$db_upgrade = new \ProjectSend\Classes\DatabaseUpgrade;
$db_upgrade->upgradeDatabase(false);

/**
 * Call the database update file to see if any change is needed,
 * but only if logged in as a system user.
 */
$core_update_allowed = array(9,8,7);
if (current_role_in($core_update_allowed)) {
    require_once INCLUDES_DIR . DS . 'core.update.php';
}

// Redirect if password needs to be changed
password_change_required();
?>
<!doctype html>
<html lang="<?php echo SITE_LANG; ?>">
<head>
    <meta charset="<?php echo(CHARSET); ?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <?php meta_noindex(); ?>

    <title><?php echo html_output( $page_title . ' &raquo; ' . htmlspecialchars(get_option('this_install_title'), ENT_QUOTES, CHARSET) ); ?></title>
    <?php meta_favicon(); ?>

    <?php
        render_assets('js', 'head');
        render_assets('css', 'head');

        render_custom_assets('head');
    ?>
</head>

<body <?php echo add_body_class( $body_class ); ?> <?php if (!empty($page_id)) { echo add_page_id($page_id); } ?>>
    <div class="container-custom">
        <?php include_once LAYOUT_DIR . DS . 'header-top.php'; ?>
        <?php include_once LAYOUT_DIR . DS . 'main-menu.php'; ?>

        <div class="main_content">
            <div class="container-fluid">
                <?php
                    render_custom_assets('body_top');

                    // Gets the mark up and values for the System Updated and errors messages.
                    include_once INCLUDES_DIR . DS . 'updates.messages.php';

                    include_once INCLUDES_DIR . DS . 'header-messages.php';
                ?>

                <div class="row">
                    <div class="col-xs-12">
                        <div id="section_title">
                            <h2><?php echo $page_title; ?></h2>
                        </div>
                    </div>
                </div>

                <?php
                    // Flash messages
                    if ($flash->hasMessages()) {
                        echo $flash;
                    }
